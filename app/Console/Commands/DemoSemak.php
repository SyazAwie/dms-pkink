<?php

namespace App\Console\Commands;

use App\Support\Demo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Senarai semak hari demo (Fasa D8). Setiap baris: OK / AMARAN / RALAT + tindakan.
 * --panaskan memuatkan model AI ke GPU dahulu (ai:panaskan) supaya analisis pertama pantas.
 */
class DemoSemak extends Command
{
    protected $signature = 'demo:semak {--panaskan : Jalankan ai:panaskan sebelum menyemak}';

    protected $description = 'Senarai semak sebelum demo: mod demo, terowong, OCR/AI, kuasa komputer';

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $baris = [];

    public function handle(): int
    {
        if ($this->option('panaskan')) {
            $this->call('ai:panaskan');
        }

        $this->modDemo();
        $this->laman();
        $this->terowong();
        $this->ocr();
        $this->ollama();
        $this->kuasa();

        $this->table(['Status', 'Perkara', 'Butiran / tindakan'], $this->baris);
        $ralat = collect($this->baris)->where(0, 'RALAT')->count();
        $amaran = collect($this->baris)->where(0, 'AMARAN')->count();
        $ralat
            ? $this->error("{$ralat} perkara mesti dibaiki sebelum demo ({$amaran} amaran).")
            : $this->info('Sedia untuk demo'.($amaran ? " ({$amaran} amaran, semak di atas)." : '.'));

        return $ralat ? self::FAILURE : self::SUCCESS;
    }

    private function catat(string $status, string $perkara, string $butiran): void
    {
        $this->baris[] = [$status, $perkara, $butiran];
    }

    private function modDemo(): void
    {
        $db = Demo::namaDb();
        $adaDb = (bool) DB::selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$db]);
        $this->catat($adaDb && File::exists(Demo::folder('asas.sql')) ? 'OK' : 'RALAT', 'DB demo', $adaDb ? "{$db} + keadaan asas" : 'php artisan demo:sedia');
        $this->catat(Demo::penandaWujud() ? 'OK' : 'RALAT', 'Mod demo', Demo::penandaWujud()
            ? 'HIDUP (APP_DEBUG dimatikan, emel ke log, log masuk butang sahaja)' : 'php artisan demo:hidup');
    }

    private function laman(): void
    {
        $url = rtrim((string) config('app.url'), '/');
        try {
            $ok = Http::timeout(10)->get("{$url}/up")->successful();
        } catch (\Throwable) {
            $ok = false;
        }
        $this->catat($ok ? 'OK' : 'RALAT', 'Laman Herd', $ok ? $url : "{$url} tidak menjawab: semak Herd berjalan & APP_URL");
    }

    private function terowong(): void
    {
        $url = Demo::urlTerowong();
        if (! $url) {
            $this->catat('RALAT', 'Terowong HTTPS', 'Jalankan `php artisan demo:terowong` dalam tetingkap berasingan');

            return;
        }
        try {
            $ok = Http::timeout(15)->get("{$url}/up")->successful();
        } catch (\Throwable) {
            $ok = false;
        }
        $this->catat($ok ? 'OK' : 'RALAT', 'Terowong HTTPS', $ok ? "{$url}  (QR: {$url}/demo)" : "{$url} tidak menjawab: mulakan semula demo:terowong");
    }

    private function ocr(): void
    {
        foreach (['Tesseract' => [config('services.ocr.tesseract_bin'), '--version'], 'Poppler' => [config('services.ocr.pdftoppm_bin'), '-v']] as $nama => $arahan) {
            try {
                $hasil = Process::timeout(10)->run($arahan);
                $ok = trim($hasil->output().$hasil->errorOutput()) !== '';
            } catch (\Throwable) {
                $ok = false;
            }
            $this->catat($ok ? 'OK' : 'RALAT', $nama, $ok ? (string) $arahan[0] : "Tidak ditemui ({$arahan[0]})");
        }
    }

    private function ollama(): void
    {
        $url = rtrim((string) config('services.ollama.url'), '/');
        $model = (string) config('services.ollama.model');
        try {
            $dimuat = collect(Http::timeout(3)->get("{$url}/api/ps")->throw()->json('models', []))->pluck('name');
        } catch (\Throwable) {
            $this->catat('RALAT', 'Ollama', "Tidak berjalan ({$url})");

            return;
        }
        $panas = $dimuat->contains(fn ($m) => $m === $model || str_starts_with($m, $model.':'));
        $this->catat($panas ? 'OK' : 'AMARAN', 'Model AI dalam GPU', $panas ? $model : "{$model} belum dimuat: php artisan demo:semak --panaskan");
    }

    /** Windows: komputer riba dicas dan tidak tidur semasa demo. */
    private function kuasa(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $tidur = Process::timeout(10)->run(['powercfg', '/query', 'SCHEME_CURRENT', 'SUB_SLEEP', 'STANDBYIDLE'])->output();
        // Dua nilai terakhir ialah indeks semasa AC kemudian DC (teks label ikut bahasa Windows)
        $ac = preg_match_all('/:\s*0x([0-9a-f]+)\s*$/mi', $tidur, $m) && count($m[1]) >= 2 ? hexdec($m[1][count($m[1]) - 2]) : null;
        $this->catat($ac === 0 ? 'OK' : 'AMARAN', 'Tidur bila dicas', $ac === 0
            ? 'Tidak pernah tidur'
            : 'Komputer tidur selepas '.($ac !== null ? round($ac / 60).' minit' : '?').': Tetapan > Sistem > Kuasa > "Never", atau: powercfg /change standby-timeout-ac 0');

        $bateri = trim(Process::timeout(15)->run(['powershell', '-NoProfile', '-Command', '(Get-CimInstance Win32_Battery).BatteryStatus'])->output());
        if ($bateri !== '') {
            // 2 = disambung ke kuasa AC (lain-lain: menggunakan bateri)
            $this->catat($bateri === '2' ? 'OK' : 'AMARAN', 'Pengecas', $bateri === '2' ? 'Disambung' : 'Menggunakan bateri: sambungkan pengecas');
        }
    }
}
