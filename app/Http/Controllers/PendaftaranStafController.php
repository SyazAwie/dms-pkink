<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Models\PendaftaranStaf;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PendaftaranDiluluskan;
use App\Notifications\PendaftaranDitolak;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * ============================================================================
 * PENDAFTARAN STAF BAHARU
 * ============================================================================
 * (1) Staf baharu isi borang (modal di halaman log masuk) → permohonan
 *     "Menunggu" dalam jadual pendaftaran_staf.
 * (2) SUPERADMIN/ADMIN lulus atau tolak di halaman Pengesahan Pendaftaran.
 *     - Lulus: cipta akaun users dengan kata laluan sementara (sah 72 jam,
 *       wajib tukar), emel dihantar kepada pemohon. SUPERADMIN pilih peranan;
 *       ADMIN sentiasa beri STAFF (selari dengan UserController).
 *     - Tolak: sebab wajib, dinyatakan dalam emel.
 * Emel dihantar DI DALAM transaksi: jika emel gagal, lulus/tolak dibatalkan
 * dan permohonan kekal "Menunggu".
 * ============================================================================
 */
class PendaftaranStafController extends Controller
{
    /** Tempoh sah kata laluan sementara (jam). */
    private const JAM_SAH_KATA_LALUAN = 72;

    /** Had permohonan bagi setiap IP: 3 setiap 10 minit. */
    private const HAD_PERMOHONAN = 3;

    private const TEMPOH_HAD_SAAT = 600;

    // ---- (1) Awam: hantar permohonan dari modal halaman log masuk ----
    public function simpan(Request $request)
    {
        $mesejBerjaya = 'Permohonan pendaftaran anda telah dihantar. Anda akan dimaklumkan melalui emel selepas permohonan disemak oleh pentadbir.';
        $kunciHad = 'daftar-staf|' . $request->ip();

        if (RateLimiter::tooManyAttempts($kunciHad, self::HAD_PERMOHONAN)) {
            $minit = (int) ceil(RateLimiter::availableIn($kunciHad) / 60);

            return back()->withInput()->withErrors([
                'pendaftaran' => "Terlalu banyak permohonan dari rangkaian ini. Cuba lagi dalam {$minit} minit.",
            ], 'pendaftaran');
        }

        // Perangkap bot: medan tersembunyi ini tidak kelihatan kepada manusia.
        // Jika diisi, buat seolah-olah berjaya tetapi jangan simpan apa-apa.
        if ($request->filled('laman_web')) {
            RateLimiter::hit($kunciHad, self::TEMPOH_HAD_SAAT);

            return redirect()->route('login')->with('success', $mesejBerjaya);
        }

        $data = $request->validateWithBag('pendaftaran', [
            'ic_pekerja' => [
                'required', 'digits:12',
                Rule::unique('users', 'ic_pekerja')->whereNull('deleted_at'),
                Rule::unique('pendaftaran_staf', 'ic_pekerja')->where('status', PendaftaranStaf::MENUNGGU),
            ],
            'nama_staff' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
                Rule::unique('pendaftaran_staf', 'email')->where('status', PendaftaranStaf::MENUNGGU),
            ],
            'bahagian_id' => ['required', Rule::exists('bahagian', 'bahagian_id')->whereNull('deleted_at')],
        ], [
            'ic_pekerja.required' => 'Sila masukkan No. Kad Pengenalan.',
            'ic_pekerja.digits' => 'No. Kad Pengenalan mesti 12 digit nombor tanpa sengkang (-).',
            'ic_pekerja.unique' => 'No. Kad Pengenalan ini sudah berdaftar atau mempunyai permohonan yang sedang menunggu pengesahan.',
            'nama_staff.required' => 'Sila masukkan nama penuh.',
            'nama_staff.max' => 'Nama penuh terlalu panjang.',
            'email.required' => 'Sila masukkan alamat emel.',
            'email.email' => 'Format alamat emel tidak sah.',
            'email.max' => 'Alamat emel terlalu panjang.',
            'email.unique' => 'Emel ini sudah berdaftar atau mempunyai permohonan yang sedang menunggu pengesahan.',
            'bahagian_id.required' => 'Sila pilih bahagian.',
            'bahagian_id.exists' => 'Bahagian yang dipilih tidak sah.',
        ]);

