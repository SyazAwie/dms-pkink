<?php

namespace App\Http\Controllers;

use App\Models\IpDisekat;
use App\Models\LogAudit;
use App\Models\User;
use App\Support\KeadaanKeselamatan;
use App\Support\Keselamatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pusat Keselamatan (Fasa Keselamatan S2 + S3), SUPERADMIN/ADMIN.
 * S2: ringkasan ancaman, semakan pengerasan, tindakan sensitif.
 * S3 butang kecemasan: log keluar paksa, kunci akaun, sekat IP (pentadbir); log keluar
 * semua pengguna & mod kecemasan (SUPERADMIN sahaja, perlu kata laluan semula).
 * ADMIN tidak boleh bertindak ke atas akaun SUPERADMIN; tiada sesiapa ke atas diri sendiri.
 */
class KeselamatanController extends Controller
{
    public const TEMPOH = ['1j' => '1 jam', '24j' => '24 jam', '7h' => '7 hari', 'kekal' => 'Sehingga dibuka'];

    public function index()
    {
        $user = Auth::user();
        $ringkasan = Keselamatan::ringkasan();
        $senaraiSemak = Keselamatan::senaraiSemak();
        $bilMasalah = collect($senaraiSemak)->whereIn('status', ['ralat', 'amaran'])->count();

        $superadmin = $user->hasRole('SUPERADMIN');
        $sasaran = User::with('roles')->where('user_id', '!=', $user->user_id)->orderBy('nama_staff')->get()
            ->reject(fn ($u) => ! $superadmin && $u->roles->contains('kod_roles', 'SUPERADMIN'))->values();
        $dikunci = User::whereNotNull('dikunci_hingga')->where('dikunci_hingga', '>', now())->orderBy('nama_staff')->get();
        $ipDisekat = IpDisekat::aktif()->with('pengsekat')->latest('ip_disekat_id')->get();
        $kecemasan = KeadaanKeselamatan::kecemasan();

        return view('keselamatan.index', compact('ringkasan', 'senaraiSemak', 'bilMasalah', 'superadmin', 'sasaran', 'dikunci', 'ipDisekat', 'kecemasan'));
    }

