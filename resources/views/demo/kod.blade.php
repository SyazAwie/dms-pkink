{{-- Kod akses demo/percubaan (ModDemo::pintuKod): pautan terowong sahaja tidak mencukupi --}}
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Kod Akses - DMS PKINK</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="icon" href="{{ asset('ikon/favicon-32.png') }}?v=2" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --dms-brand: #183f68; --dms-primary: #1769d2; --dms-line: #dce5ef; --dms-text: #1c3048; --dms-muted: #68798d; }
        * { box-sizing: border-box; }
        body { display: grid; min-height: 100vh; margin: 0; padding: 24px 16px; place-items: center; color: var(--dms-text);
            background: linear-gradient(135deg, #f1f6fb, #edf8fb 50%, #e8f0fb); font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
        .panel { width: 100%; max-width: 400px; padding: 32px 24px; border-radius: 16px; background: #fff; box-shadow: 0 18px 48px rgba(24, 63, 104, .1); text-align: center; }
        .panel img { width: 56px; height: 56px; }
        h1 { margin: 12px 0 6px; color: var(--dms-brand); font-size: 22px; font-weight: 800; }
        p { margin: 0 0 18px; color: var(--dms-muted); font-size: 14px; line-height: 1.6; }
        label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 700; text-align: left; }
        input { width: 100%; padding: 12px 14px; border: 1px solid var(--dms-line); border-radius: 10px; font: inherit; font-size: 22px; font-weight: 800; letter-spacing: 6px; text-align: center; text-transform: uppercase; }
        input:focus { border-color: var(--dms-primary); outline: 3px solid rgba(23, 105, 210, .15); }
        button { width: 100%; min-height: 46px; margin-top: 14px; border: 0; border-radius: 10px; color: #fff; background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary)); font: inherit; font-weight: 700; cursor: pointer; }
        .ralat { margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: #fff1f2; color: #be123c; font-size: 13px; }
    </style>
</head>
<body>
    <main class="panel">
        <img src="{{ asset('images/emel/logo-pkink.png') }}" alt="Logo PKINK">
        <h1>Masukkan kod akses</h1>
        <p>Sistem ini dibuka untuk sesi demo sahaja. Imbas kod QR pada skrin, atau taip kod 6 aksara yang dipaparkan.</p>
        @if($salah)
            <div class="ralat" role="alert">Kod akses tidak sah. Semak semula kod pada skrin.</div>
        @endif
        <form method="POST" action="{{ route('demo.kod') }}">
            @csrf
            <input type="hidden" name="ke" value="{{ $ke }}">
            <label for="kod">Kod akses</label>
            <input type="text" id="kod" name="kod" maxlength="6" autocomplete="off" autocapitalize="characters" required autofocus>
            <button type="submit">Teruskan</button>
        </form>
    </main>
</body>
</html>
