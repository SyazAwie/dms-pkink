<?php

namespace App\Http\Controllers;

use App\Models\Bahagian;
use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Papan Pemuka — kandungan berbeza ikut peranan pengguna. Semua angka
 * ditarik terus daripada pangkalan data sebenar (tiada data reka/statik).
 *
 * Nota: "Fail Menunggu OCR" cuma kiraan status_ocr='Pending' — BUKAN
 * penilaian AI, sebab enjin OCR belum disepadukan (lihat README).
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $data = ['peranan' => $user->roles->pluck('kod_roles')->all()];

        // ---- Kad asas: dikongsi oleh semua peranan yang ada skop dokumen ----
        if ($user->hasAnyRole('STAFF', 'PENGURUS', 'KERANI', 'ADMIN', 'SUPERADMIN')) {
            $skop = Dokumen::bolehDilihat($user);

            $data['jumlahDokumen'] = (clone $skop)->count();
            $data['ocrPending'] = DokumenScan::whereHas('dokumen', fn ($q) => $q->bolehDilihat($user))
                ->where('status_ocr', 'Pending')->count();
            $data['dokumenTerkini'] = (clone $skop)->with(['jenisDokumen', 'pemuatNaik'])
                ->latest('dokumen_id')->limit(5)->get();
            $data['trendBulanan'] = $this->trendBulanan(clone $skop);
        }

        // ---- STAFF: status permohonan sendiri ----
        if ($user->hasRole('STAFF')) {
            $data['permohonanSaya'] = Borang::where('pemohon_id', $user->user_id)
                ->selectRaw('status_permohonan, COUNT(*) as jumlah')
                ->groupBy('status_permohonan')->pluck('jumlah', 'status_permohonan');
        }

        // ---- PENGURUS: pecahan kategori dokumen bahagian sendiri ----
        if ($user->hasRole('PENGURUS') && $user->bahagian_id) {
            $data['pecahanKategori'] = $this->pecahanKategori(Dokumen::bolehDilihat($user));
        }

        // ---- PENYOKONG: giliran sokongan ----
        if ($user->hasAnyRole('PENYOKONG', 'SUPERADMIN', 'ADMIN')) {
            $data['menungguSokongan'] = Borang::where('status_permohonan', 'Dalam Proses')->count();
            $data['disokongBulanIni'] = Borang::where('pegawai_penyokong_id', $user->user_id)
                ->whereMonth('tarikh_sokongan', now()->month)->whereYear('tarikh_sokongan', now()->year)->count();
        }

        // ---- PELULUS: giliran kelulusan ----
        if ($user->hasAnyRole('PELULUS', 'SUPERADMIN', 'ADMIN')) {
            $data['menungguKelulusan'] = Borang::where('status_permohonan', 'Disokong')->count();
            $data['diluluskanBulanIni'] = Borang::where('pegawai_pelulus_id', $user->user_id)
                ->whereMonth('tarikh_kelulusan', now()->month)->whereYear('tarikh_kelulusan', now()->year)->count();
        }

        // Senarai ringkas untuk Penyokong/Pelulus semak terus dari dashboard
        if ($user->hasAnyRole('PENYOKONG', 'PELULUS')) {
            $statusDikehendaki = $user->hasRole('PELULUS') ? 'Disokong' : 'Dalam Proses';
            $data['senaraiMenunggu'] = Borang::with(['dokumen.jenisDokumen', 'pemohon'])
                ->where('status_permohonan', $statusDikehendaki)
                ->latest('borang_id')->limit(5)->get();
        }

        // ---- AUDIT: ringkasan log ----
        if ($user->hasRole('AUDIT')) {
            $data['logHariIni'] = LogAudit::whereDate('created_at', today())->count();
            $data['logMingguIni'] = LogAudit::where('created_at', '>=', now()->subDays(7))->count();
            $data['pecahanTindakan'] = $this->pecahanTindakan();
        }

        // ---- ADMIN / SUPERADMIN: ringkasan eksekutif ----
        if ($user->hasAnyRole('SUPERADMIN', 'ADMIN')) {
            $data['jumlahKategori'] = JenisDokumen::count();
            $data['jumlahKakitangan'] = User::count();
            $data['logHariIni'] = LogAudit::whereDate('created_at', today())->count();
            $data['permohonanBreakdown'] = Borang::selectRaw('status_permohonan, COUNT(*) as jumlah')
                ->groupBy('status_permohonan')->pluck('jumlah', 'status_permohonan');
            $data['purataMasaKelulusan'] = $this->purataMasaKelulusanJam();
            $data['pecahanKategori'] = $this->pecahanKategori(Dokumen::query());
        }

        return view('dashboard.index', $data);
    }

    /**
     * Bilangan dokumen 6 bulan terakhir (termasuk bulan tanpa rekod, supaya
     * carta garis tidak terputus/mengelirukan).
     */
    private function trendBulanan($query): array
    {
        $mula = now()->subMonths(5)->startOfMonth();

        $mentah = (clone $query)
            ->where('dokumen.created_at', '>=', $mula)
            ->selectRaw("DATE_FORMAT(dokumen.created_at, '%Y-%m') as bulan, COUNT(*) as jumlah")
            ->groupBy('bulan')
            ->pluck('jumlah', 'bulan');

        $label = [];
        $nilai = [];
        for ($i = 5; $i >= 0; $i--) {
            $tarikh = now()->subMonths($i);
            $kunci = $tarikh->format('Y-m');
            $label[] = $tarikh->translatedFormat('M Y');
            $nilai[] = (int) ($mentah[$kunci] ?? 0);
        }

        return ['label' => $label, 'nilai' => $nilai];
    }

    /** Pecahan bilangan dokumen ikut Jenis Dokumen, untuk carta donut. */
    private function pecahanKategori($query): array
    {
        $baris = (clone $query)
            ->join('jenis_dokumen', 'dokumen.jenis_dokumen_id', '=', 'jenis_dokumen.jenis_dokumen_id')
            ->selectRaw('jenis_dokumen.nama_dokumen as label, COUNT(*) as jumlah')
            ->groupBy('jenis_dokumen.nama_dokumen')
            ->orderByDesc('jumlah')
            ->limit(6)
            ->get();

        return ['label' => $baris->pluck('label')->all(), 'nilai' => $baris->pluck('jumlah')->all()];
    }

    /** Pecahan log audit minggu ini ikut kumpulan tindakan (Cipta/Kemaskini/Padam/Lain). */
    private function pecahanTindakan(): array
    {
        $baris = LogAudit::where('created_at', '>=', now()->subDays(7))
            ->selectRaw("
                CASE
                    WHEN tindakan LIKE 'Cipta%' THEN 'Cipta'
                    WHEN tindakan LIKE 'Kemaskini%' THEN 'Kemaskini'
                    WHEN tindakan LIKE 'Padam%' THEN 'Padam'
                    ELSE 'Lain-lain'
                END as kumpulan,
                COUNT(*) as jumlah
            ")
            ->groupBy('kumpulan')
            ->pluck('jumlah', 'kumpulan');

        $susunan = ['Cipta', 'Kemaskini', 'Padam', 'Lain-lain'];
        return ['label' => $susunan, 'nilai' => array_map(fn ($k) => (int) ($baris[$k] ?? 0), $susunan)];
    }

    /** Purata masa (jam) dari dihantar hingga diluluskan, bagi borang yang sudah Diluluskan. */
    private function purataMasaKelulusanJam(): ?float
    {
        $purata = Borang::where('status_permohonan', 'Diluluskan')
            ->whereNotNull('tarikh_kelulusan')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, tarikh_permohonan, tarikh_kelulusan)) as purata')
            ->value('purata');

        return $purata !== null ? round((float) $purata, 1) : null;
    }
}