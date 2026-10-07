<?php

namespace App\Http\Controllers;

use App\Models\Bahagian;
use App\Models\LogAudit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        // Tarik semua pengguna bersama bahagian dan peranan mereka
        $senarai = User::with(['bahagian', 'roles'])->get();
        return view('users.index', compact('senarai'));
    }

    public function create()
    {
        $senarai_bahagian = Bahagian::all();
        $senarai_role = Role::all();
        return view('users.create', compact('senarai_bahagian', 'senarai_role'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'ic_pekerja' => ['required', Rule::unique('users')->whereNull('deleted_at')],
            'nama_staff' => 'required',
            'email' => ['required', 'email', Rule::unique('users')->whereNull('deleted_at')],
            'bahagian_id' => 'required',
            'password' => 'required|min:6',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,roles_id',
        ]);

        // Hanya SUPERADMIN boleh menetapkan peranan. Jika pendaftar ialah ADMIN
        // (atau tiada peranan dipilih), kakitangan baharu mendapat peranan lalai STAFF.
        $idPeranan = Auth::user()->hasRole('SUPERADMIN')
            ? array_map('intval', $request->input('roles', []))
            : [];

        if (empty($idPeranan)) {
            $idPeranan = [Role::where('kod_roles', 'STAFF')->firstOrFail()->roles_id];
        }

        DB::transaction(function () use ($request, $idPeranan) {
            $user = User::create([
                'ic_pekerja' => $request->ic_pekerja,
                'nama_staff' => $request->nama_staff,
                'email' => $request->email,
                'bahagian_id' => $request->bahagian_id,
                'password' => Hash::make($request->password),
            ]);

            $user->roles()->attach($idPeranan);
            $this->logPeranan($request, $user, [], $idPeranan);
        });

        return redirect()->route('users.index')->with('success', 'Kakitangan berjaya didaftarkan berserta peranan sistem!');
    }

    public function edit($id)
    {
        $user = User::with('roles')->where('user_id', $id)->firstOrFail();
        $this->pastikanBolehUrus($user);

        $senarai_bahagian = Bahagian::all();
        $senarai_role = Role::all();
        $idPerananSemasa = $user->roles->pluck('roles_id')->all();

        return view('users.edit', compact('user', 'senarai_bahagian', 'senarai_role', 'idPerananSemasa'));
    }

    public function update(Request $request, $id)
    {
        $user = User::with('roles')->where('user_id', $id)->firstOrFail();
        $this->pastikanBolehUrus($user);

        $request->validate([
            'ic_pekerja' => ['required', Rule::unique('users')->ignore($id, 'user_id')->whereNull('deleted_at')],
            'nama_staff' => 'required',
            'email' => ['required', 'email', Rule::unique('users')->ignore($id, 'user_id')->whereNull('deleted_at')],
            'bahagian_id' => 'required',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,roles_id',
        ]);

        // Peranan hanya boleh diubah oleh SUPERADMIN, dan hanya jika borang menghantar bahagian peranan
        $tukarPeranan = Auth::user()->hasRole('SUPERADMIN') && $request->boolean('roles_dihantar');
        $idPerananBaharu = [];

        if ($tukarPeranan) {
            $idPerananBaharu = array_map('intval', $request->input('roles', []));

            if (empty($idPerananBaharu)) {
                return back()->withInput()->withErrors(['roles' => 'Pilih sekurang-kurangnya satu peranan.']);
            }

            // Jangan benarkan sistem kehilangan Super Admin terakhir
            $idSuperadmin = Role::where('kod_roles', 'SUPERADMIN')->value('roles_id');
            if ($user->hasRole('SUPERADMIN') && !in_array((int) $idSuperadmin, $idPerananBaharu, true)) {
                $bilSuperadmin = User::whereHas('roles', fn ($q) => $q->where('kod_roles', 'SUPERADMIN'))->count();
                if ($bilSuperadmin <= 1) {
                    return back()->withInput()->withErrors([
                        'roles' => 'Peranan SUPERADMIN tidak boleh dibuang daripada satu-satunya Super Admin dalam sistem.',
                    ]);
                }
            }
        }

        $dataKemaskini = [
            'ic_pekerja' => $request->ic_pekerja,
            'nama_staff' => $request->nama_staff,
            'email' => $request->email,
            'bahagian_id' => $request->bahagian_id,
        ];

        // Hanya kemas kini kata laluan jika ruangan diisi
        if ($request->filled('password')) {
            $dataKemaskini['password'] = Hash::make($request->password);
        }

        DB::transaction(function () use ($request, $user, $dataKemaskini, $tukarPeranan, $idPerananBaharu) {
            $user->update($dataKemaskini);

            if ($tukarPeranan) {
                $idLama = $user->roles->pluck('roles_id')->map(fn ($v) => (int) $v)->all();
                $user->roles()->sync($idPerananBaharu);
                $this->logPeranan($request, $user, $idLama, $idPerananBaharu);
            }

            // UserObserver tidak merekod kata laluan; catat kejadian tanpa nilainya
            if ($request->filled('password')) {
                LogAudit::create([
                    'user_id' => Auth::id(),
                    'tindakan' => 'Tukar Kata Laluan',
                    'nama_jadual' => $user->getTable(),
                    'rekod_id' => $user->getKey(),
                    'data_lama' => null,
                    'data_baharu' => null,
                    'alamat_ip' => $request->ip(),
                    'peranti_pengguna' => $request->userAgent(),
                    'created_at' => now(),
                ]);
            }
        });

        return redirect()->route('users.index')->with('success', 'Maklumat kakitangan berjaya dikemas kini!');
    }

    public function destroy($id)
    {
        $user = User::with('roles')->where('user_id', $id)->firstOrFail();
        $this->pastikanBolehUrus($user);

        if ((int) $user->user_id === (int) Auth::id()) {
            abort(403, 'Anda tidak boleh memadam akaun anda sendiri.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'Kakitangan telah dipadam.');
    }

    /**
     * Akaun SUPERADMIN hanya boleh diurus oleh SUPERADMIN. Tanpa semakan ini,
     * seorang ADMIN boleh menukar kata laluan Super Admin lalu log masuk sebagai beliau
     * (peningkatan kuasa / privilege escalation).
     */
    private function pastikanBolehUrus(User $sasaran): void
    {
        if ($sasaran->hasRole('SUPERADMIN') && !Auth::user()->hasRole('SUPERADMIN')) {
            abort(403, 'Hanya Super Admin boleh mengurus akaun Super Admin.');
        }
    }

    /**
     * Rekod perubahan peranan dalam log audit (kod peranan, bukan ID).
     */
    private function logPeranan(Request $request, User $user, array $idLama, array $idBaharu): void
    {
        $lama = array_map('intval', $idLama);
        $baharu = array_map('intval', $idBaharu);
        sort($lama);
        sort($baharu);

        if ($lama === $baharu) {
            return;
        }

        $kod = fn (array $ids) => Role::whereIn('roles_id', $ids)->orderBy('kod_roles')->pluck('kod_roles')->all();

        LogAudit::create([
            'user_id' => Auth::id(),
            'tindakan' => 'Tetapan Peranan',
            'nama_jadual' => 'user_roles',
            'rekod_id' => $user->getKey(),
            'data_lama' => ['peranan' => $kod($lama)],
            'data_baharu' => ['peranan' => $kod($baharu)],
            'alamat_ip' => $request->ip(),
            'peranti_pengguna' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}