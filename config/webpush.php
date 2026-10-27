<?php

/*
 * Tetapan tambahan laravel-notification-channels/webpush (nilai lain ikut lalai pakej,
 * digabung melalui mergeConfigFrom). Kunci VAPID dalam .env (php artisan webpush:vapid).
 */
return [

    'vapid' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    // Klien HTTP (Guzzle) ke perkhidmatan push: jangan ikut redirect (elak SSRF melalui
    // ubah hala) dan hadkan masa supaya tindakan pengguna tidak tersekat lama.
    'client_options' => [
        'allow_redirects' => false,
        'timeout' => 10,
    ],

];
