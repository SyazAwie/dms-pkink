{{--
    Interaksi input medan EAV (medan-input.blade.php). Disertakan dalam @push('scripts')
    halaman yang memaparkan borang medan. Guna delegasi acara pada document supaya
    berfungsi juga untuk medan yang dimuatkan melalui fetch() (borang Muat Naik).
--}}
<script>
(() => {
    const MAKS_BARIS = @json(\App\Support\MedanJadual::MAKS_BARIS);

    // ---- Syarat "tunjuk jika" (JenisMedan::kelihatan, sama logik di pelayan) ----
    // Nilai semasa medan sumber: senarai nilai yang dipilih/ditaip (kosong jika tersembunyi)
    function nilaiMedan(skop, kod) {
        const pembungkus = skop.querySelector(`[data-kod="${CSS.escape(kod)}"]`);
        if (!pembungkus) return null;
        if (pembungkus.hidden) return [];
        return [...pembungkus.querySelectorAll('[name^="medan_data"]')]
            .filter((i) => !i.disabled && ((i.type !== 'radio' && i.type !== 'checkbox') || i.checked))
            .map((i) => i.value);
    }

    // Sembunyi & nyahaktif medan yang syaratnya tidak dipenuhi (input dinyahaktif tidak dihantar)
    function nilaiSyarat() {
        // Diulang beberapa kali supaya syarat berantai (A → B → C) stabil
        for (let pusingan = 0; pusingan < 3; pusingan++) {
            document.querySelectorAll('[data-tunjuk-kod]').forEach((medan) => {
                const nilai = nilaiMedan(medan.closest('form') || document, medan.dataset.tunjukKod);
                const tunjuk = nilai === null || nilai.includes(medan.dataset.tunjukNilai);
                if (medan.hidden === !tunjuk) return;
                medan.hidden = !tunjuk;
                medan.querySelectorAll('input, select, textarea, button').forEach((i) => { i.disabled = !tunjuk; });
            });
        }
    }

    document.addEventListener('change', (e) => {
        if (e.target.closest('[data-kod]')) nilaiSyarat();
    });
    // Medan yang dimuatkan melalui fetch() (borang Muat Naik) juga dinilai
    new MutationObserver((perubahan) => {
        if (perubahan.some((p) => [...p.addedNodes].some((n) => n.nodeType === 1 && (n.matches('[data-kod]') || n.querySelector('[data-kod]'))))) {
            nilaiSyarat();
        }
    }).observe(document.body, { childList: true, subtree: true });
    nilaiSyarat();

    document.addEventListener('click', (e) => {
        // Jadual: tambah baris daripada <template> (indeks baharu menggantikan __I__)
        const tambah = e.target.closest('.medan-jadual-tambah');
        if (tambah) {
            const jadual = tambah.closest('.medan-jadual');
            const tbody = jadual.querySelector('tbody');
            if (tbody.rows.length >= MAKS_BARIS) {
                return;
            }
            const indeks = Number(jadual.dataset.seterusnya || tbody.rows.length);
            jadual.dataset.seterusnya = indeks + 1;
            const templat = jadual.querySelector('template.medan-jadual-templat');
            tbody.insertAdjacentHTML('beforeend', templat.innerHTML.replaceAll('__I__', indeks));
            tbody.lastElementChild?.querySelector('input, select')?.focus();
            return;
        }

        // Jadual: buang baris (baris terakhir hanya dikosongkan supaya jadual tidak hilang)
        const buang = e.target.closest('.medan-jadual-buang');
        if (buang) {
            const baris = buang.closest('tr');
            if (baris.parentElement.rows.length > 1) {
                baris.remove();
            } else {
                baris.querySelectorAll('input, select').forEach((input) => { input.value = ''; });
            }
        }
    });

    // Seksyen pejabat berlipat (Muat Naik): buka jika ada ralat validasi di dalamnya
    document.querySelectorAll('details.medan-pejabat').forEach((d) => {
        if (d.querySelector('.is-invalid, .dms-error')) d.open = true;
    });
})();
</script>
