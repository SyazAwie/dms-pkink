<?php

namespace App\Console\Commands;

use App\Support\PemulihanSandaran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Salinan luar talian (Fasa Keselamatan S4): salin sandaran terkini ke cakera luaran,
 * sahkan SHA-256, dan rekod tarikhnya (luar_talian.json, dipantau Pusat Keselamatan).
 * Selepas itu CABUT cakera tersebut: ransomware tidak boleh menyulitkan apa yang tidak
 * bersambung. Gilirkan dua cakera (minggu ganjil/genap) jika boleh.
 *
 *   php artisan sandaran:luar-talian E:\DMS_Sandaran
 */
class SandaranLuarTalian extends Command
{
    protected $signature = 'sandaran:luar-talian {destinasi : Folder pada cakera luaran (cth. E:\\DMS_Sandaran)}';

    protected $description = 'Salin sandaran terkini ke cakera luaran dan sahkan SHA-256';

    public function handle(): int
    {
        $destinasi = rtrim((string) $this->argument('destinasi'), '\\/');
        $zip = PemulihanSandaran::terkini();
        if (! $zip) {
            $this->error('Tiada sandaran. Jalankan php artisan sandaran:jalan dahulu.');

            return self::FAILURE;
        }

        $akarDestinasi = realpath(dirname($destinasi.DIRECTORY_SEPARATOR.'x')) ?: $destinasi;
        if (strtoupper(substr($akarDestinasi, 0, 2)) === strtoupper(substr((string) realpath(PemulihanSandaran::folder()), 0, 2))) {
            $this->warn('Amaran: destinasi berada pada cakera yang sama dengan pelayan. Ini BUKAN salinan luar talian; guna cakera luaran.');
        }

        try {
            File::ensureDirectoryExists($destinasi);
            $tuju = $destinasi.DIRECTORY_SEPARATOR.basename($zip);
            File::copy($zip, $tuju);
            $hash = hash_file('sha256', $zip);
            if (! hash_equals($hash, (string) hash_file('sha256', $tuju))) {
                throw new \RuntimeException('SHA-256 salinan tidak sepadan; salinan mungkin rosak.');
            }
        } catch (\Throwable $e) {
            $this->error('Salinan luar talian gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        PemulihanSandaran::tulisStatus('luar_talian.json', ['masa' => now()->toDateTimeString(), 'destinasi' => $destinasi,
            'fail' => basename($zip), 'sha256' => $hash, 'saiz' => File::size($zip)]);
        $this->info("Disalin & disahkan: {$tuju}");
        $this->line("SHA-256: {$hash}");
        $this->warn('Sekarang CABUT cakera luaran ini dan simpan di tempat selamat.');

        return self::SUCCESS;
    }
}
