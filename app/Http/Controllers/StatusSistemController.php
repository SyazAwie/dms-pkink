<?php

namespace App\Http\Controllers;

use App\Models\DokumenScan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Status Sistem (P1): semakan langsung komponen yang sistem bergantung padanya, untuk
 * pentadbir dan sebelum demo. Setiap semakan: ok / amaran / ralat + nilai + nota tindakan.
 */
class StatusSistemController extends Controller
{
    public function index()
    {
        $kumpulan = [
            'Pangkalan Data & Aplikasi' => [$this->pangkalanData(), ...$this->konfigurasi()],
            'OCR & AI' => [$this->tesseract(), $this->poppler(), ...$this->ollama()],
            'Notifikasi' => [$this->notifikasiPeranti(), $this->notifikasiEmel()],
            'Penyelenggaraan' => [$this->penjadual(), $this->sandaran(), $this->integriti(), $this->storan()],
        ];

        $semua = collect($kumpulan)->flatten(1);
        $ringkasan = ['ok' => $semua->where('status', 'ok')->count(), 'amaran' => $semua->where('status', 'amaran')->count(),
            'ralat' => $semua->where('status', 'ralat')->count()];

        return view('status_sistem.index', compact('kumpulan', 'ringkasan'));
    }

    private function semakan(string $nama, string $status, string $nilai, ?string $nota = null): array
    {
        return compact('nama', 'status', 'nilai', 'nota');
    }

    private function pangkalanData(): array
    {
        try {
            $versi = DB::connection()->getDriverName() === 'sqlite' ? 'SQLite '.DB::selectOne('select sqlite_version() as v')->v
                : DB::selectOne('select version() as v')->v;

            return $this->semakan('Pangkalan data', 'ok', DB::connection()->getDatabaseName()." ({$versi})");
        } catch (\Throwable $e) {
            return $this->semakan('Pangkalan data', 'ralat', 'Tidak dapat disambung', 'Pastikan MySQL (XAMPP) berjalan.');
        }
    }

    private function konfigurasi(): array
    {
        return [
            config('app.debug')
                ? $this->semakan('Mod debug', 'amaran', 'APP_DEBUG=true', 'Tetapkan APP_DEBUG=false sebelum demo awam / produksi.')
                : $this->semakan('Mod debug', 'ok', 'APP_DEBUG=false'),
            $this->semakan('Persekitaran', 'ok', config('app.env').' · '.config('app.locale').' · '.config('app.timezone')),
        ];
    }

