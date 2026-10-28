<?php

namespace App\Rules;

use App\Models\LogAudit;
use App\Services\PemeriksaFail;
use App\Support\Notifikasi;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Peraturan validasi fail imbasan (Fasa Keselamatan S1): had "bom", PDF aktif dan
 * antivirus melalui PemeriksaFail. Fail berbahaya dilog dalam log audit dan pentadbir
 * dimaklumkan. KESELAMATAN_PERIKSA_FAIL=false mematikannya (ujian automatik sahaja).
 */
class FailSelamat implements ValidationRule
{
    private const SAMBUNGAN = ['pdf', 'jpg', 'jpeg', 'png'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Jenis lain sudah ditolak oleh peraturan mimes; jangan beri mesej berganda
        if (! $value instanceof UploadedFile || ! $value->isValid() || ! config('services.keselamatan.periksa_fail', true)
            || ! in_array($value->extension(), self::SAMBUNGAN, true)) {
            return;
        }

        $hasil = app(PemeriksaFail::class)->periksa($value->getRealPath(), $value->extension());
        $nama = $value->getClientOriginalName();

        if ($hasil['amaran']) {
            Log::warning("Fail \"{$nama}\" diterima tanpa imbasan antivirus: {$hasil['amaran']}");
        }
        if ($hasil['status'] === PemeriksaFail::BERSIH) {
            return;
        }

        if ($hasil['status'] === PemeriksaFail::BERBAHAYA) {
            Log::warning("Fail berbahaya ditolak: \"{$nama}\" ({$hasil['mesej']})", ['user_id' => Auth::id(), 'ip' => request()->ip()]);
            LogAudit::create([
                'user_id' => Auth::id(), 'tindakan' => 'Fail Berbahaya Ditolak', 'nama_jadual' => 'dokumen_scan', 'rekod_id' => null,
                'data_lama' => null, 'data_baharu' => ['nama_fail' => $nama, 'saiz' => $value->getSize(), 'sebab' => $hasil['mesej']],
                'alamat_ip' => request()->ip(), 'peranti_pengguna' => request()->userAgent(), 'created_at' => now(),
            ]);
            Notifikasi::amaranSistem('Fail berbahaya ditolak',
                ($value->getClientOriginalName()).' daripada '.(Auth::user()?->nama_staff ?? 'tetamu').' ditolak: '.$hasil['mesej']);
        }

        $fail($hasil['mesej']);
    }
}
