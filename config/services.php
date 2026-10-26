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
        'bahasa' => env('OCR_BAHASA', 'msa+eng'),
        'tesseract_bin' => env('OCR_TESSERACT_BIN', 'tesseract'),
        'pdftoppm_bin' => env('OCR_PDFTOPPM_BIN', 'pdftoppm'),
        // Had masa (saat) bagi permintaan yang menjalankan OCR/AI secara segerak;
        // php.ini lalai (30 saat) tidak cukup untuk PDF besar atau borang banyak medan.
        'had_masa' => (int) env('OCR_HAD_MASA', 300),
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
    ],

];
