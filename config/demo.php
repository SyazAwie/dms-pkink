<?php

/*
| Mod Demo (Fasa D): sistem dibuka kepada peserta demo melalui pautan terowong HTTPS.
| Dihidupkan dengan `php artisan demo:hidup` (fail penanda, .env tidak diubah) dan
| hanya mempengaruhi permintaan web: arahan konsol & penjadual (sandaran, semakan
| keselamatan) kekal menggunakan pangkalan data sebenar. Lihat App\Support\Demo.
*/

return [

    // Diisi oleh AppServiceProvider bagi setiap permintaan web (jangan tetapkan di .env)
    'aktif' => false,

    // Pangkalan data salinan untuk demo (dicipta oleh demo:sedia). Mesti berbeza daripada DB sebenar.
    'db' => env('DEMO_DB', 'dms_pkink_demo'),

    // Akaun demo yang dipaparkan sebagai butang "Masuk sebagai..." (No. KP, ikut susunan paparan).
    // Hanya akaun ini boleh log masuk semasa mod demo; kata laluan akaun lain dalam salinan dirawakkan.
    'akaun' => array_values(array_filter(array_map('trim', explode(',', env(
        'DEMO_AKAUN',
        '990101010101,990101010102,990101010103,990101010104,990101010107,990101010108,990101010106'
    ))))),

    // Laluan yang disekat semasa demo (corak nama laluan). Halaman GET kekal boleh dilihat
    // kecuali yang disenaraikan; tindakan ini menjejaskan peserta lain atau keluar dari sistem.
    'sekat' => [
        'login.proses', 'password.*', 'dua-faktor.*', 'pendaftaran.simpan',
        'kata-laluan.simpan', 'profil.kemaskini', 'profil.kata-laluan', 'profil.dua-faktor.*',
        'users.store', 'users.update', 'users.destroy',
        'pendaftaran.lulus', 'pendaftaran.tolak',
        'keselamatan.log-keluar', 'keselamatan.log-keluar-semua', 'keselamatan.kunci',
        'keselamatan.buka-kunci', 'keselamatan.sekat-ip', 'keselamatan.buka-ip', 'keselamatan.kecemasan',
    ],

    // Laluan cloudflared untuk demo:terowong (winget memasangnya di Program Files (x86))
    'cloudflared_bin' => env('DEMO_CLOUDFLARED_BIN', 'cloudflared'),

];
