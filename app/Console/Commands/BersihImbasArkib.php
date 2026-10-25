<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Bersihkan fail sementara Imbas & Arkib dalam storage/app/private/ocr_sementara/
 * yang ditinggalkan (pengguna tidak menekan Simpan, sesi tamat, atau proses terhenti).
 *
 * - Folder token {40 aksara}/ (fail imbasan + meta.json / meta.json.diproses):
 *   umur dikira daripada fail paling baharu dalam folder.
 * - Fail format lama {token20}_{nama asal} (sebelum aliran token).
 *
 * Fail lain dalam folder itu tidak disentuh. Guna --cuba untuk lihat senarai dahulu.
 */
class BersihImbasArkib extends Command
{
    private const FOLDER = 'ocr_sementara';

    protected $signature = 'imbas-arkib:bersih
                            {--jam=24 : Padam yang lebih lama daripada bilangan jam ini}
                            {--cuba : Senaraikan sahaja, jangan padam apa-apa}';

    protected $description = 'Padam fail sementara Imbas & Arkib yang tidak disimpan (ocr_sementara)';

    public function handle(): int
    {
        $jam = (int) $this->option('jam');
        if ($jam < 1) {
            $this->error('Nilai --jam mesti sekurang-kurangnya 1.');
            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $had = now()->subHours($jam)->getTimestamp();
        $calon = [];

        foreach ($disk->directories(self::FOLDER) as $folder) {
            if (!preg_match('/^[A-Za-z0-9]{40}$/', basename($folder))) {
                continue;
            }
            $fail = $disk->allFiles($folder);
            $masa = $fail ? max(array_map(fn ($f) => $disk->lastModified($f), $fail)) : 0;
            $saiz = array_sum(array_map(fn ($f) => $disk->size($f), $fail));
            if ($masa < $had) {
                $calon[] = ['jenis' => 'folder', 'laluan' => $folder, 'masa' => $masa, 'saiz' => $saiz];
            }
        }

        foreach ($disk->files(self::FOLDER) as $fail) {
            if (!preg_match('/^[A-Za-z0-9]{20}_.+/', basename($fail))) {
                continue;
            }
            $masa = $disk->lastModified($fail);
            if ($masa < $had) {
                $calon[] = ['jenis' => 'fail lama', 'laluan' => $fail, 'masa' => $masa, 'saiz' => $disk->size($fail)];
            }
        }

        if (!$calon) {
            $this->info("Tiada fail sementara Imbas & Arkib yang lebih lama daripada {$jam} jam.");
            return self::SUCCESS;
        }

        $this->table(['Jenis', 'Laluan', 'Diubah', 'Saiz (KB)'], array_map(fn ($c) => [
            $c['jenis'],
            $c['laluan'],
            $c['masa'] ? date('Y-m-d H:i', $c['masa']) : '-',
            number_format($c['saiz'] / 1024, 1),
        ], $calon));

        $jumlahKb = number_format(array_sum(array_column($calon, 'saiz')) / 1024, 1);

        if ($this->option('cuba')) {
            $this->warn('Mod --cuba: ' . count($calon) . " item ({$jumlahKb} KB) AKAN dipadam. Tiada apa-apa dipadam.");
            return self::SUCCESS;
        }

        foreach ($calon as $c) {
            $c['jenis'] === 'folder' ? $disk->deleteDirectory($c['laluan']) : $disk->delete($c['laluan']);
        }

        $this->info(count($calon) . " item ({$jumlahKb} KB) dipadam.");
        return self::SUCCESS;
    }
}
