<?php

namespace App\Http\Controllers;

use App\Models\Bahagian;
use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenScan;
use App\Models\DokumenOcr;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Papan Pemuka — satu bahagian bagi setiap peranan pengguna, disusun ikut keutamaan
 * (tugas yang memerlukan tindakan dahulu). Setiap bahagian menjawab: apa yang perlu
 * saya buat, apa status saya, dan (bagi pentadbir) apa trendnya. Pengguna berbilang
 * peranan melihat beberapa bahagian. Semua angka daripada pangkalan data sebenar.
 *
 * Paparan: resources/views/dashboard/index.blade.php + dashboard/partials/{bahagian}.blade.php
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $bahagian = [];

        if ($user->hasRole('STAFF')) {
            $bahagian['staf'] = $this->dataStaf($user);
        }

        // Peranan yang belum dibina semula: paparan lama (sementara)
        if ($user->hasAnyRole('SUPERADMIN', 'ADMIN', 'PENYOKONG', 'PELULUS', 'AUDIT', 'KERANI', 'PENGURUS')) {
            $bahagian = ['lama' => $this->dataLama($user)] + $bahagian;
        }

        return view('dashboard.index', [
            'bahagian' => $bahagian,
            'tindakanPantas' => $this->tindakanPantas($user),
        ]);
    }

    /** Butang pintasan di bahagian atas, ikut gabungan peranan (maksimum 4). */
    private function tindakanPantas(User $user): array
    {
        $tindakan = [];
        if ($user->can('create', Dokumen::class)) {
            $tindakan[] = ['label' => 'Isi Borang', 'ikon' => 'bi-ui-checks-grid', 'url' => route('isi-borang.index'), 'utama' => true];
            $tindakan[] = ['label' => 'Imbas & Hantar', 'ikon' => 'bi-send-check', 'url' => route('imbas-hantar.create')];
        }
        if ($user->hasAnyRole('SUPERADMIN', 'ADMIN', 'KERANI')) {
            $tindakan[] = ['label' => 'Imbas & Arkib', 'ikon' => 'bi-magic', 'url' => route('imbas-arkib.create')];
        }
        if ($user->hasAnyRole('PENYOKONG', 'PELULUS')) {
            array_unshift($tindakan, ['label' => 'Semak Kelulusan', 'ikon' => 'bi-check2-square', 'url' => route('kelulusan.index'), 'utama' => true]);
        }
        if ($user->hasAnyRole('SUPERADMIN', 'AUDIT')) {
            $tindakan[] = ['label' => 'Log Audit', 'ikon' => 'bi-shield-check', 'url' => route('log-audit.index')];
        }

        return array_slice($tindakan, 0, 4);
    }

    /**
     * STAFF: permohonan sendiri. "Perlu tindakan" = ditolak (hantar semula);
     * "Belum dihantar" = dokumen sendiri tanpa borang kelulusan.
     */
    private function dataStaf(User $user): array
    {
        $status = Borang::dokumenAktif()->where('pemohon_id', $user->user_id)
            ->selectRaw('status_permohonan, COUNT(*) as jumlah')
            ->groupBy('status_permohonan')->pluck('jumlah', 'status_permohonan');

        $dokumenSaya = Dokumen::where('created_by', $user->user_id);

        return [
            'sedangDiproses' => (int) ($status['Dalam Proses'] ?? 0) + (int) ($status['Disokong'] ?? 0),
            'perluTindakan' => (int) ($status['Ditolak'] ?? 0),
            'diluluskan' => (int) ($status['Diluluskan'] ?? 0),
            'belumDihantar' => (clone $dokumenSaya)->doesntHave('borang')->count(),
            'terkini' => (clone $dokumenSaya)->with(['jenisDokumen', 'borang'])->latest('dokumen_id')->limit(5)->get(),
        ];
    }

    /** Langkah seterusnya bagi status borang (dipaparkan dalam senarai STAFF). */
    public static function langkahSeterusnya(?string $status): string
    {
        return match ($status) {
            null => 'Belum dihantar untuk kelulusan',
            'Dalam Proses' => 'Menunggu sokongan Penyokong',
            'Disokong' => 'Menunggu kelulusan Pelulus',
            'Ditolak' => 'Semak ulasan & hantar semula',
            'Diluluskan' => 'Selesai',
            default => $status,
        };
    }

    /** Paparan lama bagi peranan yang belum dibina semula (dibuang dalam commit terakhir). */
    private function dataLama(User $user): array
    {
        $data = [];

        if ($user->hasAnyRole('PENGURUS', 'KERANI', 'ADMIN', 'SUPERADMIN')) {
            $skop = Dokumen::bolehDilihat($user);

            $data['jumlahDokumen'] = (clone $skop)->count();
            $skopScan = DokumenScan::whereHas('dokumen', fn ($q) => $q->bolehDilihat($user));
            $data['ocrPending'] = (clone $skopScan)->where('status_ocr', 'Pending')->count();
            $data['jumlahFailDiimbas'] = (clone $skopScan)->count();
            $data['dokumenTerkini'] = (clone $skop)->with(['jenisDokumen', 'pemuatNaik'])
                ->latest('dokumen_id')->limit(5)->get();
            $data['trendBulanan'] = $this->trendBulanan(clone $skop);
        }

        if ($user->hasRole('PENGURUS') && $user->bahagian_id) {
            $data['pecahanKategori'] = $this->pecahanKategori(Dokumen::bolehDilihat($user));
        }

        if ($user->hasAnyRole('PENYOKONG', 'SUPERADMIN', 'ADMIN')) {
            $data['menungguSokongan'] = Borang::dokumenAktif()->where('status_permohonan', 'Dalam Proses')->count();
            $data['disokongBulanIni'] = Borang::dokumenAktif()->where('pegawai_penyokong_id', $user->user_id)
                ->whereMonth('tarikh_sokongan', now()->month)->whereYear('tarikh_sokongan', now()->year)->count();
        }

        if ($user->hasAnyRole('PELULUS', 'SUPERADMIN', 'ADMIN')) {
            $data['menungguKelulusan'] = Borang::dokumenAktif()->where('status_permohonan', 'Disokong')->count();
            $data['diluluskanBulanIni'] = Borang::dokumenAktif()->where('pegawai_pelulus_id', $user->user_id)
                ->whereMonth('tarikh_kelulusan', now()->month)->whereYear('tarikh_kelulusan', now()->year)->count();
        }

        if ($user->hasAnyRole('PENYOKONG', 'PELULUS')) {
            $statusDikehendaki = $user->hasRole('PELULUS') ? 'Disokong' : 'Dalam Proses';
            $data['senaraiMenunggu'] = Borang::dokumenAktif()->with(['dokumen.jenisDokumen', 'pemohon'])
                ->where('status_permohonan', $statusDikehendaki)
                ->latest('borang_id')->limit(5)->get();
        }

        if ($user->hasRole('AUDIT')) {
            $data['logHariIni'] = LogAudit::whereDate('created_at', today())->count();
            $data['logMingguIni'] = LogAudit::where('created_at', '>=', now()->subDays(7))->count();
            $data['pecahanTindakan'] = $this->pecahanTindakan();
        }

        if ($user->hasAnyRole('SUPERADMIN', 'ADMIN')) {
            $data['jumlahKategori'] = JenisDokumen::count();
            $data['jumlahKakitangan'] = User::count();
            $data['logHariIni'] = LogAudit::whereDate('created_at', today())->count();
            $data['permohonanBreakdown'] = Borang::dokumenAktif()->selectRaw('status_permohonan, COUNT(*) as jumlah')
                ->groupBy('status_permohonan')->pluck('jumlah', 'status_permohonan');
            $data['purataMasaKelulusan'] = $this->purataMasaKelulusanJam();
            $data['pecahanKategori'] = $this->pecahanKategori(Dokumen::query());
            $data['pecahanBahagian'] = $this->pecahanBahagian();
            $data['arkibTerusAi'] = Borang::dokumenAktif()->where('status_permohonan', 'Diluluskan')
                ->whereNull('pegawai_penyokong_id')->whereNull('pegawai_pelulus_id')->count();
            $data['kelulusanBiasa'] = Borang::dokumenAktif()->where('status_permohonan', 'Diluluskan')
                ->where(fn ($q) => $q->whereNotNull('pegawai_penyokong_id')->orWhereNotNull('pegawai_pelulus_id'))
                ->count();
            $data['purataSkorOcr'] = DokumenOcr::whereNotNull('skor_padanan')->avg('skor_padanan');
            if ($data['purataSkorOcr'] !== null) {
                $data['purataSkorOcr'] = round((float) $data['purataSkorOcr'], 1);
            }
        }

        return $data;
    }

    /**
     * Bilangan dokumen 6 bulan terakhir (termasuk bulan tanpa rekod, supaya
     * carta tidak terputus/mengelirukan).
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

    /** Pecahan bilangan dokumen ikut Jenis Dokumen. */
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

    /** Pecahan bilangan dokumen ikut Bahagian (top 6). */
    private function pecahanBahagian(): array
    {
        $baris = Dokumen::query()
            ->join('bahagian', 'dokumen.bahagian_id', '=', 'bahagian.bahagian_id')
            ->selectRaw('bahagian.nama_bahagian as label, COUNT(*) as jumlah')
            ->groupBy('bahagian.nama_bahagian')
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
        $purata = Borang::dokumenAktif()->where('status_permohonan', 'Diluluskan')
            ->whereNotNull('tarikh_kelulusan')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, tarikh_permohonan, tarikh_kelulusan)) as purata')
            ->value('purata');

        return $purata !== null ? round((float) $purata, 1) : null;
    }
}
