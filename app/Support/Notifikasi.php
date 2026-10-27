<?php

namespace App\Support;

use App\Models\Borang;
use App\Models\PendaftaranStaf;
use App\Models\User;
use App\Notifications\NotifikasiDms;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Siapa menerima notifikasi apa (P-C). Kegagalan menghantar notifikasi tidak pernah
 * menggagalkan tindakan asal: ia dilog sahaja.
 *
 * | Peristiwa                        | Penerima                         | peranan            |
 * |----------------------------------|----------------------------------|--------------------|
 * | Permohonan dihantar (Dalam Proses)| PENYOKONG (kecuali pemohon)     | PENYOKONG          |
 * | Disokong                          | Pemohon; PELULUS (kecuali pemohon)| null; PELULUS     |
 * | Diluluskan / Ditolak              | Pemohon                          | null (peribadi)    |
 * | Pendaftaran staf baharu           | SUPERADMIN, ADMIN                | SUPERADMIN, ADMIN  |
 * | Amaran sistem (sandaran/integriti)| SUPERADMIN, ADMIN                | SUPERADMIN, ADMIN  |
 */
class Notifikasi
{
    public const PERANAN_PENTADBIR = ['SUPERADMIN', 'ADMIN'];

    /** Bilangan maksimum notifikasi yang dinilai untuk loceng (ditapis ikut peranan aktif). */
    private const HAD_LOCENG = 100;

    public static function permohonanDihantar(Borang $borang): void
    {
        [$rujukan, $nama, $url] = self::maklumatDokumen($borang);

        self::kepadaPeranan(['PENYOKONG'], new NotifikasiDms('permohonan_baharu', 'Permohonan menunggu sokongan',
            "{$rujukan} ({$nama}) daripada " . ($borang->pemohon?->nama_staff ?? 'pemohon') . '.',
            $url, 'bi-inbox', 'kuning', ['PENYOKONG']), $borang->pemohon_id);
    }

    public static function permohonanDisokong(Borang $borang): void
    {
        [$rujukan, $nama, $url] = self::maklumatDokumen($borang);
        $oleh = $borang->penyokong?->nama_staff;

        self::kepadaPemohon($borang, new NotifikasiDms('disokong', 'Permohonan anda disokong',
            "{$rujukan} ({$nama}) telah disokong" . ($oleh ? " oleh {$oleh}" : '') . ' dan kini menunggu kelulusan.',
            $url, 'bi-hand-thumbs-up', 'biru'));

        self::kepadaPeranan(['PELULUS'], new NotifikasiDms('menunggu_kelulusan', 'Permohonan menunggu kelulusan',
            "{$rujukan} ({$nama}) telah disokong dan menunggu kelulusan anda.",
            $url, 'bi-patch-check', 'kuning', ['PELULUS']), $borang->pemohon_id);
    }

    public static function permohonanDiluluskan(Borang $borang): void
    {
        [$rujukan, $nama, $url] = self::maklumatDokumen($borang);
        $oleh = $borang->pelulus?->nama_staff;

        self::kepadaPemohon($borang, new NotifikasiDms('diluluskan', 'Permohonan anda diluluskan',
            "{$rujukan} ({$nama}) telah diluluskan" . ($oleh ? " oleh {$oleh}" : '') . '.',
            $url, 'bi-check-circle', 'hijau'));
    }

    public static function permohonanDitolak(Borang $borang): void
    {
        [$rujukan, $nama, $url] = self::maklumatDokumen($borang);
        $oleh = $borang->penolak?->nama_staff;
        $ulasan = $borang->ulasan_pelulus ?: $borang->ulasan_penyokong;

        self::kepadaPemohon($borang, new NotifikasiDms('ditolak', 'Permohonan anda ditolak',
            "{$rujukan} ({$nama}) ditolak" . ($oleh ? " oleh {$oleh}" : '') . ($ulasan ? ": \"{$ulasan}\"" : '.') . ' Semak dan hantar semula.',
            $url, 'bi-x-circle', 'merah'));
    }

    public static function pendaftaranBaharu(PendaftaranStaf $pendaftaran): void
    {
        self::kepadaPeranan(self::PERANAN_PENTADBIR, new NotifikasiDms('pendaftaran_baharu', 'Pendaftaran staf baharu',
            "{$pendaftaran->nama_staff} memohon akaun dan menunggu pengesahan.",
            route('pendaftaran.index'), 'bi-person-plus', 'kuning', self::PERANAN_PENTADBIR));
    }

    public static function amaranSistem(string $tajuk, string $mesej): void
    {
        self::kepadaPeranan(self::PERANAN_PENTADBIR, new NotifikasiDms('amaran_sistem', $tajuk, $mesej,
            route('dashboard'), 'bi-exclamation-triangle', 'merah', self::PERANAN_PENTADBIR));
    }

    /**
     * Notifikasi pengguna yang kelihatan dalam peranan aktifnya (terkini dahulu).
     *
     * @return Collection<int, \Illuminate\Notifications\DatabaseNotification>
     */
    public static function kelihatan(User $user, bool $belumDibacaSahaja = false, int $had = self::HAD_LOCENG): Collection
    {
        $peranan = $user->kodPeranan();
        $query = $belumDibacaSahaja ? $user->unreadNotifications() : $user->notifications();

        return $query->latest()->limit($had)->get()
            ->filter(fn ($n) => empty($n->data['peranan']) || array_intersect($n->data['peranan'], $peranan))
            ->values();
    }

    /** @return array{0: string, 1: string, 2: string} no. rujukan, nama kategori, URL dokumen */
    private static function maklumatDokumen(Borang $borang): array
    {
        $dokumen = $borang->dokumen()->withTrashed()->with('jenisDokumen')->first();

        return [
            $dokumen?->no_rujukan ?? 'Dokumen',
            $dokumen?->jenisDokumen?->nama_dokumen ?? 'dokumen',
            route('dokumen.show', $borang->dokumen_id),
        ];
    }

    private static function kepadaPemohon(Borang $borang, NotifikasiDms $notifikasi): void
    {
        self::hantar(fn () => $borang->pemohon ? collect([$borang->pemohon]) : collect(), $notifikasi);
    }

    private static function kepadaPeranan(array $peranan, NotifikasiDms $notifikasi, $kecuali = null): void
    {
        self::hantar(fn () => User::whereHas('roles', fn ($q) => $q->whereIn('kod_roles', $peranan))
            ->when($kecuali, fn ($q) => $q->where('user_id', '!=', $kecuali))
            ->get(), $notifikasi);
    }

    private static function hantar(callable $penerima, NotifikasiDms $notifikasi): void
    {
        try {
            $senarai = $penerima();
            if ($senarai->isNotEmpty()) {
                Notification::send($senarai, $notifikasi);
            }
        } catch (\Throwable $e) {
            Log::warning("Notifikasi \"{$notifikasi->jenis}\" gagal dihantar: " . $e->getMessage());
        }
    }
}
