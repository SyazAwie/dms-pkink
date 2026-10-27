<?php

namespace App\Observers;

use App\Models\DokumenField;
use App\Models\LogAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Log audit bagi medan borang (definisi EAV) yang ditambah, diubah atau dipadam melalui
 * pembina medan Kategori Dokumen. Satu baris log bagi setiap medan; kemaskini hanya
 * merekod lajur yang berubah.
 *
 * Nota: medan yang dipadam bersama kategorinya (JenisDokumenObserver::deleted, padam pukal)
 * tidak dilog satu per satu; log padam kategori sudah merekod tindakan itu.
 */
class DokumenFieldObserver
{
    public function created(DokumenField $medan): void
    {
        $this->rekodLog('Cipta', $medan, null, $medan->toArray());
    }

    public function updated(DokumenField $medan): void
    {
        // Soft delete dilog oleh deleted()
        if ($medan->wasChanged('deleted_at')) {
            return;
        }

        $berubah = array_diff(array_keys($medan->getChanges()), ['updated_at']);
        if (empty($berubah)) {
            return;
        }

        // Nilai lama & baharu dalam bentuk yang sama (pilihan/tetapan sebagai array, bukan JSON mentah)
        $dataLama = [];
        $dataBaharu = [];
        foreach ($berubah as $lajur) {
            $dataLama[$lajur] = $medan->getOriginal($lajur);
            $dataBaharu[$lajur] = $medan->getAttribute($lajur);
        }

        $this->rekodLog('Kemaskini', $medan, $dataLama, $dataBaharu);
    }

    public function deleted(DokumenField $medan): void
    {
        $this->rekodLog('Padam (Soft Delete)', $medan, $medan->toArray(), null);
    }

    private function rekodLog($tindakan, $model, $dataLama, $dataBaharu)
    {
        LogAudit::create([
            'user_id' => Auth::id(), // null = sistem (arahan konsol/penjadual) atau tetamu
            'tindakan' => $tindakan,
            'nama_jadual' => $model->getTable(),
            'rekod_id' => $model->getKey(),
            'data_lama' => $dataLama,
            'data_baharu' => $dataBaharu,
            'alamat_ip' => Request::ip(),
            'peranti_pengguna' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}
