<?php

namespace App\Providers;

use App\Models\Bahagian;
use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use App\Observers\BahagianObserver;
use App\Observers\BorangNotifikasiObserver;
use App\Observers\BorangSejarahObserver;
use App\Observers\DokumenFieldObserver;
use App\Observers\DokumenObserver;
use App\Observers\JenisDokumenObserver;
use App\Observers\UserObserver;
use App\Policies\DokumenPolicy;
use App\Support\AntrianOcr;
use App\Support\Demo;
use App\Support\TeksEmel;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Queue OCR (T2): jadual `jobs` sentiasa dalam DB sebenar. Sambungan `antrian` disalin
        // SEBELUM mod demo menukar DB sambungan lalai, supaya permintaan web mod demo dan worker
        // konsol berkongsi satu giliran (setiap kerja membawa modnya, lihat Demo::dalamMod).
        $lalai = config('database.default');
        config([
            'database.connections.antrian' => config("database.connections.{$lalai}"),
            'queue.connections.database.connection' => 'antrian',
            'queue.failed.database' => 'antrian',
        ]);

        // Mod Demo (Fasa D): permintaan web sahaja. Konsol & penjadual (sandaran harian,
        // semakan keselamatan) kekal pada DB sebenar walaupun demo sedang berjalan.
        if (! $this->app->runningInConsole() && ($mod = Demo::modPenanda())) {
            Demo::terapkan($mod);
        }
    }

    public function boot(): void
    {
        // `php artisan serve` hanya menurunkan pembolehubah persekitaran dalam senarai putih
        // kepada proses `php -S`. Tanpa OPENSSL_CONF, Web Push gagal di Windows (kunci EC)
        // untuk notifikasi yang dicetuskan dari pelayar.
        if ($this->app->runningInConsole()) {
            ServeCommand::$passthroughVariables[] = 'OPENSSL_CONF';

            // Degupan worker queue OCR (T2): halaman tahu worker berjalan, jika tidak OCR segerak.
            // Pendengar TIDAK boleh memulangkan false: Looping === false menghentikan worker.
            $queueOcr = fn (?string $queue) => in_array(AntrianOcr::NAMA, explode(',', (string) $queue), true);
            Event::listen(Looping::class, function (Looping $e) use ($queueOcr): void {
                if ($queueOcr($e->queue)) {
                    AntrianOcr::degup(false);
                }
            });
            Event::listen(JobProcessing::class, function (JobProcessing $e) use ($queueOcr): void {
                if ($queueOcr($e->job->getQueue())) {
                    AntrianOcr::degup(true);
                }
            });
            Event::listen([JobProcessed::class, JobFailed::class], function ($e) use ($queueOcr): void {
                if ($queueOcr($e->job->getQueue())) {
                    AntrianOcr::degup(false);
                }
            });
        }

        // Daftarkan 'CCTV' secara paksa supaya sistem tidak terlepas pandang
        JenisDokumen::observe(JenisDokumenObserver::class);
        Bahagian::observe(BahagianObserver::class);
        User::observe(UserObserver::class);
        Dokumen::observe(DokumenObserver::class);
        DokumenField::observe(DokumenFieldObserver::class);

        // Notifikasi aliran kelulusan (selepas commit), bukan log audit
        Borang::observe([BorangSejarahObserver::class, BorangNotifikasiObserver::class]);

        // Kebenaran dokumen ikut peranan (lihat, muat naik, edit, padam)
        Gate::policy(Dokumen::class, DokumenPolicy::class);

        // Paginasi guna templat Bootstrap 5 (lalai Laravel ialah Tailwind, yang tidak
        // dimuatkan di sini: anak panah SVG dipaparkan bersaiz gergasi)
        Paginator::useBootstrapFive();

        // Kandungan emel tetapan semula kata laluan dalam Bahasa Melayu
        // (menggantikan templat lalai Laravel yang berbahasa Inggeris)
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $minit = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new MailMessage)
                ->subject('Tetapan Semula Kata Laluan - Sistem Arkib Digital PKINK')
                ->greeting('Salam Sejahtera, '.TeksEmel::selamat($notifiable->nama_staff))
                ->line('Kami menerima permintaan untuk menetapkan semula kata laluan akaun anda dalam Sistem Arkib Digital PKINK.')
                ->action('Tetapkan Semula Kata Laluan', $url)
                ->line("Pautan ini sah selama {$minit} minit sahaja.")
                ->markdown('notifications::email', ['peringatan' => '⚠ **Peringatan keselamatan.** Jika anda **tidak** meminta untuk menetapkan semula kata laluan, abaikan emel ini (kata laluan anda tidak akan berubah) dan maklumkan kepada Bahagian Digital PKINK. **Jangan kongsi emel ini atau pautan di dalamnya dengan sesiapa**, termasuk orang yang mengaku sebagai kakitangan PKINK. Bahagian Digital tidak akan sekali-kali meminta kata laluan anda.'])
                ->salutation('Sekian, terima kasih. Bahagian Digital PKINK');
        });
    }
}