        try {
            DB::transaction(function () use ($request, $data) {
                $pendaftaran = PendaftaranStaf::create($data + ['alamat_ip' => $request->ip()]);

                LogAudit::create([
                    'user_id' => null, // pemohon belum mempunyai akaun
                    'tindakan' => 'Mohon Pendaftaran',
                    'nama_jadual' => 'pendaftaran_staf',
                    'rekod_id' => $pendaftaran->pendaftaran_staf_id,
                    'data_lama' => null,
                    'data_baharu' => $data,
                    'alamat_ip' => $request->ip(),
                    'peranti_pengguna' => $request->userAgent(),
                    'created_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            // Dua permohonan serentak dengan IC/emel sama: lajur janaan UNIQUE menolak yang kedua
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }

            return back()->withInput()->withErrors([
                'pendaftaran' => 'No. Kad Pengenalan atau emel ini sudah mempunyai permohonan yang sedang menunggu pengesahan.',
            ], 'pendaftaran');
        }

        RateLimiter::hit($kunciHad, self::TEMPOH_HAD_SAAT);

        return redirect()->route('login')->with('success', $mesejBerjaya);
    }

    // ---- (2) Admin: senarai permohonan ----
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in([PendaftaranStaf::MENUNGGU, PendaftaranStaf::DILULUSKAN, PendaftaranStaf::DITOLAK, 'Semua'])],
        ]);

        $status = $request->input('status', PendaftaranStaf::MENUNGGU);

        $query = PendaftaranStaf::with(['bahagian', 'penyemak'])->orderByDesc('pendaftaran_staf_id');
        if ($status !== 'Semua') {
            $query->where('status', $status);
        }

        $senarai = $query->paginate(15)->withQueryString();
        $bilMenunggu = PendaftaranStaf::where('status', PendaftaranStaf::MENUNGGU)->count();
        $senaraiRole = Auth::user()->hasRole('SUPERADMIN') ? Role::orderBy('roles_id')->get() : collect();

        return view('pendaftaran.index', compact('senarai', 'status', 'bilMenunggu', 'senaraiRole'));
    }

    public function lulus(Request $request, $id)
    {
        $superadmin = Auth::user()->hasRole('SUPERADMIN');

        // Hanya SUPERADMIN boleh memilih peranan; ADMIN sentiasa beri STAFF
        if ($superadmin) {
            $request->validate([
                'roles' => ['required', 'array', 'min:1'],
                'roles.*' => ['exists:roles,roles_id'],
            ], [
                'roles.required' => 'Pilih sekurang-kurangnya satu peranan.',
                'roles.min' => 'Pilih sekurang-kurangnya satu peranan.',
                'roles.*.exists' => 'Peranan yang dipilih tidak sah.',
            ]);
            $idPeranan = array_map('intval', $request->input('roles'));
        } else {
            $idPeranan = [Role::where('kod_roles', 'STAFF')->firstOrFail()->roles_id];
        }

        PendaftaranStaf::findOrFail($id); // 404 jika tiada, sebelum masuk transaksi
        $kataLaluanSementara = Str::password(10, true, true, false);

        try {
            $pendaftaran = DB::transaction(function () use ($request, $id, $idPeranan, $kataLaluanSementara) {
                $pendaftaran = $this->permohonanMenunggu($id);

                // Semak semula: IC/emel mungkin sudah digunakan sejak permohonan dihantar
                $sudahAda = User::where('ic_pekerja', $pendaftaran->ic_pekerja)
                    ->orWhere('email', $pendaftaran->email)
                    ->exists();
                if ($sudahAda) {
                    throw new \DomainException('No. Kad Pengenalan atau emel pemohon ini sudah digunakan oleh akaun lain. Sila tolak permohonan ini.');
                }

                $user = new User([
                    'ic_pekerja' => $pendaftaran->ic_pekerja,
                    'nama_staff' => $pendaftaran->nama_staff,
                    'email' => $pendaftaran->email,
                    'bahagian_id' => $pendaftaran->bahagian_id,
                    'password' => Hash::make($kataLaluanSementara),
                ]);
                $user->perlu_tukar_kata_laluan = true;
                $user->kata_laluan_tamat_pada = now()->addHours(self::JAM_SAH_KATA_LALUAN);
                $user->save();
                $user->roles()->attach($idPeranan);

                $pendaftaran->status = PendaftaranStaf::DILULUSKAN;
                $pendaftaran->disemak_oleh = Auth::id();
                $pendaftaran->disemak_pada = now();
                $pendaftaran->user_id = $user->user_id;
                $pendaftaran->save();

                $this->log($request, $pendaftaran, 'Lulus Pendaftaran', [
                    'status' => PendaftaranStaf::DILULUSKAN,
                    'user_id' => $user->user_id,
                    'peranan' => Role::whereIn('roles_id', $idPeranan)->orderBy('kod_roles')->pluck('kod_roles')->all(),
                ]);

                // Emel terakhir: jika gagal, semua di atas dibatalkan
                Notification::route('mail', $pendaftaran->email)
                    ->notifyNow(new PendaftaranDiluluskan($pendaftaran, $kataLaluanSementara, self::JAM_SAH_KATA_LALUAN));

                return $pendaftaran;
            });
        } catch (\DomainException $e) {
            // Peraturan perniagaan (sudah diproses / IC atau emel sudah digunakan)
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // Biasanya kegagalan emel (SMTP). Transaksi telah dibatalkan.
            Log::error("Gagal meluluskan pendaftaran_staf_id {$id}: " . $e->getMessage());

            return back()->with('error', 'Emel kepada pemohon gagal dihantar, jadi permohonan TIDAK diluluskan dan masih menunggu. Sila semak tetapan emel sistem dan cuba lagi.');
        }

        return back()->with('success', "Pendaftaran {$pendaftaran->nama_staff} diluluskan. Emel berserta kata laluan sementara telah dihantar ke {$pendaftaran->email}.");
    }

    public function tolak(Request $request, $id)
    {
        $request->validate([
            'sebab_tolak' => ['required', 'string', 'max:500'],
        ], [
            'sebab_tolak.required' => 'Sila nyatakan sebab penolakan.',
            'sebab_tolak.max' => 'Sebab penolakan tidak boleh melebihi 500 aksara.',
        ]);

        PendaftaranStaf::findOrFail($id); // 404 jika tiada, sebelum masuk transaksi

        try {
            $pendaftaran = DB::transaction(function () use ($request, $id) {
                $pendaftaran = $this->permohonanMenunggu($id);

                $pendaftaran->status = PendaftaranStaf::DITOLAK;
                $pendaftaran->sebab_tolak = $request->sebab_tolak;
                $pendaftaran->disemak_oleh = Auth::id();
                $pendaftaran->disemak_pada = now();
                $pendaftaran->save();

                $this->log($request, $pendaftaran, 'Tolak Pendaftaran', [
                    'status' => PendaftaranStaf::DITOLAK,
                    'sebab_tolak' => $request->sebab_tolak,
                ]);

                Notification::route('mail', $pendaftaran->email)
                    ->notifyNow(new PendaftaranDitolak($pendaftaran));

                return $pendaftaran;
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error("Gagal menolak pendaftaran_staf_id {$id}: " . $e->getMessage());

            return back()->with('error', 'Emel kepada pemohon gagal dihantar, jadi permohonan TIDAK ditolak dan masih menunggu. Sila semak tetapan emel sistem dan cuba lagi.');
        }

        return back()->with('success', "Pendaftaran {$pendaftaran->nama_staff} ditolak. Emel makluman telah dihantar ke {$pendaftaran->email}.");
    }

    /**
     * Kunci baris permohonan (elak dua admin bertindak serentak) dan pastikan
     * ia masih "Menunggu".
     */
    private function permohonanMenunggu($id): PendaftaranStaf
    {
        $pendaftaran = PendaftaranStaf::lockForUpdate()->findOrFail($id);

        if ($pendaftaran->status !== PendaftaranStaf::MENUNGGU) {
            throw new \DomainException("Permohonan ini telah {$pendaftaran->status} dan tidak boleh diproses lagi.");
        }

        return $pendaftaran;
    }

    private function log(Request $request, PendaftaranStaf $pendaftaran, string $tindakan, array $dataBaharu): void
    {
        LogAudit::create([
            'user_id' => Auth::id(),
            'tindakan' => $tindakan,
            'nama_jadual' => 'pendaftaran_staf',
            'rekod_id' => $pendaftaran->pendaftaran_staf_id,
            'data_lama' => ['status' => PendaftaranStaf::MENUNGGU],
            'data_baharu' => $dataBaharu,
            'alamat_ip' => $request->ip(),
            'peranti_pengguna' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
