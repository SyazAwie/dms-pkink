<?php

namespace App\Console\Commands;

use App\Models\DokumenScan;
use App\Support\Notifikasi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Semak integriti fail imbasan arkib (dokumen_scan):
 * - fail yang tiada metadata (rekod sebelum S7) diisi saiz, MIME & SHA-256
 * - fail yang hilang daripada storan, atau yang SHA-256 tidak lagi sepadan, dilaporkan
 *
 * Keputusan ditulis ke {SANDARAN_FOLDER}/integriti.json dan log Laravel. Dijadualkan
 * mingguan dalam routes/console.php. Kod keluar gagal jika ada fail hilang/berubah.
 */
class FailIntegriti extends Command
{
    protected $signature = 'fail:integriti';

    protected $description = 'Semak integriti fail imbasan (saiz, MIME, SHA-256) dan isi metadata yang belum ada';

    public function handle(): int
    {
        $bil = ['disemak' => 0, 'diisi' => 0, 'hilang' => 0, 'berubah' => 0];
        $masalah = [];

        DokumenScan::with('dokumen:dokumen_id,no_rujukan')->orderBy('dokumen_scan_id')
            ->chunkById(200, function ($senarai) use (&$bil, &$masalah) {
                foreach ($senarai as $scan) {
                    $bil['disemak']++;
                    $meta = $scan->metadataFail();
                    $rujukan = $scan->dokumen?->no_rujukan ?? "dokumen_id {$scan->dokumen_id}";

                    if ($meta === null) {
                        $bil['hilang']++;
                        $masalah[] = ['status' => 'Hilang', 'id' => $scan->dokumen_scan_id, 'dokumen' => $rujukan, 'fail' => $scan->nama_fail];
                    } elseif ($scan->hash_sha256 === null) {
                        $scan->update($meta);
                        $bil['diisi']++;
                    } elseif (! hash_equals($scan->hash_sha256, $meta['hash_sha256'])) {
                        $bil['berubah']++;
                        $masalah[] = ['status' => 'Berubah', 'id' => $scan->dokumen_scan_id, 'dokumen' => $rujukan, 'fail' => $scan->nama_fail];
                    }
                }
            }, 'dokumen_scan_id');

        $this->tulisStatus($bil, $masalah);

        $this->info("{$bil['disemak']} fail disemak, {$bil['diisi']} metadata baharu diisi.");
        if ($masalah) {
            $this->table(['Status', 'ID', 'Dokumen', 'Fail'], array_map(fn ($m) => array_values($m), $masalah));
            $this->error("{$bil['hilang']} fail hilang, {$bil['berubah']} fail berubah. Pulihkan daripada sandaran (README 10.2).");
            Log::warning('Integriti fail imbasan: ' . json_encode($bil), ['masalah' => $masalah]);
            Notifikasi::amaranSistem('Fail imbasan bermasalah',
                "{$bil['hilang']} fail hilang dan {$bil['berubah']} fail berubah dalam semakan integriti. Pulihkan daripada sandaran.");

            return self::FAILURE;
        }

        $this->info('Semua fail imbasan utuh.');
        Log::info('Integriti fail imbasan: ' . json_encode($bil));

        return self::SUCCESS;
    }

    private function tulisStatus(array $bil, array $masalah): void
    {
        try {
            $folder = rtrim((string) config('services.sandaran.folder'), '\\/');
            File::ensureDirectoryExists($folder);
            File::put("{$folder}/integriti.json", json_encode(
                ['masa' => now()->toDateTimeString(), 'utuh' => ! $masalah] + $bil + ['masalah' => $masalah],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ));
        } catch (\Throwable) {
            // Status hanya maklumat tambahan
        }
    }
}
