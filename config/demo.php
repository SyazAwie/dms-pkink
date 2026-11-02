<?php

/*
| Mod Demo (Fasa D): sistem dibuka kepada peserta demo melalui pautan terowong HTTPS.
| Dihidupkan dengan `php artisan demo:hidup` (fail penanda, .env tidak diubah) dan
| hanya mempengaruhi permintaan web: arahan konsol & penjadual (sandaran, semakan
| keselamatan) kekal menggunakan pangkalan data sebenar. Lihat App\Support\Demo.
*/

return [

    // Diisi oleh AppServiceProvider bagi setiap permintaan web: null | 'demo' | 'percubaan'
    'mod' => null,

    // Pangkalan data salinan (dicipta oleh demo:sedia [--percubaan]). Mesti berbeza daripada DB sebenar.
    'db' => env('DEMO_DB', 'dms_pkink_demo'),
    'percubaan_db' => env('PERCUBAAN_DB', 'dms_pkink_percubaan'),

    // Akaun demo yang dipaparkan sebagai butang "Masuk sebagai..." (No. KP, ikut susunan paparan).
    // Hanya akaun ini boleh log masuk semasa mod demo; kata laluan akaun lain dalam salinan dirawakkan.
    // Ditetapkan dalam .env (DEMO_AKAUN=noKP1,noKP2,...); kosong = tiada butang akaun demo.
    'akaun' => array_values(array_filter(array_map('trim', explode(',', (string) env('DEMO_AKAUN', ''))))),

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
