<?php

namespace App\Observers;

use App\Models\Borang;
use App\Models\BorangSejarah;
use Illuminate\Support\Facades\Auth;

/**
 * Sejarah aliran kelulusan (P4) bagi SEMUA laluan yang mencipta atau mengubah status
 * borang (Kelulusan, Simpan & Hantar, Imbas & Arkib/Hantar, hantar semula). Berjalan
 * segerak dalam transaksi yang sama, jadi simpanan yang dibatalkan tidak meninggalkan
 * sejarah. Pegawai "bagi pihak" (P5) dihantar melalui Borang::$bagiPihakId.
 */
class BorangSejarahObserver
{
    public function created(Borang $borang): void
    {
        $arkibTerus = $borang->status_permohonan === 'Diluluskan' && ! $borang->pegawai_pelulus_id;

        $this->rekod($borang, $arkibTerus ? 'Arkib Terus' : 'Hantar', $arkibTerus ? $borang->ulasan_pelulus : null);
    }

    public function updated(Borang $borang): void
    {
        if (! $borang->wasChanged('status_permohonan')) {
            return;
        }

        [$tindakan, $ulasan] = match ($borang->status_permohonan) {
            'Dalam Proses' => ['Hantar Semula', null],
            'Disokong' => ['Sokong', $borang->ulasan_penyokong],
            'Diluluskan' => ['Lulus', $borang->ulasan_pelulus],
            'Ditolak' => ['Tolak', $borang->wasChanged('ulasan_pelulus') ? $borang->ulasan_pelulus : $borang->ulasan_penyokong],
            default => [$borang->status_permohonan, null],
        };

        $this->rekod($borang, $tindakan, $ulasan);
    }

    private function rekod(Borang $borang, string $tindakan, ?string $ulasan): void
    {
        BorangSejarah::create([
            'borang_id' => $borang->borang_id,
            'tindakan' => $tindakan,
            'status_selepas' => $borang->status_permohonan,
            'user_id' => Auth::id() ?? $borang->pemohon_id,
            'bagi_pihak_id' => $borang->bagiPihakId,
            'ulasan' => $ulasan,
        ]);
        $borang->bagiPihakId = null;
    }
}
