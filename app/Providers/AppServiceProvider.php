<?php

namespace App\Providers;

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
    }
}