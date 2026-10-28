<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- PWA & notifikasi peranti (public/manifest.webmanifest, public/sw.js) --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#183f68">
    <link rel="apple-touch-icon" href="{{ asset('ikon/dms-192.png') }}">
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
    <title>DMS PKINK - @yield('title', 'Papan Pemuka')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <style>
        :root {
            --dms-brand: #183f68;
            --dms-primary: #1769d2;
            --dms-primary-hover: #1159b5;
            --dms-mint: #25a979;
            --dms-page-start: #f1f6fb;
            --dms-page-middle: #edf8fb;
            --dms-page-end: #e8f0fb;
            --dms-surface: rgba(255, 255, 255, .72);
            --dms-surface-strong: rgba(255, 255, 255, .9);
            --dms-border: rgba(255, 255, 255, .8);
            --dms-line: #dce5ef;
            --dms-text: #1c3048;
            --dms-muted: #68798d;
            --dms-danger: #c43d4e;
            --dms-shadow: 0 18px 48px rgba(24, 63, 104, .1);
            --sidebar-width: 280px;
        }

        * { box-sizing: border-box; }

        html { min-width: 320px; }

        body {
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
            color: var(--dms-text);
            background:
                radial-gradient(circle at 0 0, rgba(23, 105, 210, .12), transparent 30rem),
                radial-gradient(circle at 100% 100%, rgba(37, 169, 121, .1), transparent 34rem),
                linear-gradient(135deg, var(--dms-page-start), var(--dms-page-middle) 50%, var(--dms-page-end));
            font-family: "Plus Jakarta Sans", sans-serif;
        }

        button, a { -webkit-tap-highlight-color: transparent; }
        a { text-decoration: none; }

        .app-shell { min-height: 100vh; }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1040;
            display: flex;
            width: var(--sidebar-width);
            flex-direction: column;
            border-right: 1px solid var(--dms-border);
            background: rgba(255, 255, 255, .76);
            box-shadow: var(--dms-shadow);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            transition: transform .3s ease;
        }

        .brand {
            display: grid;
            min-height: 80px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--dms-line);
        }

        .brand-mark {
            display: grid;
            width: 44px;
            height: 44px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 12px;
            color: #fff;
            background: linear-gradient(135deg, var(--dms-brand), var(--dms-primary));
            box-shadow: 0 8px 22px rgba(23, 105, 210, .24);
            font-size: 20px;
        }

        .brand-copy { min-width: 0; line-height: 1.25; }
        .brand-title { overflow: hidden; color: var(--dms-brand); font-size: 16px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .brand-subtitle { overflow: hidden; margin-top: 4px; color: var(--dms-muted); font-size: 11px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }

        .icon-button {
            display: inline-grid;
            width: 40px;
            height: 40px;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid var(--dms-line);
            border-radius: 10px;
            color: var(--dms-text);
            background: rgba(255, 255, 255, .72);
            cursor: pointer;
            transition: border-color .2s ease, background .2s ease, color .2s ease, transform .2s ease;
        }

        .icon-button:hover { border-color: rgba(23, 105, 210, .35); color: var(--dms-primary); background: #fff; }
        .icon-button:active { transform: scale(.97); }
        .icon-button:focus-visible, .nav-link:focus-visible, .quick-link:focus-visible { outline: 3px solid rgba(23, 105, 210, .2); outline-offset: 2px; }
        .sidebar-close { display: none; }

        .nav-area { flex: 1; overflow-y: auto; padding: 18px 16px; }
        .nav-label { margin: 0 12px 10px; color: var(--dms-muted); font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .nav-list { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; }

        .nav-link {
            display: grid;
            min-height: 46px;
            grid-template-columns: 22px minmax(0, 1fr) auto;
            align-items: center;
            gap: 11px;
            padding: 11px 13px;
            border-radius: 12px;
            color: var(--dms-muted);
            font-size: 13px;
            font-weight: 700;
            transition: color .2s ease, background .2s ease, transform .2s ease, box-shadow .2s ease;
        }

        .nav-link i:first-child { font-size: 18px; text-align: center; }
        .nav-link span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .nav-link:hover { color: var(--dms-text); background: rgba(231, 239, 248, .9); transform: translateX(2px); }
        .nav-link.active { color: #fff; background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary)); box-shadow: 0 8px 20px rgba(23, 105, 210, .2); }

        .sidebar-footer { padding: 15px 16px; border-top: 1px solid var(--dms-line); }
        .sidebar-user { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: 11px; padding: 11px; border-radius: 12px; background: rgba(231, 239, 248, .72); }
        .avatar { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 10px; color: var(--dms-brand); background: rgba(37, 169, 121, .13); font-size: 13px; font-weight: 800; }
        .user-copy { min-width: 0; }
        .user-name { overflow: hidden; color: var(--dms-text); font-size: 12px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .user-role { margin-top: 3px; color: var(--dms-muted); font-size: 10px; }

        .page { min-height: 100vh; padding-left: var(--sidebar-width); }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            display: grid;
            min-height: 80px;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 16px;
            padding: 12px 32px;
            border-bottom: 1px solid var(--dms-border);
            background: rgba(255, 255, 255, .72);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .topbar-left { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: 12px; min-width: 0; }
        .menu-toggle { display: none; }
        .page-heading { min-width: 0; }
        .page-eyebrow { overflow: hidden; color: var(--dms-primary); font-size: 11px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
        .page-title { overflow: hidden; margin: 3px 0 0; color: var(--dms-brand); font-size: 20px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }

        .topbar-actions { display: flex; align-items: center; gap: 9px; }
        .notification { position: relative; }
        .pautan-profil { color: inherit; text-decoration: none; transition: background .2s ease, color .2s ease; }
        .pautan-profil:hover { color: var(--dms-primary); }
        .sidebar-user.pautan-profil:hover, .sidebar-user.pautan-profil.aktif { background: rgba(23, 105, 210, .1); }
        .nav-bil { min-width: 22px; padding: 2px 7px; border-radius: 10px; background: var(--dms-danger); color: #fff; font-size: 10.5px; font-weight: 800; text-align: center; }
        .loceng-menu { min-width: 250px; padding: 6px; border: 1px solid var(--dms-line); border-radius: 12px; box-shadow: 0 12px 30px rgba(15, 23, 42, .12); }
        .loceng-menu .dropdown-header { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
        .loceng-menu .dropdown-item { display: grid; grid-template-columns: 20px 1fr auto; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 8px; font-size: 13px; font-weight: 600; }
        .loceng-bil { min-width: 24px; padding: 2px 7px; border-radius: 10px; background: rgba(107, 122, 144, .14); color: var(--dms-muted); font-size: 11px; font-weight: 800; text-align: center; }
        .loceng-bil.ada { background: var(--dms-danger); color: #fff; }
        /* Senarai notifikasi dalam loceng (P-C) */
        .loceng-menu { width: min(360px, calc(100vw - 24px)); padding: 0; overflow: hidden; }
        .loceng-kepala { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 14px; border-bottom: 1px solid var(--dms-line); }
        .loceng-kepala strong { color: var(--dms-brand); font-size: 14px; }
        .loceng-pautan { padding: 0; border: 0; color: var(--dms-primary); background: none; font-size: 12px; font-weight: 700; }
        .loceng-senarai { max-height: min(380px, 60vh); overflow-y: auto; }
        .loceng-item { display: grid; grid-template-columns: 34px 1fr; gap: 10px; padding: 11px 14px; border-bottom: 1px solid var(--dms-line); color: var(--dms-text); }
        .loceng-item:hover { background: rgba(23, 105, 210, .05); }
        .loceng-item.belum-baca { background: rgba(23, 105, 210, .06); }
        .loceng-item.belum-baca strong::after { content: ""; display: inline-block; width: 7px; height: 7px; margin-left: 6px; border-radius: 50%; background: var(--dms-primary); vertical-align: middle; }
        .loceng-teks { min-width: 0; }
        .loceng-teks strong { display: block; font-size: 12.5px; }
        .loceng-teks span { display: -webkit-box; overflow: hidden; color: var(--dms-muted); font-size: 12px; line-height: 1.4; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .loceng-teks small { color: var(--dms-muted); font-size: 11px; }
        .loceng-ikon { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 10px; font-size: 15px; }
        .warna-biru { color: var(--dms-primary); background: rgba(23, 105, 210, .1); }
        .warna-hijau { color: var(--dms-mint); background: rgba(37, 169, 121, .12); }
        .warna-kuning { color: #b45309; background: rgba(245, 158, 11, .14); }
        .warna-merah { color: var(--dms-danger); background: rgba(220, 53, 69, .1); }
        .loceng-kosong { padding: 22px 14px; color: var(--dms-muted); font-size: 12.5px; text-align: center; }
        .loceng-giliran { padding: 6px; border-bottom: 1px solid var(--dms-line); background: rgba(231, 239, 248, .4); }
        .loceng-kaki { display: block; padding: 10px 14px; color: var(--dms-primary); font-size: 12.5px; font-weight: 700; text-align: center; }
        .loceng-kaki:hover { background: rgba(23, 105, 210, .05); }
        .loceng-peranti { display: grid; grid-template-columns: 18px 1fr auto; align-items: center; gap: 8px; padding: 9px 14px; border-bottom: 1px solid var(--dms-line); color: var(--dms-text); background: rgba(37, 169, 121, .06); font-size: 12px; }
        .loceng-peranti i { color: var(--dms-mint); }
        .loceng-peranti.mati { background: rgba(231, 239, 248, .5); }
        .loceng-peranti.mati i { color: var(--dms-muted); }
        /* Popup notifikasi baharu dalam halaman */
        .notif-toast-bekas { position: fixed; right: 16px; bottom: 16px; z-index: 1090; display: grid; gap: 8px; width: min(360px, calc(100vw - 32px)); }
        .notif-toast { display: grid; grid-template-columns: 34px 1fr auto; gap: 10px; align-items: start; padding: 12px; border: 1px solid var(--dms-line); border-radius: 12px; color: var(--dms-text); background: #fff; box-shadow: 0 14px 34px rgba(15, 23, 42, .18); animation: notifMasuk .25s ease; }
        .notif-toast strong { display: block; font-size: 13px; }
        .notif-toast span { color: var(--dms-muted); font-size: 12px; }
        .notif-toast button { border: 0; color: var(--dms-muted); background: none; }
        @keyframes notifMasuk { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        .notification-badge { position: absolute; top: -6px; right: -6px; display: inline-grid; min-width: 19px; height: 19px; padding: 0 5px; place-items: center; border: 2px solid #fff; border-radius: 10px; background: var(--dms-danger); color: #fff; font-size: 10px; font-weight: 800; line-height: 1; }
        .top-user { min-width: 0; max-width: 200px; text-align: right; }
        .top-user strong, .top-user small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .top-user strong { font-size: 12px; }
        .top-user small { margin-top: 2px; color: var(--dms-muted); font-size: 10px; }
        .logout-form { margin: 0; }
        /* Menu pengguna (tukar peranan) */
        .pengguna-butang { display: flex; align-items: center; gap: 10px; min-width: 0; padding: 4px 6px; border: 0; border-radius: 12px; color: inherit; background: transparent; cursor: pointer; }
        .pengguna-butang:hover, .pengguna-butang[aria-expanded="true"] { background: rgba(23, 105, 210, .08); }
        .pengguna-butang:focus-visible { outline: 3px solid rgba(23, 105, 210, .2); }
        .avatar-kecil { width: 36px; height: 36px; font-size: 13px; }
        .avatar-aktif { color: #fff; background: linear-gradient(135deg, var(--dms-brand), var(--dms-primary)); }
        .pengguna-menu { min-width: 250px; padding: 6px; border: 1px solid var(--dms-line); border-radius: 12px; box-shadow: 0 12px 30px rgba(15, 23, 42, .12); }
        .pengguna-menu-kepala { padding: 8px 10px 4px; }
        .pengguna-menu-kepala strong, .pengguna-menu-kepala small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pengguna-menu-kepala strong { color: var(--dms-brand); font-size: 13px; }
        .pengguna-menu-kepala small { color: var(--dms-muted); font-size: 11px; }
        .pengguna-menu .dropdown-header { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
        .pengguna-menu .dropdown-item { display: grid; grid-template-columns: 20px 1fr auto; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 8px; font-size: 13px; font-weight: 600; }
        .pengguna-menu .dropdown-item.active { color: var(--dms-primary); background: rgba(23, 105, 210, .1); }

        .content-wrap { width: 100%; max-width: 1500px; margin: 0 auto; padding: 28px 32px 40px; }
        .content-panel { min-height: calc(100vh - 148px); }

        .glass-panel {
            border: 1px solid var(--dms-border);
            border-radius: 12px;
            background: var(--dms-surface);
            box-shadow: 0 12px 34px rgba(24, 63, 104, .07);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .page-intro { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 18px; margin-bottom: 24px; }
        .page-intro h2 { margin: 0; color: var(--dms-brand); font-size: 24px; font-weight: 800; }
        .page-intro p { margin: 7px 0 0; color: var(--dms-muted); font-size: 13px; line-height: 1.7; }
        .date-chip { display: inline-flex; align-items: center; gap: 8px; padding: 9px 12px; border: 1px solid var(--dms-line); border-radius: 9px; color: var(--dms-muted); background: var(--dms-surface-strong); font-size: 11px; font-weight: 700; }
        .date-chip i { color: var(--dms-primary); }

        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .summary-grid.tiga-kad { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .summary-card { padding: 20px; transition: transform .2s ease, box-shadow .2s ease; }
        .summary-card:hover { transform: translateY(-3px); box-shadow: var(--dms-shadow); }
        .summary-head { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 14px; }
        .summary-label { overflow: hidden; color: var(--dms-muted); font-size: 11px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
        .summary-value { margin-top: 7px; color: var(--dms-brand); font-size: 29px; font-weight: 800; }
        .summary-icon { display: grid; width: 43px; height: 43px; place-items: center; border-radius: 11px; color: var(--dms-primary); background: rgba(23, 105, 210, .1); font-size: 19px; }
        .summary-detail { margin-top: 15px; color: var(--dms-muted); font-size: 10px; font-weight: 600; }

        .dashboard-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(280px, .8fr); gap: 20px; margin-top: 20px; }
        .section-card { padding: 22px; }
        .section-header { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 14px; padding-bottom: 15px; border-bottom: 1px solid var(--dms-line); }
        .section-header h3 { overflow: hidden; margin: 0; color: var(--dms-brand); font-size: 15px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .section-header p { margin: 5px 0 0; color: var(--dms-muted); font-size: 11px; }
        /* Ruang antara garisan tajuk panel dan kandungan pertama di bawahnya */
        .section-header + .px-4, .section-header + .meta-list { padding-top: 16px; }
        .outline-button { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: 7px; padding: 7px 14px; border: 1px solid var(--dms-line); border-radius: 9px; color: var(--dms-text); background: rgba(255, 255, 255, .72); font-size: 12px; font-weight: 700; cursor: pointer; }
        .outline-button:hover { border-color: rgba(23, 105, 210, .35); color: var(--dms-primary); background: #fff; }
        /* Medan fail BM (lihat skrip di hujung halaman) */
        .pilih-fail { position: relative; display: flex; min-width: 0; align-items: center; gap: 12px; cursor: pointer; }
        .pilih-fail-input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .pilih-fail-butang { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 6px; padding: 5px 12px; border: 1px solid var(--dms-line); border-radius: 7px; color: var(--dms-brand); background: #fff; font-size: 12px; font-weight: 700; }
        .pilih-fail:hover .pilih-fail-butang { border-color: rgba(23, 105, 210, .35); color: var(--dms-primary); }
        .pilih-fail:focus-within { border-color: var(--dms-primary); box-shadow: 0 0 0 3px rgba(23, 105, 210, .15); }
        .pilih-fail-nama { overflow: hidden; min-width: 0; color: var(--dms-muted); font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
        .pilih-fail.ada-fail .pilih-fail-nama { color: var(--dms-text); font-weight: 600; }
        .pilih-fail-baris { display: flex; align-items: stretch; gap: 8px; }
        .pilih-fail-baris .pilih-fail { flex: 1 1 auto; }
        .butang-kamera { flex: 0 0 auto; }
        /* Modal kamera */
        .kamera-kandungan { border: 0; border-radius: 14px; }
        .kamera-kandungan .modal-title { color: var(--dms-brand); font-size: 16px; font-weight: 800; }
        .kamera-paparan { position: relative; display: grid; min-height: 240px; place-items: center; overflow: hidden; border-radius: 12px; background: #0f1b2a; }
        .kamera-paparan video { display: block; width: 100%; max-height: 62vh; object-fit: contain; }
        .kamera-mesej { padding: 24px; color: #e8eef6; font-size: 13px; text-align: center; }
        .kamera-gambar { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .kamera-item { position: relative; width: 72px; height: 72px; overflow: hidden; border: 1px solid var(--dms-line); border-radius: 8px; }
        .kamera-item img { width: 100%; height: 100%; object-fit: cover; }
        .kamera-buang { position: absolute; top: 2px; right: 2px; display: grid; width: 22px; height: 22px; place-items: center; padding: 0; border: 0; border-radius: 50%; color: #fff; background: rgba(0, 0, 0, .6); }
        .kamera-butang { flex-wrap: wrap; gap: 8px; }
        /* Paginasi (templat Bootstrap 5) ikut warna sistem */
        .pagination { --bs-pagination-font-size: 13px; --bs-pagination-color: var(--dms-primary); --bs-pagination-active-bg: var(--dms-primary); --bs-pagination-active-border-color: var(--dms-primary); --bs-pagination-border-color: var(--dms-line); margin-bottom: 0; }
        nav[role="navigation"] p.small { margin-bottom: 0; color: var(--dms-muted); font-size: 12px; }
        /* Butang utama: saiz fon & tinggi seragam di semua halaman (gaya warna dalam setiap view) */
        .save-btn { display: inline-flex; min-height: 40px; align-items: center; justify-content: center; gap: 4px; font-size: 13px; }

        .document-list { margin: 0; padding: 0; list-style: none; }
        .document-item { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--dms-line); }
        .document-item:last-child { border-bottom: 0; padding-bottom: 0; }
        .document-icon { display: grid; width: 40px; height: 40px; place-items: center; border-radius: 9px; color: var(--dms-primary); background: rgba(23, 105, 210, .1); }
        .document-copy { min-width: 0; }
        .document-name, .document-meta { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .document-name { font-size: 12px; font-weight: 800; }
        .document-meta { margin-top: 4px; color: var(--dms-muted); font-size: 10px; }
        .document-time { color: var(--dms-muted); font-size: 9px; font-weight: 600; }

        .quick-title { margin: 0; color: var(--dms-brand); font-size: 15px; font-weight: 800; }
        .quick-subtitle { margin: 5px 0 16px; color: var(--dms-muted); font-size: 10px; }
        .quick-list { display: grid; gap: 10px; }
        .quick-link { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 11px; padding: 11px; border: 1px solid var(--dms-line); border-radius: 10px; color: var(--dms-text); background: rgba(255, 255, 255, .55); transition: border-color .2s ease, background .2s ease, transform .2s ease; }
        .quick-link:hover { border-color: rgba(23, 105, 210, .35); color: var(--dms-primary); background: rgba(23, 105, 210, .05); transform: translateX(2px); }
        .quick-icon { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 8px; color: var(--dms-primary); background: rgba(23, 105, 210, .1); }
        .quick-link span { overflow: hidden; font-size: 11px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }

        .sidebar-backdrop { position: fixed; inset: 0; z-index: 1030; display: none; border: 0; background: rgba(24, 63, 104, .38); backdrop-filter: blur(3px); }

        @media (max-width: 1199.98px) {
            .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .dashboard-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.is-open { transform: translateX(0); }
            .sidebar-backdrop.is-visible { display: block; }
            .sidebar-close, .menu-toggle { display: inline-grid; }
            .page { padding-left: 0; }
            .topbar { padding-inline: 20px; }
            .content-wrap { padding-inline: 20px; }
        }

        @media (max-width: 767.98px) {
            /* Jadual senarai: tatal mendatar dengan lebar minimum, bukan baris yang sangat tinggi */
            .table-responsive > .dms-table { min-width: 720px; }
            .table-responsive > .dms-table :is(th, td):not([colspan]) { white-space: nowrap; }
            .table-responsive > .dms-table td.boleh-balut { min-width: 210px; white-space: normal; }
        }

        @media (max-width: 575.98px) {
            .sidebar { width: min(86vw, 300px); }
            .topbar { min-height: 72px; grid-template-columns: minmax(0, 1fr) auto; padding: 10px 14px; }
            .topbar-left { min-width: 0; }
            .top-user { display: none; }
            .page-title { font-size: 17px; }
            .content-wrap { padding: 22px 14px 32px; }
            .content-panel { min-height: calc(100vh - 126px); }
            .page-intro { grid-template-columns: 1fr; }
            .page-intro h2 { font-size: 21px; }
            .date-chip { display: none; }
            .summary-grid, .summary-grid.tiga-kad { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .summary-grid.tiga-kad > :nth-child(3):last-child { grid-column: 1 / -1; }
            .summary-card { padding: 14px; }
            .summary-value { font-size: 24px; }
            .summary-icon { width: 36px; height: 36px; font-size: 16px; }
            .section-card { padding: 17px; }
            .section-header { align-items: start; }
            .section-header h3 { white-space: normal; }
            /* Butang sekunder yang ada ikon jadi ikon sahaja (kecuali .kekal-label);
               butang teks sahaja seperti "Batal"/"Tolak" kekal dengan labelnya */
            .outline-button:has(> .bi):not(.kekal-label) { width: 38px; padding: 0; }
            .outline-button:has(> .bi):not(.kekal-label) span:not(.badge) { display: none; }
            .document-time { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; }
        }
    </style>

    @stack('styles')
</head>
<body>
    <div class="app-shell">
        @php
            // Giliran tindakan untuk loceng topbar & menu sidebar (bukan sistem notifikasi penuh):
            // kelulusan dokumen (PENYOKONG = Dalam Proses, PELULUS = Disokong, Admin = kedua-duanya)
            // dan pendaftaran staf baharu yang menunggu pengesahan (SUPERADMIN/ADMIN).
            // Permohonan sendiri tidak dikira: pemohon tidak boleh bertindak ke atasnya.
            $penggunaSemasa = Auth::user();
            $bolehKelulusan = $penggunaSemasa->hasAnyRole('PENYOKONG', 'PELULUS', 'SUPERADMIN', 'ADMIN');
            $bolehPendaftaran = $penggunaSemasa->hasAnyRole('SUPERADMIN', 'ADMIN');
            $bilKelulusan = 0;
            if ($penggunaSemasa->hasAnyRole('PENYOKONG', 'SUPERADMIN', 'ADMIN')) {
                $bilKelulusan += \App\Support\SkopKelulusan::hadkanGiliranSokongan(
                    \App\Models\Borang::dokumenAktif()->bukanMilik($penggunaSemasa->user_id)->where('status_permohonan', 'Dalam Proses'), $penggunaSemasa
                )->count();
            }
            if ($penggunaSemasa->hasAnyRole('PELULUS', 'SUPERADMIN', 'ADMIN')) {
                $bilKelulusan += \App\Models\Borang::dokumenAktif()->bukanMilik($penggunaSemasa->user_id)->where('status_permohonan', 'Disokong')->count();
            }
            $bilPendaftaran = $bolehPendaftaran
                ? \App\Models\PendaftaranStaf::where('status', \App\Models\PendaftaranStaf::MENUNGGU)->count()
                : 0;
            $bilGiliran = $bilKelulusan + $bilPendaftaran;

            // Notifikasi dalam sistem (P-C), ditapis ikut peranan aktif (App\Support\Notifikasi)
            $bilBelumBaca = \App\Support\Notifikasi::kelihatan($penggunaSemasa, true)->count();
            $notifTerkini = \App\Support\Notifikasi::kelihatan($penggunaSemasa, false, 30)->take(6);
            $labelLoceng = "Notifikasi: {$bilBelumBaca} belum dibaca"
                . ($bilGiliran > 0 ? ", {$bilGiliran} perkara menunggu tindakan anda" : '');
        @endphp
        <button type="button" class="sidebar-backdrop" id="sidebarBackdrop" aria-label="Tutup menu navigasi"></button>

        <aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
            <div class="brand">
                <img src="{{ asset('images/Logo_PKINK_Tulisan_Jawi_Bawah.png') }}" alt="Logo PKINK" style="width: 44px; height: 44px; object-fit: contain; flex: 0 0 auto;">
                <div class="brand-copy">
                    <div class="brand-title">DMS PKINK</div>
                    <div class="brand-subtitle">Sistem Arkib Digital</div>
                </div>
                <button type="button" class="icon-button sidebar-close" id="sidebarClose" aria-label="Tutup menu">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>

            <nav class="nav-area">
                <p class="nav-label">Menu Utama</p>
                <ul class="nav-list">
                    <li>
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>
                            <span>Papan Pemuka</span>
                            @if(request()->routeIs('dashboard'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @if(Auth::user()->hasAnyRole('SUPERADMIN', 'ADMIN', 'KERANI'))
                    <li>
                        <a href="{{ route('jenis-dokumen.index') }}" class="nav-link {{ request()->routeIs('jenis-dokumen.*') ? 'active' : '' }}">
                            <i class="bi bi-folder2-open" aria-hidden="true"></i>
                            <span>Kategori Dokumen</span>
                            @if(request()->routeIs('jenis-dokumen.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if(Auth::user()->can('viewAny', \App\Models\Dokumen::class))
                    <li>
                        <a href="{{ route('dokumen.index') }}" class="nav-link {{ request()->routeIs('dokumen.*') ? 'active' : '' }}">
                            <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i>
                            <span>Dokumen</span>
                            @if(request()->routeIs('dokumen.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if(Auth::user()->hasAnyRole('SUPERADMIN', 'ADMIN', 'KERANI'))
                    <li>
                        <a href="{{ route('imbas-arkib.create') }}" class="nav-link {{ request()->routeIs('imbas-arkib.*') ? 'active' : '' }}">
                            <i class="bi bi-magic" aria-hidden="true"></i>
                            <span>Imbas &amp; Arkib</span>
                            @if(request()->routeIs('imbas-arkib.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if(Auth::user()->can('create', \App\Models\Dokumen::class))
                    <li>
                        {{-- Isi Borang: pintu masuk Isi Digital & Imbas & Hantar --}}
                        <a href="{{ route('isi-borang.index') }}" class="nav-link {{ request()->routeIs('isi-borang.*', 'imbas-hantar.*') ? 'active' : '' }}">
                            <i class="bi bi-ui-checks-grid" aria-hidden="true"></i>
                            <span>Isi Borang</span>
                            @if(request()->routeIs('isi-borang.*', 'imbas-hantar.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if(Auth::user()->hasAnyRole('SUPERADMIN', 'ADMIN', 'KERANI'))
                    <li>
                        <a href="{{ route('bahagian.index') }}" class="nav-link {{ request()->routeIs('bahagian.*') ? 'active' : '' }}">
                            <i class="bi bi-building" aria-hidden="true"></i>
                            <span>Pengurusan Bahagian</span>
                            @if(request()->routeIs('bahagian.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if(Auth::user()->hasAnyRole('SUPERADMIN', 'ADMIN'))
                    <li>
                        <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <i class="bi bi-people-fill" aria-hidden="true"></i>
                            <span>Pengurusan Kakitangan</span>
                            @if(request()->routeIs('users.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if($bolehPendaftaran)
                    <li>
                        <a href="{{ route('pendaftaran.index') }}" class="nav-link {{ request()->routeIs('pendaftaran.*') ? 'active' : '' }}">
                            <i class="bi bi-person-check" aria-hidden="true"></i>
                            <span>Pengesahan Pendaftaran</span>
                            @if($bilPendaftaran > 0)
                                <span class="nav-bil" title="{{ $bilPendaftaran }} permohonan menunggu">{{ $bilPendaftaran > 99 ? '99+' : $bilPendaftaran }}</span>
                            @elseif(request()->routeIs('pendaftaran.*'))
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            @endif
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('tong-sampah.index') }}" class="nav-link {{ request()->routeIs('tong-sampah.*') ? 'active' : '' }}">
                            <i class="bi bi-trash3" aria-hidden="true"></i>
                            <span>Tong Sampah</span>
                            @if(request()->routeIs('tong-sampah.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('status-sistem.index') }}" class="nav-link {{ request()->routeIs('status-sistem.*') ? 'active' : '' }}">
                            <i class="bi bi-activity" aria-hidden="true"></i>
                            <span>Status Sistem</span>
                            @if(request()->routeIs('status-sistem.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if(Auth::user()->hasAnyRole('SUPERADMIN', 'AUDIT'))
                    <li>
                        <a href="{{ route('log-audit.index') }}" class="nav-link {{ request()->routeIs('log-audit.*') ? 'active' : '' }}">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            <span>Log Audit</span>
                            @if(request()->routeIs('log-audit.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                    @if(Auth::user()->hasAnyRole('SUPERADMIN', 'ADMIN', 'PENYOKONG', 'PELULUS'))
                    <li>
                        <a href="{{ route('kelulusan.index') }}" class="nav-link {{ request()->routeIs('kelulusan.*') ? 'active' : '' }}">
                            <i class="bi bi-patch-check" aria-hidden="true"></i>
                            <span>Kelulusan</span>
                            @if(request()->routeIs('kelulusan.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('delegasi.index') }}" class="nav-link {{ request()->routeIs('delegasi.*') ? 'active' : '' }}">
                            <i class="bi bi-person-check" aria-hidden="true"></i>
                            <span>Delegasi</span>
                            @if(request()->routeIs('delegasi.*'))<i class="bi bi-chevron-right" aria-hidden="true"></i>@endif
                        </a>
                    </li>
                    @endif
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="{{ route('profil.index') }}" class="sidebar-user pautan-profil {{ request()->routeIs('profil.*') ? 'aktif' : '' }}" title="Profil Saya">
                    <div class="avatar" aria-hidden="true">
                        {{ strtoupper(substr(Auth::user()->nama_staff, 0, 1)) }}
                    </div>
                    <div class="user-copy">
                        <div class="user-name">{{ Auth::user()->nama_staff }}</div>
                        @php
                            // Peranan dimiliki & peranan aktif (tukar dari menu nama di topbar)
                            $senaraiPeranan = Auth::user()->roles->sortBy('nama_roles');
                            $kodAktif = Auth::user()->perananAktif();
                            $labelPeranan = $kodAktif
                                ? $senaraiPeranan->firstWhere('kod_roles', $kodAktif)?->nama_roles
                                : ($senaraiPeranan->count() > 1 ? 'Semua peranan (' . $senaraiPeranan->count() . ')' : ($senaraiPeranan->first()?->nama_roles ?? 'Tiada peranan'));
                        @endphp
                        <div class="user-role">{{ $labelPeranan }}</div>
                    </div>
                </a>
            </div>
        </aside>

        <div class="page">
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="icon-button menu-toggle" id="menuToggle" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                    <div class="page-heading">
                        <div class="page-eyebrow">Sistem Arkib Digital</div>
                        <h1 class="page-title">@yield('title', 'Papan Pemuka')</h1>
                    </div>
                </div>

                <div class="topbar-actions">
                    {{-- Loceng notifikasi (semua peranan): notifikasi terkini + giliran tindakan; dikemas kini setiap 30 s --}}
                    <div class="dropdown" id="loceng" data-ringkasan="{{ route('notifikasi.ringkasan') }}">
                        <button type="button" class="icon-button notification" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="{{ $labelLoceng }}" title="{{ $labelLoceng }}">
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            <span class="notification-badge" id="locengBadge" aria-hidden="true" @if(!$bilBelumBaca) hidden @endif>{{ $bilBelumBaca > 99 ? '99+' : $bilBelumBaca }}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end loceng-menu">
                            <div class="loceng-kepala">
                                <strong>Notifikasi</strong>
                                <form method="POST" action="{{ route('notifikasi.baca-semua') }}" class="m-0">
                                    @csrf
                                    <button type="submit" class="loceng-pautan" id="locengBacaSemua" @if(!$bilBelumBaca) hidden @endif>Tandakan semua dibaca</button>
                                </form>
                            </div>
                            {{-- Notifikasi peranti (Web Push): keadaan & butang diisi oleh skrip --}}
                            <div class="loceng-peranti" id="locengPeranti" data-langgan="{{ route('notifikasi.langgan') }}" hidden>
                                <i class="bi bi-phone-vibrate" aria-hidden="true"></i>
                                <span id="locengPerantiTeks">Terima notifikasi di peranti ini</span>
                                <button type="button" class="loceng-pautan" id="locengPerantiButang">Hidupkan</button>
                            </div>
                            <div class="loceng-senarai" id="locengSenarai">
                                @forelse($notifTerkini as $n)
                                    <a href="{{ route('notifikasi.buka', $n->id) }}" class="loceng-item {{ $n->read_at ? '' : 'belum-baca' }}">
                                        <span class="loceng-ikon warna-{{ $n->data['warna'] ?? 'biru' }}"><i class="bi {{ $n->data['ikon'] ?? 'bi-bell' }}" aria-hidden="true"></i></span>
                                        <span class="loceng-teks">
                                            <strong>{{ $n->data['tajuk'] ?? 'Notifikasi' }}</strong>
                                            <span>{{ $n->data['mesej'] ?? '' }}</span>
                                            <small>{{ $n->created_at?->diffForHumans() }}</small>
                                        </span>
                                    </a>
                                @empty
                                    <div class="loceng-kosong"><i class="bi bi-bell-slash" aria-hidden="true"></i> Tiada notifikasi.</div>
                                @endforelse
                            </div>
                            @if($bolehKelulusan)
                                <div class="loceng-giliran">
                                    <div class="dropdown-header">Menunggu tindakan anda</div>
                                    <a class="dropdown-item" href="{{ route('kelulusan.index') }}">
                                        <i class="bi bi-patch-check" aria-hidden="true"></i>
                                        <span>Kelulusan dokumen</span>
                                        <span class="loceng-bil {{ $bilKelulusan > 0 ? 'ada' : '' }}">{{ $bilKelulusan }}</span>
                                    </a>
                                    @if($bolehPendaftaran)
                                        <a class="dropdown-item" href="{{ route('pendaftaran.index') }}">
                                            <i class="bi bi-person-check" aria-hidden="true"></i>
                                            <span>Pendaftaran staf</span>
                                            <span class="loceng-bil {{ $bilPendaftaran > 0 ? 'ada' : '' }}">{{ $bilPendaftaran }}</span>
                                        </a>
                                    @endif
                                </div>
                            @endif
                            <a href="{{ route('notifikasi.index') }}" class="loceng-kaki">Lihat semua notifikasi <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                    {{-- Menu pengguna: tukar peranan aktif (berbilang peranan) + profil --}}
                    <div class="dropdown">
                        <button type="button" class="pengguna-butang" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu pengguna: {{ Auth::user()->nama_staff }}, {{ $labelPeranan }}">
                            <span class="top-user">
                                <strong>{{ Auth::user()->nama_staff }}</strong>
                                <small>@if($kodAktif)<i class="bi bi-person-badge" aria-hidden="true"></i> @endif{{ $labelPeranan }}</small>
                            </span>
                            <span class="avatar avatar-kecil {{ $kodAktif ? 'avatar-aktif' : '' }}" aria-hidden="true">{{ strtoupper(substr(Auth::user()->nama_staff, 0, 1)) }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end pengguna-menu">
                            <li class="pengguna-menu-kepala">
                                <strong>{{ Auth::user()->nama_staff }}</strong>
                                <small>{{ Auth::user()->email }}</small>
                            </li>
                            @if($senaraiPeranan->count() > 1)
                                <li><hr class="dropdown-divider"></li>
                                <li class="dropdown-header">Tukar peranan</li>
                                <li>
                                    <button type="submit" form="formTukarPeranan" name="peranan" value="" class="dropdown-item {{ $kodAktif ? '' : 'active' }}" @if(!$kodAktif) aria-current="true" @endif>
                                        <i class="bi bi-layers" aria-hidden="true"></i>
                                        <span>Semua peranan</span>
                                        @if(!$kodAktif)<i class="bi bi-check2" aria-hidden="true"></i>@endif
                                    </button>
                                </li>
                                @foreach($senaraiPeranan as $peranan)
                                    <li>
                                        <button type="submit" form="formTukarPeranan" name="peranan" value="{{ $peranan->kod_roles }}" class="dropdown-item {{ $kodAktif === $peranan->kod_roles ? 'active' : '' }}" @if($kodAktif === $peranan->kod_roles) aria-current="true" @endif>
                                            <i class="bi bi-person-badge" aria-hidden="true"></i>
                                            <span>{{ $peranan->nama_roles }}</span>
                                            @if($kodAktif === $peranan->kod_roles)<i class="bi bi-check2" aria-hidden="true"></i>@endif
                                        </button>
                                    </li>
                                @endforeach
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="{{ route('profil.index') }}">
                                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                                    <span>Profil Saya</span>
                                </a>
                            </li>
                        </ul>
                        <form method="POST" action="{{ route('peranan.tukar') }}" id="formTukarPeranan" class="d-none">@csrf</form>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="logout-form">
                        @csrf
                        <button type="submit" class="icon-button" aria-label="Log keluar" title="Log keluar">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </header>

            <div class="content-wrap">
                <main class="content-panel">
                    @yield('content')
                </main>
            </div>
        </div>
    </div>

    {{-- Modal kamera (butang "Kamera" pada medan fail; lihat window.dmsKamera) --}}
    <div class="modal fade" id="modalKamera" tabindex="-1" aria-labelledby="modalKameraTajuk" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
            <div class="modal-content kamera-kandungan">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalKameraTajuk"><i class="bi bi-camera me-2" aria-hidden="true"></i>Ambil Gambar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="kamera-paparan">
                        <video id="kameraVideo" autoplay playsinline muted></video>
                        <div id="kameraMesej" class="kamera-mesej" role="status" hidden></div>
                    </div>
                    <div id="kameraGambar" class="kamera-gambar" aria-live="polite"></div>
                    <div class="dms-hint mt-2">Letakkan dokumen di permukaan rata, pastikan cukup cahaya dan seluruh halaman kelihatan.</div>
                </div>
                <div class="modal-footer kamera-butang">
                    <button type="button" class="outline-button kekal-label" id="kameraTukar" hidden><i class="bi bi-arrow-repeat" aria-hidden="true"></i> <span>Tukar Kamera</span></button>
                    <button type="button" class="outline-button kekal-label" id="kameraApl"><i class="bi bi-phone" aria-hidden="true"></i> <span>Aplikasi Kamera</span></button>
                    <button type="button" class="outline-button kekal-label" id="kameraAmbil"><i class="bi bi-camera-fill" aria-hidden="true"></i> <span>Ambil</span></button>
                    <button type="button" class="btn text-white save-btn" id="kameraSiap" disabled><i class="bi bi-check2" aria-hidden="true"></i> <span>Guna Gambar</span></button>
                </div>
            </div>
        </div>
    </div>
    <input type="file" id="kameraFallback" accept="image/*" capture="environment" hidden>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script>
        (() => {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('menuToggle');
            const closeButton = document.getElementById('sidebarClose');
            const backdrop = document.getElementById('sidebarBackdrop');

            if (!sidebar || !toggle || !closeButton || !backdrop) return;

            const setMenu = (open) => {
                sidebar.classList.toggle('is-open', open);
                backdrop.classList.toggle('is-visible', open);
                toggle.setAttribute('aria-expanded', String(open));
                document.body.style.overflow = open ? 'hidden' : '';
            };

            toggle.addEventListener('click', () => setMenu(true));
            closeButton.addEventListener('click', () => setMenu(false));
            backdrop.addEventListener('click', () => setMenu(false));

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setMenu(false);
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 992) setMenu(false);
            });
        })();

        // Notifikasi (P-C): semak setiap 30 s; popup dalam halaman bila notifikasi baharu masuk.
        // Notifikasi yang sudah dilihat diingat dalam sessionStorage supaya popup tidak berulang.
        (() => {
            const loceng = document.getElementById('loceng');
            if (!loceng) return;
            const badge = document.getElementById('locengBadge');
            const senarai = document.getElementById('locengSenarai');
            const bacaSemua = document.getElementById('locengBacaSemua');
            const KUNCI = 'dmsNotifDilihat';
            let dilihat = null;
            try { dilihat = sessionStorage.getItem(KUNCI) !== null ? new Set(JSON.parse(sessionStorage.getItem(KUNCI))) : null; } catch (e) { dilihat = null; }
            const kaliPertama = dilihat === null; // halaman pertama sesi: jangan popup notifikasi lama
            dilihat = dilihat || new Set();
            const simpan = () => { try { sessionStorage.setItem(KUNCI, JSON.stringify([...dilihat].slice(-200))); } catch (e) { /* storan disekat */ } };

            const bekas = document.createElement('div');
            bekas.className = 'notif-toast-bekas';
            bekas.setAttribute('aria-live', 'polite');
            document.body.append(bekas);

            const elemen = (tag, kelas, teks) => { const e = document.createElement(tag); if (kelas) e.className = kelas; if (teks !== undefined) e.textContent = teks; return e; };
            const ikon = (n) => { const s = elemen('span', `loceng-ikon warna-${n.warna}`); const i = elemen('i', `bi ${n.ikon}`); i.setAttribute('aria-hidden', 'true'); s.append(i); return s; };

            const popup = (n) => {
                const a = elemen('a', 'notif-toast');
                a.href = n.url;
                const teks = elemen('span');
                teks.append(elemen('strong', '', n.tajuk), elemen('span', '', n.mesej));
                const tutup = elemen('button', '', '×');
                tutup.type = 'button';
                tutup.setAttribute('aria-label', 'Tutup notifikasi');
                tutup.addEventListener('click', (e) => { e.preventDefault(); a.remove(); });
                a.append(ikon(n), teks, tutup);
                bekas.append(a);
                setTimeout(() => a.remove(), 8000);
                window.dispatchEvent(new CustomEvent('dms:notifikasi-baharu', { detail: n }));
            };

            const lukis = (data) => {
                badge.hidden = !data.belum_dibaca;
                badge.textContent = data.belum_dibaca > 99 ? '99+' : data.belum_dibaca;
                bacaSemua.hidden = !data.belum_dibaca;
                senarai.replaceChildren(...(data.terkini.length ? data.terkini.map((n) => {
                    const a = elemen('a', `loceng-item ${n.dibaca ? '' : 'belum-baca'}`);
                    a.href = n.url;
                    const teks = elemen('span', 'loceng-teks');
                    teks.append(elemen('strong', '', n.tajuk), elemen('span', '', n.mesej), elemen('small', '', n.masa));
                    a.append(ikon(n), teks);
                    return a;
                }) : [Object.assign(elemen('div', 'loceng-kosong', ' Tiada notifikasi.'))]));
            };

            let pertama = true;
            const semak = async () => {
                try {
                    const r = await fetch(loceng.dataset.ringkasan, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!r.ok) return;
                    const data = await r.json();
                    lukis(data);
                    data.terkini.filter((n) => !n.dibaca && !dilihat.has(n.id)).forEach((n) => {
                        dilihat.add(n.id);
                        if (!(pertama && kaliPertama)) popup(n);
                    });
                    simpan();
                    pertama = false;
                } catch (e) { /* rangkaian terputus: cuba lagi kemudian */ }
            };

            semak();
            setInterval(() => { if (!document.hidden) semak(); }, 30000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) semak(); });
        })();

        // Notifikasi peranti (P-D, Web Push): service worker public/sw.js memaparkan notifikasi
        // sistem pengendalian walaupun tab ditutup. Perlukan HTTPS atau localhost; di iPhone
        // hanya selepas "Tambah ke Skrin Utama". Langganan diselaraskan setiap kali halaman dibuka
        // (peranti dikongsi: pengguna yang log masuk menjadi pemilik langganan) dan dibuang semasa log keluar.
        (async () => {
            const baris = document.getElementById('locengPeranti');
            const kunciAwam = document.querySelector('meta[name="vapid-public-key"]')?.content;
            if (!baris || !kunciAwam) return;
            const teks = document.getElementById('locengPerantiTeks');
            const butang = document.getElementById('locengPerantiButang');
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const url = baris.dataset.langgan;
            const papar = (mesej, labelButang, hidup) => {
                baris.hidden = false;
                teks.textContent = mesej;
                butang.hidden = !labelButang;
                butang.textContent = labelButang || '';
                baris.classList.toggle('mati', !hidup);
            };

            const iPhone = /iPhone|iPad|iPod/.test(navigator.userAgent);
            if (!window.isSecureContext || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                papar(iPhone ? 'iPhone: tambah laman ini ke Skrin Utama untuk notifikasi peranti' : 'Notifikasi peranti memerlukan sambungan HTTPS', null, false);
                return;
            }

            const kunci = (b64) => {
                const pad = '='.repeat((4 - (b64.length % 4)) % 4);
                const mentah = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
                return Uint8Array.from([...mentah].map((c) => c.charCodeAt(0)));
            };
            const hantar = (kaedah, badan) => fetch(url, {
                method: kaedah, credentials: 'same-origin', keepalive: true,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(badan),
            });
            // Simpan langganan di pelayan; gagal (cth. 419/422) = langganan pelayar dibuang semula
            const simpan = async (langganan) => {
                const json = langganan.toJSON();
                const r = await hantar('POST', { endpoint: json.endpoint, keys: json.keys, contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0] });
                if (!r.ok) {
                    await langganan.unsubscribe().catch(() => {});
                    throw new Error(`Pelayan menolak langganan (kod ${r.status})`);
                }
            };

            let reg;
            try { reg = await navigator.serviceWorker.register('/sw.js'); await navigator.serviceWorker.ready; } catch (e) { return; }

            const kemaskini = async () => {
                const langganan = await reg.pushManager.getSubscription();
                if (Notification.permission === 'denied') {
                    papar('Notifikasi disekat oleh pelayar. Benarkan notifikasi untuk laman ini dalam tetapan pelayar.', null, false);
                } else if (langganan && Notification.permission === 'granted') {
                    papar('Notifikasi peranti dihidupkan', 'Matikan', true);
                } else {
                    papar('Terima notifikasi di peranti ini', 'Hidupkan', false);
                }
                return langganan;
            };

            butang.addEventListener('click', async (e) => {
                e.stopPropagation();
                butang.disabled = true;
                try {
                    const sedia = await reg.pushManager.getSubscription();
                    if (sedia) {
                        await hantar('DELETE', { endpoint: sedia.endpoint });
                        await sedia.unsubscribe();
                    } else if (await Notification.requestPermission() === 'granted') {
                        const baharu = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: kunci(kunciAwam) });
                        await simpan(baharu);
                    }
                    butang.disabled = false;
                    await kemaskini();
                } catch (err) {
                    console.warn('Notifikasi peranti:', err);
                    butang.disabled = false;
                    papar(`Notifikasi peranti tidak dapat dihidupkan: ${err.message || err}`, 'Cuba lagi', false);
                }
            });

            // Selaraskan langganan sedia ada dengan pengguna yang sedang log masuk
            const sedia = await kemaskini();
            if (sedia && Notification.permission === 'granted') {
                simpan(sedia).catch((err) => {
                    console.warn('Notifikasi peranti:', err);
                    papar(`Notifikasi peranti tidak aktif: ${err.message || err}`, 'Cuba lagi', false);
                });
            }

            // Log keluar: buang langganan peranti ini daripada akaun (peranti dikongsi semasa demo)
            document.querySelectorAll('form.logout-form').forEach((borang) => borang.addEventListener('submit', (e) => {
                if (borang.dataset.selesai) return;
                e.preventDefault(); // mesti segerak; log keluar diteruskan selepas langganan dibuang
                borang.dataset.selesai = '1';
                reg.pushManager.getSubscription()
                    .then((langganan) => langganan ? hantar('DELETE', { endpoint: langganan.endpoint }) : null)
                    .catch(() => {})
                    .finally(() => borang.submit());
            }));
        })();

        // Kamera semasa muat naik: kamera langsung (getUserMedia: kamera belakang telefon / webcam
        // laptop, perlukan HTTPS atau localhost) atau aplikasi kamera peranti (input capture).
        // Gambar menjadi fail JPG dalam medan fail asal; medan berbilang menambah, medan tunggal menggantikan.
        window.dmsKamera = (() => {
            const modalEl = document.getElementById('modalKamera');
            if (!modalEl || !window.bootstrap) return null;
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            const video = document.getElementById('kameraVideo');
            const mesej = document.getElementById('kameraMesej');
            const senaraiGambar = document.getElementById('kameraGambar');
            const btnAmbil = document.getElementById('kameraAmbil');
            const btnSiap = document.getElementById('kameraSiap');
            const btnTukar = document.getElementById('kameraTukar');
            const btnApl = document.getElementById('kameraApl');
            const inputApl = document.getElementById('kameraFallback');
            let sasaran = null, aliran = null, gambar = [], senaraiKamera = [], indeksKamera = -1;

            const tambahFail = (input, fail) => {
                const dt = new DataTransfer();
                if (input.multiple) Array.from(input.files).forEach((f) => dt.items.add(f));
                (input.multiple ? fail : fail.slice(-1)).forEach((f) => dt.items.add(f));
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            };
            const henti = () => { aliran?.getTracks().forEach((t) => t.stop()); aliran = null; video.srcObject = null; };
            const tunjukMesej = (teks) => { mesej.textContent = teks; mesej.hidden = !teks; video.hidden = !!teks; btnAmbil.disabled = !!teks; };
            const lukisGambar = () => {
                senaraiGambar.innerHTML = '';
                gambar.forEach((f, i) => {
                    const item = document.createElement('div');
                    item.className = 'kamera-item';
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(f);
                    img.alt = `Gambar ${i + 1}`;
                    const buang = document.createElement('button');
                    buang.type = 'button';
                    buang.className = 'kamera-buang';
                    buang.innerHTML = '<i class="bi bi-x" aria-hidden="true"></i>';
                    buang.setAttribute('aria-label', `Buang gambar ${i + 1}`);
                    buang.addEventListener('click', () => { gambar.splice(i, 1); lukisGambar(); });
                    item.append(img, buang);
                    senaraiGambar.append(item);
                });
                btnSiap.disabled = gambar.length === 0;
                btnSiap.querySelector('span').textContent = gambar.length > 1 ? `Guna ${gambar.length} Gambar` : 'Guna Gambar';
            };
            const mula = async () => {
                henti();
                tunjukMesej('');
                if (!navigator.mediaDevices?.getUserMedia || !window.isSecureContext) {
                    tunjukMesej('Kamera langsung memerlukan sambungan HTTPS. Tekan "Aplikasi Kamera" untuk mengambil gambar dengan kamera peranti.');
                    return;
                }
                try {
                    const pilihan = senaraiKamera[indeksKamera]
                        ? { deviceId: { exact: senaraiKamera[indeksKamera].deviceId } }
                        : { facingMode: { ideal: 'environment' } };
                    aliran = await navigator.mediaDevices.getUserMedia({ video: { ...pilihan, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false });
                    video.srcObject = aliran;
                    senaraiKamera = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput');
                    btnTukar.hidden = senaraiKamera.length < 2;
                } catch (e) {
                    tunjukMesej(e.name === 'NotAllowedError'
                        ? 'Akses kamera ditolak. Benarkan kamera untuk laman ini dalam tetapan pelayar, atau tekan "Aplikasi Kamera".'
                        : 'Kamera tidak dapat dibuka pada peranti ini. Tekan "Aplikasi Kamera" atau guna Pilih Fail.');
                }
            };
            const namaGambar = () => {
                const t = new Date(), d = (n) => String(n).padStart(2, '0');
                return `Gambar_${t.getFullYear()}${d(t.getMonth() + 1)}${d(t.getDate())}_${d(t.getHours())}${d(t.getMinutes())}${d(t.getSeconds())}_${gambar.length + 1}.jpg`;
            };
            const selesai = () => { if (gambar.length) tambahFail(sasaran, gambar.slice()); modal.hide(); };

            btnAmbil.addEventListener('click', () => {
                if (!video.videoWidth) return;
                const kanvas = document.createElement('canvas');
                kanvas.width = video.videoWidth;
                kanvas.height = video.videoHeight;
                kanvas.getContext('2d').drawImage(video, 0, 0);
                kanvas.toBlob((blob) => {
                    gambar.push(new File([blob], namaGambar(), { type: 'image/jpeg', lastModified: Date.now() }));
                    lukisGambar();
                    if (!sasaran.multiple) selesai(); // medan satu fail (Imbas): terus guna gambar
                }, 'image/jpeg', 0.9);
            });
            btnSiap.addEventListener('click', selesai);
            btnTukar.addEventListener('click', () => { indeksKamera = (indeksKamera + 1) % senaraiKamera.length; mula(); });
            btnApl.addEventListener('click', () => { inputApl.multiple = !!sasaran?.multiple; inputApl.value = ''; inputApl.click(); });
            inputApl.addEventListener('change', () => {
                if (inputApl.files.length) { tambahFail(sasaran, Array.from(inputApl.files)); modal.hide(); }
            });
            modalEl.addEventListener('hidden.bs.modal', () => { henti(); gambar = []; lukisGambar(); });

            return {
                buka(input) { sasaran = input; gambar = []; lukisGambar(); modal.show(); mula(); },
                tambahFail,
            };
        })();

        // Medan fail dalam BM: teks asal pelayar ("Choose Files / No file chosen") ikut bahasa
        // pelayar. Input sebenar kekal (lutsinar, menutupi kotak) supaya klik, seret-lepas,
        // papan kekunci dan validasi "required" masih berfungsi.
        document.querySelectorAll('.content-panel input[type="file"]').forEach((input) => {
            const kotak = document.createElement('div');
            kotak.className = 'pilih-fail ' + input.className;
            input.className = 'pilih-fail-input';
            input.parentNode.insertBefore(kotak, input);

            const butang = document.createElement('span');
            butang.className = 'pilih-fail-butang';
            butang.innerHTML = '<i class="bi bi-paperclip" aria-hidden="true"></i> Pilih Fail';
            const nama = document.createElement('span');
            nama.className = 'pilih-fail-nama';
            kotak.append(input, butang, nama);

            // Medan yang menerima imej: butang "Kamera" di sebelah kotak (modal kamera di bawah)
            if (window.dmsKamera && /image|jpe?g|png/i.test(input.accept || '')) {
                const baris = document.createElement('div');
                baris.className = 'pilih-fail-baris';
                kotak.parentNode.insertBefore(baris, kotak);
                const kamera = document.createElement('button');
                kamera.type = 'button';
                kamera.className = 'outline-button kekal-label butang-kamera';
                kamera.innerHTML = '<i class="bi bi-camera" aria-hidden="true"></i> <span>Kamera</span>';
                kamera.setAttribute('aria-label', 'Ambil gambar dengan kamera');
                kamera.addEventListener('click', () => window.dmsKamera.buka(input));
                baris.append(kotak, kamera);
            }

            const kemaskini = () => {
                const fail = Array.from(input.files);
                nama.textContent = fail.length === 0 ? 'Tiada fail dipilih'
                    : fail.length === 1 ? fail[0].name
                    : `${fail.length} fail dipilih: ${fail.map((f) => f.name).join(', ')}`;
                kotak.classList.toggle('ada-fail', fail.length > 0);
            };
            input.addEventListener('change', kemaskini);
            kemaskini();
        });
    </script>

    @stack('scripts')
</body>
</html>