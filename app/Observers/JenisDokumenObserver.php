<?php

namespace App\Observers;

use App\Models\JenisDokumen;
use App\Models\LogAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class JenisDokumenObserver
{
    public function created(JenisDokumen $dokumen): void
    {
        $this->rekodLog('Cipta', $dokumen, null, $dokumen->toArray());
    }

    public function updated(JenisDokumen $dokumen): void
    {
        // Jangan log jika ini adalah proses Soft Delete
        if ($dokumen->wasChanged('deleted_at')) {
            return;
        }
        // Dapatkan hanya data yang berubah
        $this->rekodLog('Kemaskini', $dokumen, $dokumen->getOriginal(), $dokumen->getChanges());
    }

    public function deleted(JenisDokumen $dokumen): void
    {
        // Ini akan menangkap tindakan Soft Delete
        $this->rekodLog('Padam (Soft Delete)', $dokumen, $dokumen->toArray(), null);

        // Padam sekali (soft delete) semua medan EAV di bawah kategori ini,
        // supaya medan tidak kekal "aktif" bagi kategori yang telah dipadam.
        // Nota: guna mass delete query builder, jadi tidak mencetuskan
        // event/log berasingan bagi setiap medan.
        $dokumen->fields()->delete();
    }

    // Fungsi pengurus untuk simpan ke database
    private function rekodLog($tindakan, $model, $dataLama, $dataBaharu)
    {
        LogAudit::create([
            'user_id' => Auth::id() ?? 1, // ID Staf yang sedang login
            'tindakan' => $tindakan,
            'nama_jadual' => $model->getTable(),
            'rekod_id' => $model->getKey(),
            'data_lama' => $dataLama, // Simpan sebagai JSON
            'data_baharu' => $dataBaharu, // Simpan sebagai JSON
            'alamat_ip' => Request::ip(),
            'peranti_pengguna' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}