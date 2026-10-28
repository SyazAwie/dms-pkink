<?php

namespace App\Http\Controllers;

use App\Models\DelegasiKelulusan;
use App\Models\LogAudit;
use App\Models\User;
use App\Notifications\NotifikasiDms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Delegasi kelulusan semasa cuti (P5). PENYOKONG/PELULUS melantik pengganti bagi diri
 * sendiri; SUPERADMIN/ADMIN boleh mengurus delegasi sesiapa. Penerima melihat delegasi
 * yang diterimanya. Delegasi tidak dipadam, hanya dibatalkan (jejak audit).
 */
class DelegasiController extends Controller
{
    /** Tempoh maksimum satu delegasi (hari). */
    public const MAKS_HARI = 180;

    public function index()
    {
        $user = Auth::user();
        $pentadbir = $this->pentadbir($user);

        $senarai = DelegasiKelulusan::with(['pemberi' => fn ($q) => $q->withTrashed(), 'penerima' => fn ($q) => $q->withTrashed()])
            ->when(! $pentadbir, fn ($q) => $q->where(fn ($w) => $w->where('pemberi_id', $user->user_id)->orWhere('penerima_id', $user->user_id)))
            ->orderByDesc('tarikh_mula')->orderByDesc('delegasi_id')->paginate(15);

        // Pemberi yang boleh dipilih: diri sendiri, atau (pentadbir) semua pemegang peranan
        $pemberi = $pentadbir
            ? User::whereHas('roles', fn ($q) => $q->whereIn('kod_roles', DelegasiKelulusan::PERANAN))->with('roles')->orderBy('nama_staff')->get()
            : collect(array_intersect(DelegasiKelulusan::PERANAN, $user->kodPerananSendiri()) ? [$user] : []);
        $penerima = User::orderBy('nama_staff')->get(['user_id', 'nama_staff', 'jawatan']);

        return view('delegasi.index', compact('senarai', 'pemberi', 'penerima', 'pentadbir'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'pemberi_id' => ['required', 'integer', Rule::exists('users', 'user_id')->whereNull('deleted_at')],
            'kod_peranan' => ['required', Rule::in(DelegasiKelulusan::PERANAN)],
            'penerima_id' => ['required', 'integer', 'different:pemberi_id', Rule::exists('users', 'user_id')->whereNull('deleted_at')],
            'tarikh_mula' => ['required', 'date', 'after_or_equal:today'],
            'tarikh_tamat' => ['required', 'date', 'after_or_equal:tarikh_mula'],
            'sebab' => ['nullable', 'string', 'max:255'],
        ], [
            'penerima_id.different' => 'Pengganti mesti pegawai lain.',
            'tarikh_mula.after_or_equal' => 'Tarikh mula tidak boleh sebelum hari ini.',
            'tarikh_tamat.after_or_equal' => 'Tarikh tamat mesti pada atau selepas tarikh mula.',
        ], [
            'pemberi_id' => 'pegawai', 'kod_peranan' => 'peranan', 'penerima_id' => 'pengganti',
            'tarikh_mula' => 'tarikh mula', 'tarikh_tamat' => 'tarikh tamat',
        ]);

        if (! $this->pentadbir($user) && (int) $data['pemberi_id'] !== (int) $user->user_id) {
            abort(403, 'Anda hanya boleh melantik pengganti bagi diri sendiri.');
        }

        $pemberi = User::with('roles')->findOrFail($data['pemberi_id']);
        $ralat = match (true) {
            ! in_array($data['kod_peranan'], $pemberi->kodPerananSendiri(), true) => ['kod_peranan' => "{$pemberi->nama_staff} tidak memegang peranan {$data['kod_peranan']}."],
            now()->parse($data['tarikh_mula'])->diffInDays(now()->parse($data['tarikh_tamat'])) >= self::MAKS_HARI => ['tarikh_tamat' => 'Tempoh delegasi maksimum '.self::MAKS_HARI.' hari.'],
            DelegasiKelulusan::whereNull('dibatalkan_pada')->where('pemberi_id', $pemberi->user_id)->where('kod_peranan', $data['kod_peranan'])
                ->whereDate('tarikh_mula', '<=', $data['tarikh_tamat'])->whereDate('tarikh_tamat', '>=', $data['tarikh_mula'])->exists() => ['tarikh_mula' => 'Sudah ada delegasi peranan ini yang bertindih dengan tarikh tersebut. Batalkan yang lama dahulu.'],
            default => null,
        };
        if ($ralat) {
            throw ValidationException::withMessages($ralat);
        }

        $delegasi = DelegasiKelulusan::create($data + ['dicipta_oleh' => $user->user_id]);
        $this->log($request, 'Cipta Delegasi', $delegasi, null, $delegasi->only(['pemberi_id', 'penerima_id', 'kod_peranan', 'sebab'])
            + ['tarikh_mula' => $data['tarikh_mula'], 'tarikh_tamat' => $data['tarikh_tamat']]);
        $this->maklumkanPenerima($delegasi->load('pemberi', 'penerima'));

        return redirect()->route('delegasi.index')->with('success', "Delegasi {$data['kod_peranan']} kepada {$delegasi->penerima->nama_staff} telah disimpan.");
    }

    public function batal(Request $request, DelegasiKelulusan $delegasi)
    {
        $user = Auth::user();
        abort_unless($this->pentadbir($user) || in_array((int) $user->user_id, [(int) $delegasi->pemberi_id, (int) $delegasi->dicipta_oleh], true), 403,
            'Anda tidak boleh membatalkan delegasi ini.');

        if ($delegasi->dibatalkan_pada || $delegasi->tarikh_tamat->lt(today())) {
            return back()->with('error', 'Delegasi ini sudah tamat atau dibatalkan.');
        }

        $delegasi->forceFill(['dibatalkan_pada' => now()])->save();
        $this->log($request, 'Batal Delegasi', $delegasi, ['status' => 'Aktif'], ['status' => 'Dibatalkan']);

        return back()->with('success', 'Delegasi telah dibatalkan.');
    }

    private function pentadbir(User $user): bool
    {
        return $user->hasAnyRole('SUPERADMIN', 'ADMIN');
    }

    private function maklumkanPenerima(DelegasiKelulusan $d): void
    {
        try {
            $d->penerima->notify(new NotifikasiDms('delegasi', 'Anda dilantik sebagai pengganti',
                "{$d->pemberi->nama_staff} melantik anda sebagai {$d->kod_peranan} pengganti dari {$d->tarikh_mula->format('d/m/Y')} hingga {$d->tarikh_tamat->format('d/m/Y')}.",
                route('delegasi.index'), 'bi-person-check', 'biru'));
        } catch (\Throwable $e) {
            Log::warning('Notifikasi delegasi gagal: '.$e->getMessage());
        }
    }

    private function log(Request $request, string $tindakan, DelegasiKelulusan $d, ?array $lama, ?array $baharu): void
    {
        LogAudit::create([
            'user_id' => Auth::id(), 'tindakan' => $tindakan, 'nama_jadual' => 'delegasi_kelulusan', 'rekod_id' => $d->delegasi_id,
            'data_lama' => $lama, 'data_baharu' => $baharu,
            'alamat_ip' => $request->ip(), 'peranti_pengguna' => $request->userAgent(), 'created_at' => now(),
        ]);
    }
}
