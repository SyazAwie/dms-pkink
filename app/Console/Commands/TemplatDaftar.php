<?php

namespace App\Console\Commands;

use App\Models\JenisDokumen;
use App\Models\TemplatBorang;
use App\Services\TemplatBorangService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Daftar fail borang kosong yang sudah berada dalam folder Templat/ sebagai templat kategori
 * (Fasa TB), contohnya fail asal yang disalin terus ke folder itu. Muat naik biasa dibuat
 * melalui halaman Kategori Dokumen.
 *
 *   php artisan templat:daftar 4-b_Borang_Pohon_Peralatan_Komputer.pdf BORANG-ICT
 *   php artisan templat:daftar --senarai        (fail dalam Templat/ dan statusnya)
 */
class TemplatDaftar extends Command
{
    protected $signature = 'templat:daftar
        {fail? : Laluan fail relatif kepada folder Templat/}
        {kod? : Kod kategori (kod_dokumen)}
        {--senarai : Senaraikan fail dalam Templat/ dan templat berdaftar}';

    protected $description = 'Daftar borang kosong dalam folder Templat/ sebagai templat kategori';

    public function handle(TemplatBorangService $servis): int
    {
        $disk = Storage::disk(TemplatBorangService::DISK);

        if ($this->option('senarai') || ! $this->argument('fail')) {
            $berdaftar = TemplatBorang::with('jenisDokumen')->get()->keyBy('lokasi_fail');
            $this->table(['Fail', 'Kategori', 'Versi', 'Status OCR', 'Aktif'], collect($disk->allFiles())
                ->reject(fn ($f) => str_starts_with(basename($f), '.'))
                ->map(fn ($f) => [$f, $berdaftar[$f]?->jenisDokumen?->kod_dokumen ?? '-', $berdaftar[$f]?->versi ?? '-',
                    $berdaftar[$f]?->status_ocr ?? 'belum didaftar', isset($berdaftar[$f]) ? ($berdaftar[$f]->aktif ? 'Ya' : 'Tidak') : '-'])
                ->values()->all());

            return self::SUCCESS;
        }

        $fail = str_replace('\\', '/', (string) $this->argument('fail'));
        if (str_contains($fail, '..') || ! $disk->exists($fail)) {
            $this->error("Fail tidak dijumpai dalam Templat/: {$fail}");

            return self::FAILURE;
        }
        $jenis = JenisDokumen::where('kod_dokumen', (string) $this->argument('kod'))->first();
        if (! $jenis) {
            $this->error('Kategori tidak dijumpai: '.$this->argument('kod'));

            return self::FAILURE;
        }
        if (TemplatBorang::where('lokasi_fail', $fail)->exists()) {
            $this->warn("Fail ini sudah didaftarkan: {$fail}");

            return self::SUCCESS;
        }

        $templat = $servis->daftar($jenis, $fail, basename($fail), null, 'Didaftarkan melalui templat:daftar');
        $this->info("Templat {$jenis->kod_dokumen} versi {$templat->versi} didaftarkan: {$fail} (OCR: {$templat->status_ocr}).");

        return self::SUCCESS;
    }
}
