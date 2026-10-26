{{-- Gaya input medan EAV (medan-input.blade.php). Disertakan dalam @push('styles') halaman yang memaparkan borang medan. --}}
<style>
    .medan-wang { display: flex; align-items: stretch; }
    .medan-wang > span {
        display: flex; align-items: center; padding: 0 12px;
        border: 1px solid var(--dms-line); border-right: none; border-radius: 10px 0 0 10px;
        background: rgba(23, 105, 210, .05); color: var(--dms-muted); font-size: 12.5px; font-weight: 700;
    }
    .medan-wang > .dms-input { border-radius: 0 10px 10px 0; }

    .medan-pilihan-kumpulan { display: flex; flex-wrap: wrap; gap: 8px; }
    .medan-pilihan-kumpulan.is-invalid .medan-radio { border-color: var(--dms-danger); }
    .medan-radio {
        display: inline-flex; align-items: center; gap: 8px; margin: 0;
        padding: 8px 12px; border: 1px solid var(--dms-line); border-radius: 10px;
        background: var(--dms-surface-strong); font-size: 13px; color: var(--dms-text); cursor: pointer;
    }
    .medan-radio:has(input:checked) { border-color: var(--dms-primary); background: rgba(23, 105, 210, .07); font-weight: 700; }
    .medan-radio input { accent-color: var(--dms-primary); }
    .medan-radio-kosong { color: var(--dms-muted); }

    /* "Lain-lain (nyatakan)": tunjuk input teks hanya bila pilihan Lain-lain dipilih */
    .medan-lain-wrap { display: none; margin-top: 8px; }
    .medan-eav:has(option[value="__lain__"]:checked) .medan-lain-wrap,
    .medan-eav:has(input[value="__lain__"]:checked) .medan-lain-wrap,
    .medan-lain-wrap:has(.is-invalid) { display: block; }

    /* Medan yang syarat "tunjuk jika"nya tidak dipenuhi (medan-skrip) */
    [data-kod][hidden] { display: none !important; }

    /* Tajuk seksyen (jenis "seksyen"): pemisah antara kumpulan medan */
    .medan-seksyen { margin: 18px 0 12px; padding: 8px 12px; border-left: 3px solid var(--dms-primary); background: rgba(23, 105, 210, .05); border-radius: 0 8px 8px 0; }
    .medan-seksyen-tajuk { font-size: 13px; font-weight: 800; color: var(--dms-brand); text-transform: uppercase; letter-spacing: .3px; }

    /* Jadual (baris berulang) */
    .medan-jadual-wrap { overflow-x: auto; border: 1px solid var(--dms-line); border-radius: 10px; background: var(--dms-surface-strong); }
    .medan-jadual-input { width: 100%; border-collapse: collapse; font-size: 12.5px; counter-reset: baris; }
    .medan-jadual-input th {
        padding: 8px 10px; background: rgba(23, 105, 210, .05); color: var(--dms-brand);
        font-weight: 700; text-align: left; white-space: nowrap; border-bottom: 1px solid var(--dms-line);
    }
    .medan-jadual-input td { padding: 6px; border-bottom: 1px solid var(--dms-line); vertical-align: middle; min-width: 120px; }
    .medan-jadual-input tbody tr:last-child td { border-bottom: none; }
    .medan-jadual-input tbody tr { counter-increment: baris; }
    .medan-jadual-input .dms-input { padding: 7px 9px; font-size: 12.5px; border-radius: 8px; }
    .medan-jadual-input .medan-jadual-label { min-width: 0; width: 1%; white-space: nowrap; font-weight: 700; color: var(--dms-text); }
    .medan-jadual-input.ada-baris-tetap .medan-jadual-label { min-width: 120px; white-space: normal; }
    .medan-jadual-bil::before { content: counter(baris); color: var(--dms-muted); }
    .medan-jadual-tindakan { min-width: 0 !important; width: 1%; }
    .medan-jadual-buang {
        border: 1px solid var(--dms-line); background: transparent; color: var(--dms-danger);
        border-radius: 8px; padding: 5px 9px; cursor: pointer;
    }
    .medan-jadual-buang:hover { background: rgba(220, 53, 69, .08); }
    .medan-jadual-tambah {
        margin-top: 8px; border: 1px dashed var(--dms-primary); background: rgba(23, 105, 210, .04);
        color: var(--dms-primary); border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 700; cursor: pointer;
    }

    .tag-ai {
        background: rgba(124, 58, 237, .12); color: #7c3aed;
        font-size: 9.5px; font-weight: 800; padding: 2px 7px; border-radius: 999px; margin-left: 4px;
        text-transform: uppercase; letter-spacing: .3px;
    }
    .tag-profil {
        background: rgba(23, 105, 210, .1); color: var(--dms-primary);
        font-size: 9.5px; font-weight: 800; padding: 2px 7px; border-radius: 999px; margin-left: 4px;
        text-transform: uppercase; letter-spacing: .3px;
    }
    /* Medan auto-isi daripada profil: dikunci, nilai diisi oleh pelayan */
    .medan-eav .dms-input[readonly] { background: var(--dms-surface-strong); color: var(--dms-muted); cursor: not-allowed; }
</style>
