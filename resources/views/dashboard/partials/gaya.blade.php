{{-- Gaya papan pemuka (awalan pp-), dikongsi semua bahagian peranan. Responsif: desktop, tablet, telefon. --}}
<style>
    /* ---- Kepala & tindakan pantas ---- */
    .pp-kepala { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px 18px; margin-bottom: 18px; }
    .pp-kepala h2 { margin: 0; color: var(--dms-brand); font-size: 22px; font-weight: 800; }
    .pp-peranan { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .pp-cip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; background: rgba(23, 105, 210, .08); color: var(--dms-primary); font-size: 11px; font-weight: 700; }
    .pp-cip-kelabu { background: rgba(104, 121, 141, .1); color: var(--dms-muted); }

    .pp-pantas { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 24px; }
    .pp-pantas a {
        display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border: 1px solid var(--dms-line);
        border-radius: 10px; background: var(--dms-surface-strong); color: var(--dms-text); font-size: 13px; font-weight: 700;
        transition: border-color .2s ease, transform .2s ease;
    }
    .pp-pantas a:hover { border-color: var(--dms-primary); color: var(--dms-primary); transform: translateY(-1px); }
    .pp-pantas a.utama { border-color: transparent; background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary)); color: #fff; }

    /* ---- Bahagian peranan ---- */
    .pp-bahagian { margin-bottom: 28px; }
    .pp-bahagian-tajuk { display: flex; align-items: center; gap: 8px; margin: 0 0 12px; color: var(--dms-muted); font-size: 12px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; }

    /* ---- Kad KPI padat: 4 lajur desktop, 2 lajur tablet/telefon ---- */
    .pp-kpi { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    @media (min-width: 1200px) { .pp-kpi { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .pp-kpi.tiga { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .pp-kad { display: flex; align-items: center; gap: 12px; padding: 14px 16px; color: inherit; text-decoration: none; transition: box-shadow .2s ease, transform .2s ease; }
    a.pp-kad:hover { box-shadow: var(--dms-shadow); transform: translateY(-2px); color: inherit; }
    .pp-kad.penting { box-shadow: inset 3px 0 0 var(--dms-danger); }
    .pp-kad-ikon { display: grid; flex: 0 0 40px; height: 40px; place-items: center; border-radius: 10px; font-size: 18px; }
    .pp-kad-teks { min-width: 0; }
    .pp-kad-nilai { color: var(--dms-brand); font-size: 24px; font-weight: 800; line-height: 1.1; }
    .pp-kad-nilai small { margin-left: 3px; color: var(--dms-muted); font-size: 12px; font-weight: 700; }
    .pp-kad-label { margin-top: 2px; color: var(--dms-text); font-size: 12px; font-weight: 700; }
    .pp-kad-nota { margin-top: 1px; color: var(--dms-muted); font-size: 11px; }
    .warna-biru { background: rgba(23, 105, 210, .1); color: var(--dms-primary); }
    .warna-hijau { background: rgba(37, 169, 121, .12); color: var(--dms-mint); }
    .warna-kuning { background: rgba(234, 179, 8, .15); color: #b45309; }
    .warna-merah { background: rgba(196, 61, 78, .1); color: var(--dms-danger); }
    .warna-ungu { background: rgba(124, 58, 237, .1); color: #7c3aed; }
    .warna-kelabu { background: rgba(104, 121, 141, .1); color: var(--dms-muted); }

    /* ---- Panel ---- */
    .pp-panel { padding: 0; overflow: hidden; }
    .pp-panel-kepala { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 18px; border-bottom: 1px solid var(--dms-line); }
    .pp-panel-kepala h3 { margin: 0; color: var(--dms-brand); font-size: 15px; font-weight: 800; }
    .pp-panel-kepala a { color: var(--dms-primary); font-size: 12px; font-weight: 700; white-space: nowrap; }

    /* ---- Jadual: menjadi senarai kad pada telefon (tiada skrol ke tepi) ---- */
    .pp-jadual { width: 100%; border-collapse: collapse; font-size: 13px; }
    .pp-jadual th { padding: 10px 18px; border-bottom: 1px solid var(--dms-line); color: var(--dms-muted); font-size: 11px; font-weight: 700; letter-spacing: .4px; text-align: left; text-transform: uppercase; }
    .pp-jadual td { padding: 11px 18px; border-bottom: 1px solid var(--dms-line); vertical-align: middle; }
    .pp-jadual tr:last-child td { border-bottom: 0; }
    .pp-rujukan { display: inline-block; padding: 4px 9px; border-radius: 6px; background: rgba(23, 105, 210, .1); color: var(--dms-primary); font-size: 11.5px; font-weight: 700; white-space: nowrap; }
    .pp-kecil { color: var(--dms-muted); font-size: 11.5px; }
    .pp-dok-nama { display: block; margin-top: 4px; color: var(--dms-text); font-size: 12px; line-height: 1.35; }
    .pp-aksi { display: inline-flex; width: 32px; height: 32px; align-items: center; justify-content: center; border: 1px solid var(--dms-line); border-radius: 8px; background: var(--dms-surface-strong); color: var(--dms-primary); }
    .pp-aksi:hover { border-color: var(--dms-primary); background: rgba(23, 105, 210, .08); }
    @media (max-width: 576px) {
        .pp-jadual thead { display: none; }
        .pp-jadual tr { display: block; padding: 12px 16px; border-bottom: 1px solid var(--dms-line); }
        .pp-jadual tr:last-child { border-bottom: 0; }
        .pp-jadual td { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; border: 0; text-align: right; }
        .pp-jadual td::before { content: attr(data-label); flex-shrink: 0; color: var(--dms-muted); font-size: 11px; font-weight: 700; text-align: left; }
        .pp-jadual td.pp-sel-dokumen { display: block; padding-bottom: 6px; text-align: left; }
        .pp-jadual td.pp-sel-dokumen::before { content: none; }
        .pp-jadual td.pp-sel-tindakan { justify-content: flex-end; padding-top: 6px; }
        .pp-jadual td.pp-sel-tindakan::before { content: none; }
    }

    /* ---- Lencana status permohonan ---- */
    .pp-status { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .pp-status-belum { background: rgba(104, 121, 141, .12); color: var(--dms-muted); }
    .pp-status-proses { background: rgba(245, 158, 11, .14); color: #b45309; }
    .pp-status-sokong { background: rgba(23, 105, 210, .1); color: var(--dms-primary); }
    .pp-status-lulus { background: rgba(37, 169, 121, .12); color: var(--dms-mint); }
    .pp-status-tolak { background: rgba(196, 61, 78, .1); color: var(--dms-danger); }

    /* ---- Dua panel bersebelahan (desktop), bertindan (tablet/telefon) ---- */
    .pp-dua { display: grid; grid-template-columns: 1fr; gap: 16px; }
    @media (min-width: 1200px) { .pp-dua { grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); } }

    /* ---- Bar mendatar HTML ---- */
    .pp-bar { display: grid; gap: 12px; margin: 0; padding: 16px 18px; list-style: none; }
    .pp-bar-teks { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 5px; font-size: 12.5px; }
    .pp-bar-teks span { color: var(--dms-text); line-height: 1.35; }
    .pp-bar-teks strong { color: var(--dms-brand); }
    .pp-bar-trek { height: 8px; border-radius: 999px; background: rgba(104, 121, 141, .12); overflow: hidden; }
    .pp-bar-isi { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary)); }

    @media (min-width: 1200px) { .pp-dua-sama { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

    /* ---- Pentadbir: perlu perhatian ---- */
    .pp-perhatian { margin-bottom: 16px; padding: 14px 18px; box-shadow: inset 4px 0 0 var(--dms-danger); }
    .pp-perhatian-ok { box-shadow: inset 4px 0 0 var(--dms-mint); }
    .pp-perhatian-ok .pp-bahagian-tajuk { color: var(--dms-mint); }
    .pp-perhatian .pp-bahagian-tajuk { color: var(--dms-danger); }
    .pp-perhatian ul { display: grid; gap: 8px; margin: 10px 0 0; padding: 0; list-style: none; }
    .pp-perhatian li { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; font-size: 13px; }
    .pp-perhatian-bil { min-width: 30px; padding: 3px 8px; border-radius: 8px; font-weight: 800; text-align: center; }
    .pp-perhatian-teks { flex: 1 1 200px; color: var(--dms-text); }
    .pp-perhatian li a { color: var(--dms-primary); font-size: 12px; font-weight: 700; white-space: nowrap; }

    /* ---- Pentadbir: aliran kelulusan (bar bersegmen) ---- */
    .pp-aliran { padding: 18px; }
    .pp-aliran-bar { display: flex; height: 14px; overflow: hidden; border-radius: 999px; background: rgba(104, 121, 141, .12); }
    .pp-aliran-bar span { display: block; height: 100%; }
    .pp-aliran-bar .pp-status-proses, .pp-titik.pp-status-proses { background: #f59e0b; }
    .pp-aliran-bar .pp-status-sokong, .pp-titik.pp-status-sokong { background: var(--dms-primary); }
    .pp-aliran-bar .pp-status-lulus, .pp-titik.pp-status-lulus { background: var(--dms-mint); }
    .pp-aliran-bar .pp-status-tolak, .pp-titik.pp-status-tolak { background: var(--dms-danger); }
    .pp-aliran-legenda { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 16px; margin: 14px 0 0; padding: 0; list-style: none; font-size: 12.5px; }
    .pp-aliran-legenda li { display: flex; align-items: center; gap: 7px; }
    .pp-aliran-legenda strong { margin-left: auto; color: var(--dms-brand); }
    .pp-titik { display: inline-block; width: 10px; height: 10px; padding: 0; border-radius: 50%; }

    /* ---- Pentadbir: trend (bar menegak HTML) ---- */
    .pp-trend { display: flex; align-items: flex-end; gap: 10px; height: 170px; padding: 16px 18px 12px; }
    .pp-trend-lajur { display: flex; flex: 1; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; gap: 4px; }
    .pp-trend-bar { width: 100%; max-width: 42px; border-radius: 6px 6px 2px 2px; background: linear-gradient(180deg, var(--dms-primary), var(--dms-brand)); }
    .pp-trend-nilai { color: var(--dms-brand); font-size: 11.5px; font-weight: 800; }
    .pp-trend-label { color: var(--dms-muted); font-size: 11px; font-weight: 600; }

    /* ---- Pentadbir: bahagian berlipat ---- */
    .pp-lipat { padding: 0; }
    .pp-lipat summary { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 14px 18px; color: var(--dms-brand); font-size: 14px; font-weight: 800; cursor: pointer; list-style: none; }
    .pp-lipat summary::-webkit-details-marker { display: none; }
    .pp-lipat summary::after { content: "\F282"; margin-left: auto; font-family: bootstrap-icons; transition: transform .2s ease; }
    .pp-lipat[open] summary::after { transform: rotate(180deg); }
    .pp-lipat[open] summary .pp-kecil { display: none; }
    .pp-lipat-isi { padding: 4px 18px 18px; }

    /* ---- Keadaan kosong ---- */
    .pp-kosong { display: grid; justify-items: center; gap: 8px; padding: 28px 18px; color: var(--dms-muted); font-size: 13px; text-align: center; }
    .pp-kosong > i { font-size: 28px; color: var(--dms-primary); opacity: .6; }
    .pp-kosong a { color: var(--dms-primary); font-weight: 700; }

    @media (max-width: 576px) {
        .pp-kepala h2 { font-size: 19px; }
        .pp-pantas a { flex: 1 1 calc(50% - 10px); justify-content: center; padding: 10px; font-size: 12.5px; }
        .pp-kpi, .pp-kpi.tiga { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .pp-kad { flex-direction: column; align-items: flex-start; gap: 8px; padding: 12px; }
        .pp-kad-ikon { flex-basis: 34px; width: 34px; height: 34px; font-size: 16px; }
        .pp-kad-nilai { font-size: 20px; }
        .pp-panel-kepala { padding: 12px 16px; }
    }
</style>
