<?php

namespace App\Observers;

use App\Models\Borang;
use App\Support\Notifikasi;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Notifikasi aliran kelulusan bagi SEMUA laluan yang mengubah status borang (Kelulusan,
 * Simpan & Hantar, Imbas & Hantar, hantar semula). Berjalan selepas transaksi commit,
 * jadi simpanan yang dibatalkan tidak menghantar notifikasi.
 *
 * Arkib terus Imbas & Arkib (dicipta "Diluluskan" tanpa pelulus) tidak menghantar apa-apa.
 */
class BorangNotifikasiObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Borang $borang): void
    {
        $this->statusBaharu($borang);
    }

    public function updated(Borang $borang): void
    {
        if ($borang->wasChanged('status_permohonan')) {
            $this->statusBaharu($borang);
        }
    }

    private function statusBaharu(Borang $borang): void
    {
        match ($borang->status_permohonan) {
            'Dalam Proses' => Notifikasi::permohonanDihantar($borang),
            'Disokong' => Notifikasi::permohonanDisokong($borang),
            'Diluluskan' => $borang->pegawai_pelulus_id ? Notifikasi::permohonanDiluluskan($borang) : null,
            'Ditolak' => Notifikasi::permohonanDitolak($borang),
            default => null,
        };
    }
}
