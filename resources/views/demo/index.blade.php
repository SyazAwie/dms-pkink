{{-- Halaman tayangan demo (Fasa D7): kod QR pautan + senarai akaun demo, untuk projektor/slaid --}}
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Cuba Sistem - DMS PKINK</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="icon" href="{{ asset('ikon/favicon-32.png') }}?v=2" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --dms-brand: #183f68; --dms-primary: #1769d2; --dms-line: #dce5ef; --dms-text: #1c3048; --dms-muted: #68798d; }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh; margin: 0; padding: 32px 16px; color: var(--dms-text);
            background: linear-gradient(135deg, #f1f6fb, #edf8fb 50%, #e8f0fb);
            font-family: "Plus Jakarta Sans", system-ui, sans-serif;
        }
        .kad { display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: 40px; align-items: center; max-width: 1040px; margin: 0 auto; padding: 40px; border-radius: 20px; background: #fff; box-shadow: 0 18px 48px rgba(24, 63, 104, .1); }
        .jenama { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .jenama img { width: 52px; height: 52px; }
        .jenama-tajuk { color: var(--dms-brand); font-size: 22px; font-weight: 800; }
        .jenama-sub { color: var(--dms-muted); font-size: 13px; }
        h1 { margin: 0 0 8px; color: var(--dms-brand); font-size: 32px; font-weight: 800; }
        .langkah { margin: 0 0 18px; padding-left: 20px; font-size: 16px; line-height: 1.7; }
        .qr { width: 100%; padding: 10px; border: 1px solid var(--dms-line); border-radius: 16px; background: #fff; }
        .qr svg { display: block; width: 100%; height: auto; }
        .url { margin-top: 12px; color: var(--dms-primary); font-size: 15px; font-weight: 700; text-align: center; word-break: break-all; }
        .amaran { margin-top: 10px; padding: 8px 12px; border-radius: 8px; background: #fff8e6; color: #5c4400; font-size: 13px; text-align: center; }
        .akaun { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin: 0; padding: 0; list-style: none; }
        .akaun li { padding: 10px 12px; border: 1px solid var(--dms-line); border-radius: 10px; }
        .akaun strong { display: block; color: var(--dms-brand); font-size: 14px; }
        .akaun span { color: var(--dms-muted); font-size: 12.5px; }
        .nota { margin-top: 16px; color: var(--dms-muted); font-size: 13px; }
        @media (max-width: 820px) { .kad { grid-template-columns: 1fr; padding: 24px 18px; gap: 24px; } .qr { width: min(100%, 320px); margin: 0 auto; } h1 { font-size: 26px; } }
        @media (max-width: 420px) { .akaun { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <main class="kad">
        <div>
            <div class="qr" role="img" aria-label="Kod QR pautan demo">{!! $qr !!}</div>
            <div class="url">{{ $url }}</div>
            @unless($adaTerowong)
                <div class="amaran">Terowong belum berjalan: pautan ini hanya boleh dibuka di komputer ini. Jalankan <code>php artisan demo:terowong</code>.</div>
            @endunless
        </div>
        <div>
            <div class="jenama">
                <img src="{{ asset('images/emel/logo-pkink.png') }}" alt="Logo PKINK">
                <div>
                    <div class="jenama-tajuk">DMS PKINK</div>
                    <div class="jenama-sub">Sistem Arkib Digital &middot; Perbadanan Kemajuan Iktisad Negeri Kelantan</div>
                </div>
            </div>
            @if($percubaan)
                <h1>Cuba sistem sebenar</h1>
                <ol class="langkah">
                    <li>Imbas kod QR dengan kamera telefon.</li>
                    <li>Tekan <strong>Daftar Akaun Baharu</strong> dan isi No. KP, nama, emel serta bahagian.</li>
                    <li>Tunggu pentadbir meluluskan permohonan anda.</li>
                    <li>Semak emel: log masuk dengan kata laluan sementara, kemudian tetapkan kata laluan baharu.</li>
                    <li>Cuba Isi Borang, Imbas &amp; Hantar dengan kamera, dan ikuti status kelulusan anda.</li>
                </ol>
                <p class="nota">Sesi latihan: akaun dan semua data anda (termasuk No. KP dan emel) akan dipadam selepas sesi tamat.</p>
            @else
                <h1>Cuba sistem di telefon anda</h1>
                <ol class="langkah">
                    <li>Imbas kod QR dengan kamera telefon.</li>
                    <li>Pilih satu peranan di halaman log masuk (tiada kata laluan diperlukan).</li>
                    <li>Cuba Isi Borang, Imbas &amp; Hantar dengan kamera, atau Kelulusan.</li>
                </ol>
                <ul class="akaun">
                    @foreach($akaun as $a)
                        <li><strong>{{ $a['peranan'] }}</strong><span>{{ $a['nama'] }}</span></li>
                    @endforeach
                </ul>
                <p class="nota">Data rekaan sahaja. Jangan masukkan maklumat peribadi sebenar; semua perubahan dipadam selepas demo.</p>
            @endif
        </div>
    </main>
</body>
</html>
