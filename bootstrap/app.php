<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\HeaderKeselamatan;
use App\Http\Middleware\SemakPeranan;
use App\Http\Middleware\WajibDuaFaktor;
use App\Http\Middleware\WajibTukarKataLaluan;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias untuk semakan peranan: ->middleware('peranan:SUPERADMIN,ADMIN')
        // dan paksaan tukar kata laluan sementara: ->middleware('wajib.tukar')
        $middleware->alias([
            'peranan' => SemakPeranan::class,
            'wajib.tukar' => WajibTukarKataLaluan::class,
            'wajib.dua.faktor' => WajibDuaFaktor::class,
        ]);

        // Terowong HTTPS (cth. cloudflared untuk demo) berjalan pada komputer yang sama dan
        // menghantar X-Forwarded-*: percayai localhost sahaja supaya URL https dan IP sebenar
        // pengguna (had kadar log masuk) betul, tanpa membenarkan IP lain memalsukan header itu.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->web(append: [HeaderKeselamatan::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();