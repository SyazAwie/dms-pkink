<?php

namespace App\Observers;

use App\Models\User;
use App\Models\LogAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class UserObserver
{
    /**
     * Lajur yang tidak boleh sekali-kali masuk ke dalam log audit,
     * sama ada kerana sensitif (password, remember_token) atau
     * tidak bermakna untuk audit (updated_at berubah setiap simpan).
     */
    private array $lajurDikecualikan = ['password', 'remember_token', 'updated_at'];

    public function created(User $user): void
    {
        $data = $user->toArray();
        $this->buangLajurSensitif($data);
        $this->rekodLog('Cipta', $user, null, $data);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('deleted_at')) return;

        $dataLama = $user->getOriginal();
        $dataBaharu = $user->getChanges();

        $this->buangLajurSensitif($dataLama);
        $this->buangLajurSensitif($dataBaharu);

        // Jika selepas dibuang lajur sensitif tiada apa-apa perubahan
        // bermakna yang tinggal (cth. hanya remember_token berubah semasa
        // log masuk "ingat saya"), LANGKAU — elak log bising & kebocoran token.
        if (empty($dataBaharu)) {
            return;
        }

        $this->rekodLog('Kemaskini', $user, $dataLama, $dataBaharu);
    }

    public function deleted(User $user): void
    {
        $data = $user->toArray();
        $this->buangLajurSensitif($data);
        $this->rekodLog('Padam (Soft Delete)', $user, $data, null);
    }

    private function buangLajurSensitif(array &$data): void
    {
        foreach ($this->lajurDikecualikan as $lajur) {
            unset($data[$lajur]);
        }
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