{{--
    Pengesahan slip kelulusan (P6), dibuka oleh kod QR pada slip. Halaman AWAM: berdiri
    sendiri (tanpa layouts.app) dan hanya memaparkan maklumat minimum.
--}}
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Pengesahan Slip Kelulusan - DMS PKINK</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="icon" href="{{ asset('ikon/favicon-32.png') }}?v=2" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <style>
        :root { --dms-brand: #183f68; --dms-line: #dce5ef; --dms-text: #1c3048; --dms-muted: #68798d; }
        * { box-sizing: border-box; }
        body {
            display: grid; min-height: 100vh; margin: 0; padding: 24px 16px; place-items: center;
            color: var(--dms-text); font-family: "Plus Jakarta Sans", system-ui, sans-serif;
            background:
                radial-gradient(circle at 0 0, rgba(23, 105, 210, .12), transparent 30rem),
                radial-gradient(circle at 100% 100%, rgba(37, 169, 121, .1), transparent 34rem),
                linear-gradient(135deg, #f1f6fb, #edf8fb 50%, #e8f0fb);
        }
        .panel {
            width: 100%; max-width: 480px; padding: 36px 30px; border: 1px solid rgba(255, 255, 255, .8);
            border-radius: 16px; background: rgba(255, 255, 255, .9); box-shadow: 0 18px 48px rgba(24, 63, 104, .1);
        }
        .jenama { margin-bottom: 20px; color: var(--dms-muted); font-size: 12px; font-weight: 700; letter-spacing: .3px; text-align: center; }
        .ikon {
            display: grid; width: 64px; height: 64px; margin: 0 auto 12px; place-items: center;
            border-radius: 18px; font-size: 30px; color: var(--warna); background: var(--latar);
        }
        h1 { margin: 0 0 6px; color: var(--dms-brand); font-size: 22px; font-weight: 800; text-align: center; }
        .mesej { margin: 0 0 20px; font-size: 14px; line-height: 1.6; text-align: center; }
        dl { margin: 0; border-top: 1px solid var(--dms-line); }
        .baris { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px dashed var(--dms-line); font-size: 13px; }
        dt { color: var(--dms-muted); font-weight: 600; }
        dd { margin: 0; font-weight: 700; text-align: right; overflow-wrap: anywhere; }
        .nota { margin: 16px 0 0; color: var(--dms-muted); font-size: 12px; line-height: 1.6; text-align: center; }
    </style>
</head>
<body>
    @if($sah)
        <main class="panel" style="--warna: #258c65; --latar: rgba(37, 169, 121, .12);">
            <div class="jenama">DMS PKINK &middot; Sistem Arkib Digital</div>
            <div class="ikon" aria-hidden="true"><i class="bi bi-patch-check-fill"></i></div>
            <h1>Slip Sah</h1>
            <p class="mesej">Permohonan ini berstatus <strong>Diluluskan</strong> dalam Sistem Arkib Digital PKINK.</p>
            <dl>
                <div class="baris"><dt>No. Rujukan</dt><dd>{{ $borang->dokumen->no_rujukan }}</dd></div>
                <div class="baris"><dt>Kategori</dt><dd>{{ $borang->dokumen->jenisDokumen->nama_dokumen ?? '-' }}</dd></div>
                <div class="baris"><dt>Pemohon</dt><dd>{{ $borang->pemohon->nama_staff ?? '-' }}</dd></div>
                <div class="baris"><dt>Diluluskan Oleh</dt><dd>{{ $borang->pelulus->nama_staff ?? 'Diarkibkan terus (Imbas & Arkib)' }}</dd></div>
                <div class="baris"><dt>Tarikh Kelulusan</dt><dd>{{ $borang->tarikh_kelulusan->translatedFormat('d M Y, h:i A') }}</dd></div>
                <div class="baris"><dt>Kod Pengesahan</dt><dd>{{ $borang->kodPengesahan() }}</dd></div>
            </dl>
            <p class="nota">Pastikan maklumat di atas sepadan dengan slip bercetak.</p>
        </main>
    @else
        <main class="panel" style="--warna: #dc3545; --latar: rgba(220, 53, 69, .1);">
            <div class="jenama">DMS PKINK &middot; Sistem Arkib Digital</div>
            <div class="ikon" aria-hidden="true"><i class="bi bi-x-octagon-fill"></i></div>
            <h1>Slip Tidak Sah</h1>
            <p class="mesej">Slip kelulusan ini tidak dapat disahkan.</p>
            <p class="nota">Kod pengesahan salah, atau permohonan telah ditarik balik, dipadam atau diluluskan semula dengan slip baharu. Sila hubungi Bahagian Digital PKINK untuk pengesahan.</p>
        </main>
    @endif
</body>
</html>
