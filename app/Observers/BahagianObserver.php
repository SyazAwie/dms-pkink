<?php

namespace App\Observers;

use App\Models\Bahagian;
use App\Models\LogAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class BahagianObserver
{
    public function created(Bahagian $bahagian): void
    {
        $this->rekodLog('Cipta', $bahagian, null, $bahagian->toArray());
    }

    public function updated(Bahagian $bahagian): void
    {
        if ($bahagian->wasChanged('deleted_at')) return; 
        $this->rekodLog('Kemaskini', $bahagian, $bahagian->getOriginal(), $bahagian->getChanges());
    }

    public function deleted(Bahagian $bahagian): void
    {
        $this->rekodLog('Padam (Soft Delete)', $bahagian, $bahagian->toArray(), null);
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