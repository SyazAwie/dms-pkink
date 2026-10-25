{{--
    Interaksi input medan EAV (medan-input.blade.php). Disertakan dalam @push('scripts')
    halaman yang memaparkan borang medan. Guna delegasi acara pada document supaya
    berfungsi juga untuk medan yang dimuatkan melalui fetch() (borang Muat Naik).
--}}
<script>
(() => {
    const MAKS_BARIS = @json(\App\Support\MedanJadual::MAKS_BARIS);

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
})();
</script>
