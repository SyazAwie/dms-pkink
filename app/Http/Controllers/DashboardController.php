<?php

namespace App\Http\Controllers;

use App\Models\Bahagian;
use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenScan;
use App\Models\DokumenOcr;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\PendaftaranStaf;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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

        // Tugas kelulusan dahulu: ada orang menunggu tindakan pengguna ini
        if ($user->hasRole('PENYOKONG')) {
            $bahagian['penyokong'] = $this->dataGiliran($user, 'Dalam Proses', 'tarikh_permohonan', 'pegawai_penyokong_id', 'tarikh_sokongan');
        }
        if ($user->hasRole('PELULUS')) {
            $bahagian['pelulus'] = $this->dataGiliran($user, 'Disokong', 'tarikh_sokongan', 'pegawai_pelulus_id', 'tarikh_kelulusan');
        }
        if ($user->hasRole('AUDIT')) {
            $bahagian['audit'] = $this->dataAudit();
        }
        if ($user->hasRole('KERANI')) {
            $bahagian['kerani'] = $this->dataKerani($user);
        }
        if ($user->hasRole('PENGURUS') && $user->bahagian_id) {
            $bahagian['pengurus'] = $this->dataPengurus($user);
        }
        if ($user->hasRole('STAFF')) {
            $bahagian['staf'] = $this->dataStaf($user);
        }

        // Pentadbir paling atas: perkara yang memerlukan perhatian seluruh sistem
        if ($user->hasAnyRole('SUPERADMIN', 'ADMIN')) {
            $bahagian = ['pentadbir' => $this->dataPentadbir()] + $bahagian;
        }

        return view('dashboard.index', [
            'bahagian' => $bahagian,
            'tindakanPantas' => $this->tindakanPantas($user),
        ]);
    }

    /** Butang pintasan di bahagian atas, ikut gabungan peranan (maksimum 4). */
    private function tindakanPantas(User $user): array
    {
        if ($user->hasAnyRole('SUPERADMIN', 'ADMIN')) {
            // Tugas pentadbir: kelulusan, kakitangan, kategori; Log Audit (SUPERADMIN) atau Imbas & Arkib (ADMIN)
            return [
                ['label' => 'Kelulusan', 'ikon' => 'bi-check2-square', 'url' => route('kelulusan.index'), 'utama' => true],
                ['label' => 'Kakitangan', 'ikon' => 'bi-people', 'url' => route('users.index')],
                ['label' => 'Kategori Dokumen', 'ikon' => 'bi-folder2-open', 'url' => route('jenis-dokumen.index')],
                $user->hasRole('SUPERADMIN')
                    ? ['label' => 'Log Audit', 'ikon' => 'bi-shield-check', 'url' => route('log-audit.index')]
                    : ['label' => 'Imbas & Arkib', 'ikon' => 'bi-magic', 'url' => route('imbas-arkib.create')],
            ];
        }

        $tindakan = [];
        if ($user->can('create', Dokumen::class)) {
            $tindakan[] = ['label' => 'Isi Borang', 'ikon' => 'bi-ui-checks-grid', 'url' => route('isi-borang.index'), 'utama' => true];
            $tindakan[] = ['label' => 'Imbas & Hantar', 'ikon' => 'bi-send-check', 'url' => route('imbas-hantar.create')];
        }
        if ($user->hasRole('KERANI')) {
            // Tugas utama kerani: mengarkib dokumen fizikal
            $tindakan = array_map(fn ($t) => ['utama' => false] + $t, $tindakan);
            array_unshift($tindakan, ['label' => 'Imbas & Arkib', 'ikon' => 'bi-magic', 'url' => route('imbas-arkib.create'), 'utama' => true]);
        }
        if ($user->hasAnyRole('PENYOKONG', 'PELULUS')) {
            array_unshift($tindakan, ['label' => 'Semak Kelulusan', 'ikon' => 'bi-check2-square', 'url' => route('kelulusan.index'), 'utama' => true]);
        }
        if ($user->hasRole('AUDIT')) {
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

    /**
     * PENGURUS: dokumen & permohonan bahagian sendiri (skop sama seperti
     * Dokumen::bolehDilihat bagi PENGURUS).
     */
    private function dataPengurus(User $user): array
    {
        $skop = Dokumen::where('bahagian_id', $user->bahagian_id);
        $borang = fn () => Borang::dokumenAktif()->whereHas('dokumen', fn ($q) => $q->where('bahagian_id', $user->bahagian_id));

        return [
            'namaBahagian' => $user->bahagian?->nama_bahagian,
            'dokumenBulanIni' => (clone $skop)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'jumlahDokumen' => (clone $skop)->count(),
            'menunggu' => $borang()->whereIn('status_permohonan', ['Dalam Proses', 'Disokong'])->count(),
            'diluluskanBulanIni' => $borang()->where('status_permohonan', 'Diluluskan')
                ->whereMonth('tarikh_kelulusan', now()->month)->whereYear('tarikh_kelulusan', now()->year)->count(),
            'ditolak' => $borang()->where('status_permohonan', 'Ditolak')->count(),
            'permohonanTerkini' => $borang()->with(['dokumen.jenisDokumen', 'pemohon'])->latest('borang_id')->limit(5)->get(),
            'kategoriTeratas' => $this->senaraiKategori($skop),
        ];
    }

    /**
     * KERANI: kerja mengarkib. "Diarkib terus" = borang Diluluskan tanpa Penyokong/Pelulus
     * (dicipta oleh Imbas & Arkib); "belum di-OCR" = fail berstatus Pending (OCR manual).
     */
    private function dataKerani(User $user): array
    {
        $skop = Dokumen::bolehDilihat($user);
        $bulanIni = fn ($q, string $lajur) => $q->whereMonth($lajur, now()->month)->whereYear($lajur, now()->year);

        return [
            'dimuatNaikBulanIni' => $bulanIni(Dokumen::where('created_by', $user->user_id), 'created_at')->count(),
            'diarkibBulanIni' => $bulanIni(Borang::dokumenAktif()->where('pemohon_id', $user->user_id)
                ->where('status_permohonan', 'Diluluskan')->whereNull('pegawai_penyokong_id')->whereNull('pegawai_pelulus_id'), 'tarikh_kelulusan')->count(),
            'belumOcr' => DokumenScan::whereIn('status_ocr', self::STATUS_BELUM_OCR)->whereHas('dokumen', fn ($q) => $q->bolehDilihat($user))->count(),
            'ralatOcr' => DokumenScan::where('status_ocr', 'Ralat')->whereHas('dokumen', fn ($q) => $q->bolehDilihat($user))->count(),
            'jumlahDokumen' => (clone $skop)->count(),
            'dokumenTerkini' => (clone $skop)->with(['jenisDokumen', 'pemuatNaik'])->latest('dokumen_id')->limit(5)->get(),
        ];
    }

    /** Hari menunggu yang dianggap tertunggak (giliran kelulusan & perhatian pentadbir). */
    public const HARI_TERTUNGGAK = 3;

    /** Fail imbasan yang masih perlu tindakan OCR: belum dijalankan atau gagal (#27). */
    public const STATUS_BELUM_OCR = ['Pending', 'Ralat'];

    /**
     * PENYOKONG / PELULUS: giliran permohonan pada peringkat $status, paling lama dahulu
     * (FIFO), dengan umur (hari) sejak masuk giliran ($lajurMasuk). Permohonan sendiri
     * tidak termasuk kerana pemohon tidak boleh bertindak ke atasnya.
     */
    private function dataGiliran(User $user, string $status, string $lajurMasuk, string $lajurPegawai, string $lajurSelesai): array
    {
        $giliran = fn () => Borang::dokumenAktif()->bukanMilik($user->user_id)->where('status_permohonan', $status);
        $tertua = $giliran()->min($lajurMasuk);

        return [
            'menunggu' => $giliran()->count(),
            'tertunggak' => $giliran()->where($lajurMasuk, '<', now()->subDays(self::HARI_TERTUNGGAK))->count(),
            'palingLama' => $tertua ? (int) \Illuminate\Support\Carbon::parse($tertua)->diffInDays(now()) : null,
            'selesaiBulanIni' => Borang::dokumenAktif()->where($lajurPegawai, $user->user_id)
                ->whereMonth($lajurSelesai, now()->month)->whereYear($lajurSelesai, now()->year)->count(),
            'senarai' => $giliran()->with(['dokumen.jenisDokumen', 'pemohon'])->orderBy($lajurMasuk)->limit(5)->get(),
            'lajurMasuk' => $lajurMasuk,
        ];
    }

    /** AUDIT: aktiviti sistem 7 hari, tindakan sensitif (padam) dan log terkini. */
    private function dataAudit(): array
    {
        $minggu = fn () => LogAudit::where('created_at', '>=', now()->subDays(7));
        $pecahan = $this->pecahanTindakan();

        return [
            'logHariIni' => LogAudit::whereDate('created_at', today())->count(),
            'logMingguIni' => $minggu()->count(),
            'padamMingguIni' => $minggu()->where('tindakan', 'like', 'Padam%')->count(),
            'penggunaAktif' => LogAudit::whereDate('created_at', today())->whereNotNull('user_id')->distinct()->count('user_id'),
            // Tiada aktiviti: senarai kosong (keadaan kosong), bukan empat bar bernilai 0
            'pecahanTindakan' => array_sum($pecahan['nilai']) === 0 ? []
                : array_map(fn ($l, $n) => ['label' => $l, 'nilai' => $n], $pecahan['label'], $pecahan['nilai']),
            'logTerkini' => LogAudit::with('pengguna')->latest('created_at')->limit(6)->get(),
        ];
    }

    /**
     * SUPERADMIN / ADMIN: seluruh sistem. Bahagian atas ialah "Perlu Perhatian" (hanya
     * perkara > 0), kemudian 4 KPI, aliran kelulusan, trend; analitik terperinci (OCR/AI,
     * kategori, bahagian) dalam bahagian berlipat.
     */
    private function dataPentadbir(): array
    {
        $tertunggak = Borang::dokumenAktif()->where(fn ($q) => $q
            ->where(fn ($d) => $d->where('status_permohonan', 'Dalam Proses')->where('tarikh_permohonan', '<', now()->subDays(self::HARI_TERTUNGGAK)))
            ->orWhere(fn ($d) => $d->where('status_permohonan', 'Disokong')->where('tarikh_sokongan', '<', now()->subDays(self::HARI_TERTUNGGAK)))
        )->count();
        $pendaftaran = PendaftaranStaf::where('status', PendaftaranStaf::MENUNGGU)->count();
        $belumOcr = DokumenScan::whereIn('status_ocr', self::STATUS_BELUM_OCR)->whereHas('dokumen')->count();

        $status = Borang::dokumenAktif()->selectRaw('status_permohonan, COUNT(*) as jumlah')
            ->groupBy('status_permohonan')->pluck('jumlah', 'status_permohonan');
        $purataOcr = DokumenOcr::whereNotNull('skor_padanan')->avg('skor_padanan');

        return [
            'perhatian' => array_values(array_filter([
                ['bil' => $pendaftaran, 'teks' => 'pendaftaran staf baharu menunggu pengesahan', 'url' => route('pendaftaran.index'), 'butang' => 'Sahkan', 'tahap' => 'merah'],
                ['bil' => $tertunggak, 'teks' => 'permohonan tertunggak lebih ' . self::HARI_TERTUNGGAK . ' hari', 'url' => route('kelulusan.index'), 'butang' => 'Semak', 'tahap' => 'merah'],
                ['bil' => $belumOcr, 'teks' => 'fail imbasan belum di-OCR atau gagal OCR', 'url' => route('dokumen.index'), 'butang' => 'Lihat', 'tahap' => 'kuning'],
            ], fn ($p) => $p['bil'] > 0)),
            'jumlahDokumen' => Dokumen::count(),
            'dokumenBulanIni' => Dokumen::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'statusPermohonan' => collect(['Dalam Proses', 'Disokong', 'Diluluskan', 'Ditolak'])
                ->mapWithKeys(fn ($s) => [$s => (int) ($status[$s] ?? 0)])->all(),
            'purataJamKelulusan' => $this->purataJamKelulusan(),
            'jumlahKakitangan' => User::count(),
            'jumlahBahagian' => Bahagian::count(),
            'trend' => $this->trendEnamBulan(),
            // Analitik terperinci (berlipat)
            'arkibTerusAi' => Borang::dokumenAktif()->where('status_permohonan', 'Diluluskan')
                ->whereNull('pegawai_penyokong_id')->whereNull('pegawai_pelulus_id')->count(),
            'jumlahFail' => DokumenScan::whereHas('dokumen')->count(),
            'purataOcr' => $purataOcr !== null ? round((float) $purataOcr, 1) : null,
            'jumlahKategori' => JenisDokumen::count(),
            'kategoriTeratas' => $this->senaraiKategori(Dokumen::query()),
            'bahagianTeratas' => $this->senaraiBahagian(),
            'dokumenTerkini' => Dokumen::with(['jenisDokumen', 'pemuatNaik', 'borang'])->latest('dokumen_id')->limit(5)->get(),
        ];
    }

    /**
     * Bilangan dokumen 6 bulan terakhir, termasuk bulan tanpa rekod. Dikira dalam PHP
     * (bukan DATE_FORMAT MySQL) supaya mudah alih & boleh diuji dengan SQLite.
     *
     * @return list<array{label: string, nilai: int}>
     */
    private function trendEnamBulan(): array
    {
        $mula = now()->subMonths(5)->startOfMonth();
        $kiraan = Dokumen::where('created_at', '>=', $mula)->pluck('created_at')
            ->countBy(fn ($t) => $t->format('Y-m'));

        return collect(range(5, 0))->map(function ($i) use ($kiraan) {
            $bulan = now()->subMonths($i);

            return ['label' => $bulan->locale('ms')->translatedFormat('M'), 'nilai' => (int) ($kiraan[$bulan->format('Y-m')] ?? 0)];
        })->all();
    }

    /** Purata jam dari dihantar hingga diluluskan (borang Diluluskan melalui aliran sebenar). */
    private function purataJamKelulusan(): ?float
    {
        $jam = Borang::dokumenAktif()->where('status_permohonan', 'Diluluskan')
            ->whereNotNull('tarikh_permohonan')->whereNotNull('tarikh_kelulusan')
            ->whereNotNull('pegawai_pelulus_id')   // kecualikan arkib terus (tiada kelulusan sebenar)
            ->get(['tarikh_permohonan', 'tarikh_kelulusan'])
            ->map(fn ($b) => $b->tarikh_permohonan->diffInMinutes($b->tarikh_kelulusan) / 60);

        return $jam->isEmpty() ? null : round($jam->avg(), 1);
    }

    /** Bahagian teratas (bar HTML); kosong jika kurang daripada 2 bahagian (tiada perbandingan). */
    private function senaraiBahagian(int $had = 5): array
    {
        $baris = Dokumen::query()
            ->join('bahagian', 'dokumen.bahagian_id', '=', 'bahagian.bahagian_id')
            ->selectRaw('bahagian.nama_bahagian as label, COUNT(*) as nilai')
            ->groupBy('bahagian.nama_bahagian')
            ->orderByDesc('nilai')
            ->limit($had)
            ->get()
            ->map(fn ($b) => ['label' => $b->label, 'nilai' => (int) $b->nilai])
            ->all();

        return count($baris) >= 2 ? $baris : [];
    }

    /** Kategori teratas (bar HTML): [['label' => nama, 'nilai' => bilangan], ...] */
    private function senaraiKategori($query, int $had = 5): array
    {
        return (clone $query)
            ->join('jenis_dokumen', 'dokumen.jenis_dokumen_id', '=', 'jenis_dokumen.jenis_dokumen_id')
            ->selectRaw('jenis_dokumen.nama_dokumen as label, COUNT(*) as nilai')
            ->groupBy('jenis_dokumen.nama_dokumen')
            ->orderByDesc('nilai')
            ->limit($had)
            ->get()
            ->map(fn ($b) => ['label' => $b->label, 'nilai' => (int) $b->nilai])
            ->all();
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
}
