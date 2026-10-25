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
    .medan-eav:has(input[type="radio"][value="__lain__"]:checked) .medan-lain-wrap,
    .medan-lain-wrap:has(.is-invalid) { display: block; }

    .tag-ai {
        background: rgba(124, 58, 237, .12); color: #7c3aed;
        font-size: 9.5px; font-weight: 800; padding: 2px 7px; border-radius: 999px; margin-left: 4px;
        text-transform: uppercase; letter-spacing: .3px;
    }
</style>
