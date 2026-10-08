<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

// Panggil semua Model
use App\Models\JenisDokumen;
use App\Models\Bahagian;
use App\Models\User;
use App\Models\Dokumen;

// Panggil semua Observer
use App\Observers\JenisDokumenObserver;
use App\Observers\BahagianObserver;
use App\Observers\UserObserver;
use App\Observers\DokumenObserver;

// Polisi kebenaran
use App\Policies\DokumenPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Daftarkan 'CCTV' secara paksa supaya sistem tidak terlepas pandang
        JenisDokumen::observe(JenisDokumenObserver::class);
        Bahagian::observe(BahagianObserver::class);
        User::observe(UserObserver::class);
        Dokumen::observe(DokumenObserver::class);

        // Kebenaran dokumen ikut peranan (lihat, muat naik, edit, padam)
        Gate::policy(Dokumen::class, DokumenPolicy::class);

        // Kandungan emel tetapan semula kata laluan dalam Bahasa Melayu
        // (menggantikan templat lalai Laravel yang berbahasa Inggeris)
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $minit = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

            return (new MailMessage)
                ->subject('Tetapan Semula Kata Laluan - Sistem Arkib Digital PKINK')
                ->greeting('Salam Sejahtera, ' . $notifiable->nama_staff)
                ->line('Kami menerima permintaan untuk menetapkan semula kata laluan akaun anda dalam Sistem Arkib Digital PKINK.')
                ->action('Tetapkan Semula Kata Laluan', $url)
                ->line("Pautan ini sah selama {$minit} minit sahaja.")
                ->line('Jika anda tidak membuat permintaan ini, sila abaikan emel ini. Kata laluan anda tidak akan berubah.')
                ->salutation('Sekian, terima kasih. Bahagian Digital PKINK');
        });
    }
}