    private function jalan(array $arahan): ?string
    {
        try {
            $hasil = Process::timeout(10)->run($arahan);

            return $hasil->successful() || trim($hasil->errorOutput()) !== '' ? trim($hasil->output()."\n".$hasil->errorOutput()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function tesseract(): array
    {
        $bin = config('services.ocr.tesseract_bin');
        $versi = $this->jalan([$bin, '--version']);
        if (! $versi || ! preg_match('/tesseract\s+v?([\d.]+)/i', $versi, $m)) {
            return $this->semakan('Tesseract OCR', 'ralat', 'Tidak ditemui', "Semak OCR_TESSERACT_BIN ({$bin}).");
        }
        // Output Windows berakhir dengan CRLF: pisah ikut \R dan trim setiap baris
        $bahasa = array_map('trim', preg_split('/\R/', (string) $this->jalan([$bin, '--list-langs'])));
        $tiada = array_diff(explode('+', (string) config('services.ocr.bahasa')), $bahasa);

        return $tiada
            ? $this->semakan('Tesseract OCR', 'amaran', "v{$m[1]}", 'Pek bahasa tiada: '.implode(', ', $tiada))
            : $this->semakan('Tesseract OCR', 'ok', "v{$m[1]} · ".config('services.ocr.bahasa'));
    }

    private function poppler(): array
    {
        $bin = config('services.ocr.pdftoppm_bin');
        $versi = $this->jalan([$bin, '-v']);

        return $versi && preg_match('/version\s+([\d.]+)/i', $versi, $m)
            ? $this->semakan('Poppler (pdftoppm)', 'ok', "v{$m[1]}")
            : $this->semakan('Poppler (pdftoppm)', 'ralat', 'Tidak ditemui', "Semak OCR_PDFTOPPM_BIN ({$bin}).");
    }

    private function ollama(): array
    {
        $url = rtrim((string) config('services.ollama.url'), '/');
        try {
            $model = collect(Http::timeout(3)->get("{$url}/api/tags")->throw()->json('models', []))->pluck('name');
            $dimuat = collect(Http::timeout(3)->get("{$url}/api/ps")->json('models', []))->pluck('name');
        } catch (\Throwable) {
            return [$this->semakan('Ollama', 'ralat', 'Tidak berjalan', "Jalankan Ollama ({$url}).")];
        }

        $ada = fn (string $nama) => $model->contains(fn ($m) => $m === $nama || str_starts_with($m, $nama.':'));
        $semak = function (string $label, ?string $nama, bool $wajib) use ($ada, $dimuat) {
            if (! $nama) {
                return $this->semakan($label, 'amaran', 'Tidak ditetapkan');
            }
            if (! $ada($nama)) {
                return $this->semakan($label, $wajib ? 'ralat' : 'amaran', "{$nama} tiada", "ollama pull {$nama}");
            }
            $panas = $dimuat->contains(fn ($m) => $m === $nama || str_starts_with($m, $nama.':'));

            return $this->semakan($label, 'ok', $nama.($panas ? ' · dimuat dalam memori' : ''), $panas ? null : 'Belum dimuat: php artisan ai:panaskan sebelum demo.');
        };

        return [
            $this->semakan('Ollama', 'ok', "{$url} · {$model->count()} model"),
            $semak('Model AI (SEA-LION)', config('services.ollama.model'), true),
            config('services.ocr.vlm_aktif')
                ? $semak('Model tulisan tangan (GLM-OCR)', config('services.ocr.vlm_model'), false)
                : $this->semakan('Model tulisan tangan (GLM-OCR)', 'amaran', 'Dimatikan (OCR_VLM_AKTIF)'),
        ];
    }

    private function notifikasiPeranti(): array
    {
        if (! config('webpush.vapid.public_key')) {
            return $this->semakan('Notifikasi peranti (Web Push)', 'amaran', 'Kunci VAPID tiada', 'php artisan webpush:vapid');
        }
        // Penyulitan setiap mesej memerlukan kunci EC; di Windows perlukan OPENSSL_CONF
        $ec = @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (! $ec) {
            return $this->semakan('Notifikasi peranti (Web Push)', 'ralat', 'OpenSSL gagal mencipta kunci EC',
                'Tetapkan OPENSSL_CONF (README 10) dan mulakan semula pelayan.');
        }
        try {
            $bil = DB::table(config('webpush.table_name', 'push_subscriptions'))->count();
        } catch (\Throwable) {
            return $this->semakan('Notifikasi peranti (Web Push)', 'ralat', 'Jadual push_subscriptions tiada');
        }

        return $this->semakan('Notifikasi peranti (Web Push)', 'ok', "{$bil} peranti melanggan");
    }

    private function notifikasiEmel(): array
    {
        return config('services.notifikasi.emel')
            ? $this->semakan('Notifikasi emel', 'ok', 'Hidup · '.config('mail.default'))
            : $this->semakan('Notifikasi emel', 'amaran', 'Mati (NOTIFIKASI_EMEL=false)', 'Hidupkan semasa serahan bila akaun sebenar digunakan.');
    }

    private function penjadual(): array
    {
        $degupan = Cache::get('penjadual:degupan');
        if (! $degupan) {
            return $this->semakan('Penjadual (Task Scheduler)', 'ralat', 'Tiada degupan', 'Cipta tugas Task Scheduler (README 10, langkah 12).');
        }
        $minit = (int) Carbon::parse($degupan)->diffInMinutes(now());

        return $minit <= 3
            ? $this->semakan('Penjadual (Task Scheduler)', 'ok', "Degupan terakhir {$minit} minit lalu")
            : $this->semakan('Penjadual (Task Scheduler)', 'ralat', 'Degupan terakhir '.Carbon::parse($degupan)->diffForHumans(), 'Semak tugas "DMS PKINK Penjadual".');
    }

    private function statusJson(string $fail): ?array
    {
        $laluan = rtrim((string) config('services.sandaran.folder'), '\\/')."/{$fail}";

        return File::exists($laluan) ? json_decode(File::get($laluan), true) : null;
    }

    private function sandaran(): array
    {
        $s = $this->statusJson('terakhir.json');
        if (! $s) {
            return $this->semakan('Sandaran', 'ralat', 'Belum pernah dibuat', 'php artisan sandaran:jalan');
        }
        $bila = Carbon::parse($s['masa']);
        if (! ($s['berjaya'] ?? false)) {
            return $this->semakan('Sandaran', 'ralat', 'Gagal '.$bila->diffForHumans(), $s['ralat'] ?? null);
        }
        $nilai = $bila->diffForHumans().' · '.number_format(($s['saiz'] ?? 0) / 1048576, 1).' MB'.(! empty($s['salinan']) ? ' · salinan OneDrive' : '');

        return $bila->lt(now()->subHours(36))
            ? $this->semakan('Sandaran', 'amaran', $nilai, 'Lebih 36 jam: laptop mungkin dimatikan pada 12:30. Jalankan php artisan sandaran:jalan.')
            : $this->semakan('Sandaran', 'ok', $nilai);
    }

    private function integriti(): array
    {
        $s = $this->statusJson('integriti.json');
        if (! $s) {
            return $this->semakan('Integriti fail imbasan', 'amaran', 'Belum pernah disemak', 'php artisan fail:integriti');
        }
        $bila = Carbon::parse($s['masa'])->diffForHumans();

        return ($s['utuh'] ?? false)
            ? $this->semakan('Integriti fail imbasan', 'ok', "{$s['disemak']} fail utuh · {$bila}")
            : $this->semakan('Integriti fail imbasan', 'ralat', "{$s['hilang']} hilang, {$s['berubah']} berubah · {$bila}", 'Pulihkan daripada sandaran (README 10.2).');
    }

    private function storan(): array
    {
        $bebas = @disk_free_space(storage_path());
        $jumlah = @disk_total_space(storage_path());
        $arkib = (int) DokumenScan::sum('saiz_fail');
        $nilai = number_format($bebas / 1073741824, 1).' GB bebas daripada '.number_format($jumlah / 1073741824, 0).' GB · arkib '
            .number_format($arkib / 1048576, 1).' MB ('.DokumenScan::count().' fail)';

        return $bebas !== false && $bebas < 5 * 1073741824
            ? $this->semakan('Ruang cakera', 'amaran', $nilai, 'Kurang 5 GB ruang bebas.')
            : $this->semakan('Ruang cakera', 'ok', $nilai);
    }
}
