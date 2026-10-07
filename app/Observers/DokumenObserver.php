<?php

namespace App\Observers;

use App\Models\Dokumen;
use App\Models\LogAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class DokumenObserver
{
    public function created(Dokumen $dokumen): void
    {
        $this->rekodLog('Cipta', $dokumen, null, $dokumen->toArray());
    }

    public function updated(Dokumen $dokumen): void
    {
        // Soft delete / pulih dilog oleh deleted()
        if ($dokumen->wasChanged('deleted_at')) {
            return;
        }

        $dataBaharu = $dokumen->getChanges();
        unset($dataBaharu['updated_at']);

        // Langkau jika tiada perubahan bermakna (cth. hanya updated_at)
        if (empty($dataBaharu)) {
            return;
        }

        // Ambil nilai lama bagi lajur yang berubah sahaja
        $dataLama = array_intersect_key($dokumen->getOriginal(), $dataBaharu);

        $this->rekodLog('Kemaskini', $dokumen, $dataLama, $dataBaharu);
    }

    public function deleted(Dokumen $dokumen): void
    {
        $this->rekodLog('Padam (Soft Delete)', $dokumen, $dokumen->toArray(), null);
    }

    private function rekodLog($tindakan, $model, $dataLama, $dataBaharu)
    {
        LogAudit::create([
            'user_id' => Auth::id() ?? 1,
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