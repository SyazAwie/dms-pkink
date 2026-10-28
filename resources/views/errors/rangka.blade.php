{{--
    Rangka halaman ralat (404, 405, 419, 429, 500, 503). Berdiri sendiri: tidak guna
    Auth, sesi atau pangkalan data, supaya selamat dipaparkan kepada tetamu dan ketika
    pelayan sendiri bermasalah. 403 kekal dalam layouts.app (pengguna sudah log masuk).
--}}
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('kod') @yield('tajuk') - DMS PKINK</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="icon" href="{{ asset('ikon/favicon-32.png') }}?v=2" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <style>
        :root {
            --dms-brand: #183f68;
            --dms-primary: #1769d2;
            --dms-line: #dce5ef;
            --dms-text: #1c3048;
            --dms-muted: #68798d;
        }
        * { box-sizing: border-box; }
        body {
            display: grid;
            min-height: 100vh;
            margin: 0;
            padding: 24px 16px;
            place-items: center;
            color: var(--dms-text);
            background:
                radial-gradient(circle at 0 0, rgba(23, 105, 210, .12), transparent 30rem),
                radial-gradient(circle at 100% 100%, rgba(37, 169, 121, .1), transparent 34rem),
                linear-gradient(135deg, #f1f6fb, #edf8fb 50%, #e8f0fb);
            font-family: "Plus Jakarta Sans", system-ui, sans-serif;
        }
        .ralat-panel {
            width: 100%;
            max-width: 480px;
            padding: 40px 32px;
            border: 1px solid rgba(255, 255, 255, .8);
            border-radius: 16px;
            background: rgba(255, 255, 255, .85);
            box-shadow: 0 18px 48px rgba(24, 63, 104, .1);
            text-align: center;
        }
        .ralat-jenama { margin-bottom: 22px; color: var(--dms-muted); font-size: 12px; font-weight: 700; letter-spacing: .3px; }
        .ralat-ikon {
            display: grid;
            width: 64px;
            height: 64px;
            margin: 0 auto 14px;
            place-items: center;
            border-radius: 18px;
            color: var(--warna, var(--dms-primary));
            background: var(--latar, rgba(23, 105, 210, .1));
            font-size: 28px;
        }
        .ralat-kod { color: var(--dms-muted); font-size: 13px; font-weight: 800; letter-spacing: 2px; }
        h1 { margin: 6px 0 10px; color: var(--dms-brand); font-size: 24px; font-weight: 800; }
        p { margin: 0 0 6px; font-size: 14px; line-height: 1.6; }
        .ralat-hint { color: var(--dms-muted); font-size: 12.5px; }
        .ralat-butang { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 26px; }
        .ralat-butang a {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }
        .butang-utama { color: #fff; background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary)); }
        .butang-utama:hover { filter: brightness(1.08); }
        .butang-kedua { border: 1px solid var(--dms-line); color: var(--dms-text); background: #fff; }
        .butang-kedua:hover { border-color: rgba(23, 105, 210, .35); color: var(--dms-primary); }
        @media (max-width: 575.98px) {
            .ralat-panel { padding: 32px 20px; }
            h1 { font-size: 21px; }
            .ralat-butang a { flex: 1 1 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <main class="ralat-panel" style="@yield('gaya')">
        <div class="ralat-jenama">DMS PKINK &middot; Sistem Arkib Digital</div>
        <div class="ralat-ikon" aria-hidden="true"><i class="bi @yield('ikon')"></i></div>
        <div class="ralat-kod">@yield('kod')</div>
        <h1>@yield('tajuk')</h1>
        <p>@yield('mesej')</p>
        @hasSection('petunjuk')
            <p class="ralat-hint">@yield('petunjuk')</p>
        @endif
        <div class="ralat-butang">
            @section('butang')
                <a href="javascript:history.back()" class="butang-kedua"><i class="bi bi-arrow-left"></i> Kembali</a>
                <a href="{{ url('/') }}" class="butang-utama"><i class="bi bi-house-door"></i> Halaman Utama</a>
            @show
        </div>
    </main>
</body>
</html>
