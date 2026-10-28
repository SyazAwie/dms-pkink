<?php

namespace App\Console\Commands;

use App\Support\Demo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Buka laman Herd kepada Internet melalui Cloudflare Quick Tunnel (Fasa D7): percuma,
 * HTTPS automatik, tiada port router dibuka. Alamat *.trycloudflare.com berubah setiap
 * kali, jadi ia disimpan dalam storage/app/demo/terowong.json untuk halaman /demo (QR)
 * dan penjanaan URL (Demo::selarasUrl). Terowong hidup selagi arahan ini berjalan.
 */
class DemoTerowong extends Command
{
    private const CLOUDFLARED_LALAI = 'C:\\Program Files (x86)\\cloudflared\\cloudflared.exe';

    protected $signature = 'demo:terowong';

    protected $description = 'Jalankan Cloudflare Tunnel (pautan HTTPS awam) ke laman demo';

    public function handle(): int
    {
        if (! Demo::penandaWujud()) {
            $this->warn('Mod demo belum hidup: peserta akan nampak DB sebenar. Jalankan `php artisan demo:hidup` dahulu.');
            if (! $this->confirm('Teruskan juga?', false)) {
                return self::FAILURE;
            }
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'dms-pkink.test';
        $bin = (string) config('demo.cloudflared_bin');
        if ($bin === 'cloudflared' && File::exists(self::CLOUDFLARED_LALAI)) {
            $bin = self::CLOUDFLARED_LALAI; // PATH proses Herd/PHP mungkin belum dikemas kini selepas winget
        }

        File::delete(Demo::folder('terowong.json'));
        // Host ditulis semula kepada laman Herd (nginx memilih laman ikut Host)
        $proses = new Process([$bin, 'tunnel', '--no-autoupdate', '--url', 'http://127.0.0.1:80', '--http-host-header', $host]);
        $proses->setTimeout(null);
        $this->line("Memulakan terowong ke http://{$host} ...");

        try {
            $proses->start();
        } catch (\Throwable $e) {
            $this->error('cloudflared tidak dapat dijalankan: '.$e->getMessage());
            $this->line('Pasang dengan: winget install --id Cloudflare.cloudflared');

            return self::FAILURE;
        }

        $url = null;
        foreach ($proses as $output) {
            if (! $url && preg_match('#https://[a-z0-9-]+\.trycloudflare\.com#i', $output, $m)) {
                $url = $m[0];
                File::ensureDirectoryExists(Demo::folder());
                File::put(Demo::folder('terowong.json'), json_encode(['url' => $url, 'mula' => now()->toIso8601String(), 'pid' => $proses->getPid()]));
                $this->newLine();
                $this->info("Pautan demo: {$url}");
                $this->line("Tayang kod QR: {$url}/demo  (atau http://{$host}/demo di komputer ini)");
                $this->line('Biarkan tetingkap ini terbuka. Tekan Ctrl+C untuk menutup terowong.');
            }
        }

        File::delete(Demo::folder('terowong.json'));
        if (! $url) {
            $this->error('Terowong gagal dimulakan:');
            $this->line(trim($proses->getErrorOutput() ?: $proses->getOutput()));

            return self::FAILURE;
        }
        $this->line('Terowong ditutup.');

        return self::SUCCESS;
    }
}
