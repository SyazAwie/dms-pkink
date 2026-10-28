<?php

namespace App\Support;

use App\Models\Borang;
use App\Models\DelegasiKelulusan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Skop & hierarki kelulusan (P5), di SATU tempat supaya senarai Kelulusan, papan pemuka,
 * loceng, butang dalam Butiran, tindakan sokong/tolak dan penerima notifikasi selari.
 *
 * - PENYOKONG hanya bertindak ke atas permohonan bahagiannya (bahagian dokumen). Jika
 *   bahagian itu tiada PENYOKONG langsung (atau dokumen tanpa bahagian), semua PENYOKONG
 *   boleh bertindak supaya permohonan tidak tersangkut.
 * - PELULUS: semua bahagian. SUPERADMIN/ADMIN: semua, tanpa skop.
 * - Delegasi: penerima bertindak bagi pihak pemberi, dengan skop bahagian pemberi.
 */
class SkopKelulusan
{
    /**
     * Bahagian yang PENYOKONG ini bertanggungjawab (null = semua bahagian).
     *
     * @return array<int>|null
     */
    public static function bahagianPenyokong(User $user): ?array
    {
        if ($user->hasAnyRole('SUPERADMIN', 'ADMIN')) {
            return null;
        }

        $bahagian = in_array('PENYOKONG', $user->kodPerananSendiri(), true) && $user->bahagian_id
            ? [(int) $user->bahagian_id] : [];

        foreach ($user->delegasiAktif('PENYOKONG') as $delegasi) {
            if ($delegasi->pemberi?->bahagian_id) {
                $bahagian[] = (int) $delegasi->pemberi->bahagian_id;
            }
        }

        return array_values(array_unique($bahagian));
    }

    /**
     * Bahagian yang mempunyai sekurang-kurangnya seorang PENYOKONG aktif.
     *
     * @return array<int>
     */
    public static function bahagianAdaPenyokong(): array
    {
        return User::whereNotNull('bahagian_id')
            ->whereHas('roles', fn ($q) => $q->where('kod_roles', 'PENYOKONG'))
            ->distinct()->pluck('bahagian_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Hadkan query Borang kepada giliran sokongan pengguna ini. */
    public static function hadkanGiliranSokongan(Builder $query, User $user): Builder
    {
        $bahagian = self::bahagianPenyokong($user);
        if ($bahagian === null) {
            return $query;
        }

        $adaPenyokong = self::bahagianAdaPenyokong();

        return $query->whereHas('dokumen', fn ($d) => $d->where(fn ($q) => $q
            ->whereIn('bahagian_id', $bahagian ?: [0])
            ->orWhereNull('bahagian_id')
            ->orWhereNotIn('bahagian_id', $adaPenyokong ?: [0])));
    }

    /** Bolehkah pengguna ini bertindak (sokong/tolak) di peringkat sokongan bagi borang ini? */
    public static function bolehSokong(User $user, Borang $borang): bool
    {
        if (! $user->hasAnyRole('PENYOKONG', 'SUPERADMIN', 'ADMIN')) {
            return false;
        }

        return self::hadkanGiliranSokongan(Borang::whereKey($borang->borang_id), $user)->exists();
    }

    /**
     * Jika pengguna bertindak dengan peranan $kod melalui delegasi (tidak memegangnya
     * sendiri), pulangkan ID pemberi untuk direkod "bagi pihak" dalam sejarah.
     */
    public static function bagiPihak(User $user, string $kod, Borang $borang): ?int
    {
        if (in_array($kod, $user->kodPerananSendiri(), true) || $user->hasAnyRole('SUPERADMIN', 'ADMIN')) {
            return null;
        }

        $delegasi = $user->delegasiAktif($kod);
        $bahagianDokumen = $borang->dokumen?->bahagian_id;

        return ($delegasi->firstWhere('pemberi.bahagian_id', $bahagianDokumen) ?? $delegasi->first())?->pemberi_id;
    }

    /**
     * Penerima notifikasi "menunggu tindakan" bagi peranan $kod, ikut skop di atas,
     * termasuk penerima delegasi aktif.
     *
     * @return Collection<int, User>
     */
    public static function penerimaGiliran(string $kod, Borang $borang): Collection
    {
        $pegawai = User::whereHas('roles', fn ($q) => $q->where('kod_roles', $kod))->get();
        $penerimaDelegasi = DelegasiKelulusan::aktif()->with(['penerima', 'pemberi'])->where('kod_peranan', $kod)->get();

        if ($kod === 'PENYOKONG') {
            $bahagian = $borang->dokumen()->withTrashed()->value('bahagian_id');
            if ($bahagian && $pegawai->contains('bahagian_id', $bahagian)) {
                $pegawai = $pegawai->where('bahagian_id', $bahagian);
                $penerimaDelegasi = $penerimaDelegasi->filter(fn ($d) => (int) $d->pemberi?->bahagian_id === (int) $bahagian);
            }
        }

        return $pegawai->concat($penerimaDelegasi->pluck('penerima')->filter())->unique('user_id')->values();
    }
}