    /** Pelan Tindak Balas Insiden (S5): docs/PELAN_TINDAK_BALAS_INSIDEN.md, boleh dicetak. */
    public function pelan()
    {
        $laluan = base_path('docs/PELAN_TINDAK_BALAS_INSIDEN.md');
        abort_unless(is_file($laluan), 404, 'Dokumen pelan tindak balas insiden tidak dijumpai.');
        $html = \Illuminate\Support\Str::markdown((string) file_get_contents($laluan), ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return view('keselamatan.pelan', compact('html'));
    }

    public function logKeluar(Request $request)
    {
        $sasaran = $this->sasaran($request);
        KeadaanKeselamatan::logKeluarPengguna($sasaran->user_id);
        $sasaran->forceFill(['remember_token' => null])->saveQuietly();
        $this->log($request, 'Log Keluar Paksa', 'users', $sasaran->user_id, ['pengguna' => $sasaran->nama_staff]);

        return back()->with('success', "Semua sesi {$sasaran->nama_staff} telah ditamatkan.");
    }

    public function kunci(Request $request)
    {
        $sasaran = $this->sasaran($request);
        $data = $request->validate([
            'tempoh' => ['required', Rule::in(array_keys(self::TEMPOH))],
            'sebab' => 'required|string|max:255',
        ], ['sebab.required' => 'Sila nyatakan sebab akaun dikunci.']);

        $hingga = match ($data['tempoh']) {
            '1j' => now()->addHour(), '24j' => now()->addDay(), '7h' => now()->addDays(7), default => now()->setDate(2099, 12, 31),
        };
        $sasaran->forceFill(['dikunci_hingga' => $hingga, 'sebab_kunci' => $data['sebab'], 'remember_token' => null])->saveQuietly();
        KeadaanKeselamatan::logKeluarPengguna($sasaran->user_id);
        $this->log($request, 'Kunci Akaun', 'users', $sasaran->user_id,
            ['pengguna' => $sasaran->nama_staff, 'tempoh' => self::TEMPOH[$data['tempoh']], 'sebab' => $data['sebab']]);

        return back()->with('success', "Akaun {$sasaran->nama_staff} dikunci ({$this->labelTempoh($data['tempoh'])}) dan semua sesinya ditamatkan.");
    }

    public function bukaKunci(Request $request)
    {
        $sasaran = $this->sasaran($request);
        $sasaran->forceFill(['dikunci_hingga' => null, 'sebab_kunci' => null])->saveQuietly();
        $this->log($request, 'Buka Kunci Akaun', 'users', $sasaran->user_id, ['pengguna' => $sasaran->nama_staff]);

        return back()->with('success', "Akaun {$sasaran->nama_staff} telah dibuka.");
    }

    public function sekatIp(Request $request)
    {
        $data = $request->validate([
            'alamat_ip' => 'required|ip',
            'tempoh' => ['required', Rule::in(array_keys(self::TEMPOH))],
            'sebab' => 'nullable|string|max:255',
        ], ['alamat_ip.ip' => 'Alamat IP tidak sah.']);

        if (in_array($data['alamat_ip'], IpDisekat::LOCALHOST, true) || $data['alamat_ip'] === $request->ip()) {
            return back()->with('error', 'Alamat IP ini tidak boleh disekat (pelayan sendiri, atau IP yang anda sedang gunakan).');
        }

        $sekatan = IpDisekat::create([
            'alamat_ip' => $data['alamat_ip'], 'sebab' => $data['sebab'], 'automatik' => false, 'disekat_oleh' => Auth::id(),
            'tamat_pada' => match ($data['tempoh']) {
                '1j' => now()->addHour(), '24j' => now()->addDay(), '7h' => now()->addDays(7), default => null
            },
        ]);
        $this->log($request, 'Sekat IP', 'ip_disekat', $sekatan->ip_disekat_id, $data + ['tempoh' => self::TEMPOH[$data['tempoh']]]);

        return back()->with('success', "IP {$data['alamat_ip']} disekat ({$this->labelTempoh($data['tempoh'])}).");
    }

    public function bukaIp(Request $request, IpDisekat $sekatan)
    {
        $sekatan->forceFill(['tamat_pada' => now()])->save();
        $this->log($request, 'Buka Sekatan IP', 'ip_disekat', $sekatan->ip_disekat_id, ['alamat_ip' => $sekatan->alamat_ip]);

        return back()->with('success', "Sekatan IP {$sekatan->alamat_ip} telah dibuka.");
    }

    public function logKeluarSemua(Request $request)
    {
        $this->pastikanSuperadmin($request);
        KeadaanKeselamatan::logKeluarSemua();
        // Pengguna yang menekan butang kekal log masuk
        $request->session()->put('masa_log_masuk', now()->timestamp);
        $this->log($request, 'Log Keluar Paksa', 'users', null, ['skop' => 'Semua pengguna']);

        return back()->with('success', 'Semua sesi pengguna lain telah ditamatkan. Mereka perlu log masuk semula.');
    }

    public function kecemasan(Request $request)
    {
        $this->pastikanSuperadmin($request);
        $data = $request->validate(['tindakan' => 'required|in:hidup,mati', 'sebab' => 'nullable|string|max:255']);

        if ($data['tindakan'] === 'hidup') {
            KeadaanKeselamatan::hidupkanKecemasan(Auth::user()->nama_staff, $data['sebab'] ?? null);
            $mesej = 'Mod kecemasan DIHIDUPKAN: hanya Super Admin boleh log masuk dan sistem kini baca sahaja.';
        } else {
            KeadaanKeselamatan::matikanKecemasan();
            $mesej = 'Mod kecemasan dimatikan. Sistem kembali seperti biasa.';
        }
        $this->log($request, 'Mod Kecemasan', 'sistem', null, ['tindakan' => $data['tindakan'], 'sebab' => $data['sebab'] ?? null]);

        return back()->with('success', $mesej);
    }

    /** Pengguna sasaran yang sah bagi pelaku semasa. */
    private function sasaran(Request $request): User
    {
        $request->validate(['user_id' => 'required|integer']);
        $sasaran = User::with('roles')->findOrFail($request->input('user_id'));

        abort_if((int) $sasaran->user_id === (int) Auth::id(), 403, 'Anda tidak boleh melakukan tindakan ini ke atas akaun sendiri.');
        abort_if($sasaran->roles->contains('kod_roles', 'SUPERADMIN') && ! Auth::user()->hasRole('SUPERADMIN'), 403,
            'Hanya Super Admin boleh bertindak ke atas akaun Super Admin.');

        return $sasaran;
    }

    private function pastikanSuperadmin(Request $request): void
    {
        abort_unless(Auth::user()->hasRole('SUPERADMIN'), 403, 'Tindakan ini untuk Super Admin sahaja.');
        $request->validateWithBag('kecemasan', ['kata_laluan' => 'required|string'], ['kata_laluan.required' => 'Masukkan kata laluan anda untuk mengesahkan.']);
        if (! Hash::check($request->input('kata_laluan'), Auth::user()->password)) {
            throw ValidationException::withMessages(['kata_laluan' => 'Kata laluan tidak tepat.'])->errorBag('kecemasan');
        }
    }

    private function labelTempoh(string $tempoh): string
    {
        return strtolower(self::TEMPOH[$tempoh]);
    }

    private function log(Request $request, string $tindakan, string $jadual, ?int $rekod, array $data): void
    {
        LogAudit::create([
            'user_id' => Auth::id(), 'tindakan' => $tindakan, 'nama_jadual' => $jadual, 'rekod_id' => $rekod,
            'data_lama' => null, 'data_baharu' => $data,
            'alamat_ip' => $request->ip(), 'peranti_pengguna' => $request->userAgent(), 'created_at' => now(),
        ]);
    }
}
