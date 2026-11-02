<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ocr' => [
        'bahasa' => env('OCR_BAHASA', 'eng'),
        'tesseract_bin' => env('OCR_TESSERACT_BIN', 'tesseract'),
        'pdftoppm_bin' => env('OCR_PDFTOPPM_BIN', 'pdftoppm'),
        // Had masa (saat) bagi permintaan yang menjalankan OCR/AI secara segerak;
        // php.ini lalai (30 saat) tidak cukup untuk PDF besar atau borang banyak medan.
        'had_masa' => (int) env('OCR_HAD_MASA', 300),
        // Satu analisis OCR/AI pada satu masa (GPU 6GB, Tesseract guna semua teras): pengguna
        // lain menunggu giliran hingga N saat (Fasa D4 ringkas; queue penuh = T2)
        'tunggu_giliran' => (int) env('OCR_TUNGGU_GILIRAN', 120),
        // Buang garisan kotak/jadual sebelum OCR (App\Support\BuangGarisan); hasil terbaik
        // antara imej asal dan imej bersih dipilih bagi setiap muka surat
        'buang_garisan' => (bool) env('OCR_BUANG_GARISAN', true),
        // T6: tanda visual borang dalam teks OCR untuk AI: perkataan dipotong (~~tidak sokong~~)
        // dan kotak pilihan [✓]/[ ] (App\Support\TandaVisual). ~2 s setiap muka surat.
        'tanda_visual' => (bool) env('OCR_TANDA_VISUAL', true),
        // Laluan hibrid: muka surat dengan skor Tesseract < ambang (cth. tulisan tangan) dibaca
        // semula oleh model penglihatan tempatan (VlmOcrService). Ujian: tulisan tangan ~77%,
        // dokumen bertaip 81-92%. Kosongkan OCR_VLM_MODEL atau OCR_VLM_AKTIF=false untuk matikan.
        'vlm_aktif' => (bool) env('OCR_VLM_AKTIF', true),
        'vlm_model' => env('OCR_VLM_MODEL', 'glm-ocr:q8_0'),
        'vlm_ambang' => (float) env('OCR_VLM_AMBANG', 80),
        // TB4: beza imej templat borang kosong (disusun TB6) dengan imbasan: medan tanpa dakwat
        // pengisi = kosong, dan keratan dakwat dibaca semula sebagai petunjuk AI
        // (App\Services\BezaTemplatService). Ambang dakwat: piksel 300dpi & nisbah luas kotak.
        // Ukuran 4 borang BD: 44 -> 45/52 (biasa & tanpa few-shot, 2 larian setiap satu), +~0.5 s OCR.
        'beza_templat' => (bool) env('OCR_BEZA_TEMPLAT', true),
        'beza_min_piksel' => (int) env('OCR_BEZA_MIN_PIKSEL', 60),
        'beza_min_nisbah' => (float) env('OCR_BEZA_MIN_NISBAH', 0.003),
        // Bacaan semula keratan dakwat diberi kepada AI hanya jika keyakinan Tesseract >= ini
        'beza_min_yakin' => (float) env('OCR_BEZA_MIN_YAKIN', 70),
    ],

    // 2FA (P13): wajib bagi SUPERADMIN/ADMIN bila true. Lalai false; true di pelayan produksi (.env.example)
    // supaya akaun demo tidak memerlukan telefon authenticator.
    'dua_faktor' => [
        'wajib' => (bool) env('DUA_FAKTOR_WAJIB', false),
    ],

    // Fasa Keselamatan: pemeriksaan fail muat naik (App\Services\PemeriksaFail)
    'keselamatan' => [
        // false HANYA untuk ujian automatik (phpunit.xml): fail palsu ujian bukan PDF sebenar
        'periksa_fail' => (bool) env('KESELAMATAN_PERIKSA_FAIL', true),
        // Kosong = Windows Defender (MpCmdRun.exe) di Windows, clamdscan di Linux
        'antivirus_bin' => env('ANTIVIRUS_BIN'),
        // true = tolak muat naik jika imbasan antivirus tidak dapat dibuat (disyorkan di pelayan produksi)
        'antivirus_wajib' => (bool) env('ANTIVIRUS_WAJIB', false),
        'antivirus_had_masa' => (int) env('ANTIVIRUS_HAD_MASA', 60),
        // Had "bom": PDF kecil beratus muka surat / imej berpiksel gergasi membebankan OCR & cakera
        'maks_muka_surat' => (int) env('KESELAMATAN_MAKS_MUKA_SURAT', 50),
        'maks_piksel' => (int) env('KESELAMATAN_MAKS_PIKSEL', 40_000_000),
        // OCR tidak dijalankan bila ruang cakera kurang daripada ini (MB)
        'min_ruang_cakera_mb' => (int) env('KESELAMATAN_MIN_RUANG_MB', 1024),
        // IP yang tidak pernah disekat (dipisah koma), cth. IP awam pejabat PKINK (NAT dikongsi staf)
        'ip_dipercayai' => env('KESELAMATAN_IP_DIPERCAYAI'),
        // Keadaan kecemasan (mod kecemasan, log keluar paksa); fail, bukan cache
        'fail_keadaan' => storage_path('app/private/keselamatan.json'),
    ],

    // Tera air salinan muat turun (TeraAirService). Laluan penuh fon TrueType; kosong = cuba
    // Arial Bold (Windows) / DejaVu Sans Bold (Linux), jika tiada guna fon bitmap GD
    'tera_air' => [
        'fon' => env('TERA_AIR_FON'),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'sea-lion-local'),
        // Prestasi (OllamaExtractionService). num_gpu 99 = muatkan SEMUA lapisan model ke GPU
        // (RTX 3050 6GB: 75% → 100% VRAM, ~1.7x lebih laju); jika model gagal dimuat, permintaan
        // diulang dengan tetapan lalai Ollama. Kosongkan OLLAMA_NUM_GPU untuk biar Ollama tentukan.
        'num_gpu' => env('OLLAMA_NUM_GPU', 99),
        // Konteks (token): prompt + jawapan. 2048 terlalu kecil (prompt Minit ~1900 token).
        'num_ctx' => (int) env('OLLAMA_NUM_CTX', 4096),
        // Had panjang jawapan: elak jawapan meleret sehingga tamat masa
        'num_predict' => (int) env('OLLAMA_NUM_PREDICT', 2048),
        // Model kekal dalam memori selepas digunakan (lalai Ollama 5 minit → muat semula lambat)
        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '30m'),
        'had_masa' => (int) env('OLLAMA_HAD_MASA', 180),
        // P16: pembetulan staf terkini bagi kategori dimasukkan ke prompt sebagai contoh
        'few_shot' => (bool) env('OLLAMA_FEW_SHOT', true),
    ],

    // Notifikasi emel (P2): mati secara lalai kerana akaun ujian guna domain @pkink.com yang
    // mungkin domain sebenar; hidupkan bila akaun dan alamat emel sebenar digunakan
    'notifikasi' => [
        'emel' => (bool) env('NOTIFIKASI_EMEL', false),
    ],

    // Sandaran harian (php artisan sandaran:jalan): pangkalan data + fail imbasan dalam satu zip
    'sandaran' => [
        // Laluan penuh .exe kerana Herd tidak nampak PATH Windows (cth. XAMPP)
        'mysqldump_bin' => env('SANDARAN_MYSQLDUMP_BIN', 'mysqldump'),
        'folder' => env('SANDARAN_FOLDER', storage_path('app/sandaran')),
        // Salinan kedua di luar projek (cth. folder OneDrive) supaya selamat jika cakera rosak
        'salinan' => env('SANDARAN_SALINAN'),
        // Fasa H: matikan bila pelayan sudah ada sandaran sendiri (cth. snapshot VM); amaran
        // "sandaran tertunggak" turut dimatikan
        'aktif' => (bool) env('SANDARAN_AKTIF', true),
        // Masa sandaran harian (HH:MM). Pelayan 24 jam: waktu tidak sibuk, cth. 02:00
        'masa' => env('SANDARAN_MASA', '12:30'),
        'simpan_hari' => (int) env('SANDARAN_SIMPAN_HARI', 14),
        // Simpanan bertingkat selepas simpan_hari: terbaharu setiap minggu / bulan (0 = tiada)
        'simpan_minggu' => (int) env('SANDARAN_SIMPAN_MINGGU', 8),
        'simpan_bulan' => (int) env('SANDARAN_SIMPAN_BULAN', 6),
        'had_masa' => (int) env('SANDARAN_HAD_MASA', 900),
        // Fasa Keselamatan S4: zip disulitkan AES-256 (simpan kata laluan DI LUAR pelayan)
        'kata_laluan' => env('SANDARAN_KATA_LALUAN'),
        // Klien mysql untuk sandaran:uji / sandaran:pulih (lalai: sebelah mysqldump)
        'mysql_bin' => env('SANDARAN_MYSQL_BIN'),
    ],

];
