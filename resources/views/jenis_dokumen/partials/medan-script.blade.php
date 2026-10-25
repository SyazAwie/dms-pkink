{{-- resources/views/jenis_dokumen/partials/medan-script.blade.php --}}
{{--
    Pembina medan EAV, dikongsi antara create.blade.php & edit.blade.php.
    Sebelum @include ini, set window.__medanSediaAda kepada senarai baris:
    medan daripada DB (edit) atau old('medan') selepas ralat validasi.
    Baris DB ada `tetapan` (objek); baris old() ada `bantuan`/`lain` terus.
--}}
<style>
    /* Dua baris: Nama + Kod, kemudian Jenis Data (label panjang) + Wajib + Buang */
    .medan-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto auto; }
    .medan-col-nama { grid-column: 1 / 2; }
    .medan-col-kod { grid-column: 2 / 5; }
    .medan-col-jenis { grid-column: 1 / 3; }
    @media (max-width: 576px) {
        .medan-col-nama, .medan-col-kod { grid-column: 1 / 5; }
    }
    .medan-tambahan { display: grid; grid-template-columns: 1fr; gap: 6px; margin-top: 10px; }
    .medan-lain-pilihan { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--dms-text); cursor: pointer; margin-top: 4px; }
</style>
<script>
(() => {
    const container = document.getElementById('senaraiMedan');
    const emptyState = document.getElementById('medanKosong');
    const btnTambah = document.getElementById('btnTambahMedan');
    const inputDipadam = document.getElementById('medan_dipadam');
    const form = document.getElementById('jenisDokumenForm');

    // Senarai jenis data dari App\Support\JenisMedan (sumber tunggal)
    const JENIS_MEDAN = @json(\App\Support\JenisMedan::SENARAI);
    const ADA_PILIHAN = @json(\App\Support\JenisMedan::ADA_PILIHAN);
    const TANPA_NILAI = @json(\App\Support\JenisMedan::TANPA_NILAI);

    let counter = 0;
    let idDipadam = inputDipadam.value ? inputDipadam.value.split(',').filter(Boolean) : [];

    function escAttr(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function slugKod(teks) {
        return String(teks ?? '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function kemaskiniEmptyState() {
        if (emptyState) {
            emptyState.style.display = container.children.length ? 'none' : 'block';
        }
    }

    function buangBaris(row) {
        const id = row.dataset.id;
        if (id) {
            idDipadam.push(id);
            inputDipadam.value = idDipadam.join(',');
        }
        row.remove();
        kemaskiniEmptyState();
    }

    function tambahBaris(data = {}) {
        const idx = counter++;
        const row = document.createElement('div');
        row.className = 'medan-row';
        row.dataset.id = data.dokumen_field_id ?? data.id ?? '';

        const tetapan = data.tetapan || {};
        const jenisSemasa = data.jenis_data in JENIS_MEDAN ? data.jenis_data : 'teks';
        const adaPilihan = ADA_PILIHAN.includes(jenisSemasa);
        // Pilihan: array (DB) atau teks "a, b, c" (old() selepas ralat validasi)
        const pilihanTeks = Array.isArray(data.pilihan) ? data.pilihan.join(', ') : (data.pilihan ?? '');
        const bantuan = tetapan.bantuan ?? data.bantuan ?? '';
        const lain = Boolean(tetapan.lain ?? (data.lain && data.lain !== '0'));
        const wajib = Boolean(data.is_required && data.is_required !== '0');
        const rowId = row.dataset.id;
        const opsyenJenis = Object.entries(JENIS_MEDAN)
            .map(([kod, label]) => `<option value="${kod}" ${kod === jenisSemasa ? 'selected' : ''}>${escAttr(label)}</option>`)
            .join('');

        row.innerHTML = `
            ${rowId ? `<input type="hidden" name="medan[${idx}][id]" value="${escAttr(rowId)}">` : ''}
            <div class="medan-grid">
                <div class="medan-col-nama">
                    <label class="dms-label">Nama Medan</label>
                    <input type="text" name="medan[${idx}][nama_field]" class="dms-input medan-nama"
                           value="${escAttr(data.nama_field)}" placeholder="Cth: No. Invois" maxlength="100">
                </div>
                <div class="medan-col-kod">
                    <label class="dms-label">Kod Medan</label>
                    <input type="text" name="medan[${idx}][kod_field]" class="dms-input medan-kod"
                           value="${escAttr(data.kod_field)}" placeholder="cth: no_invois" maxlength="100">
                </div>
                <div class="medan-col-jenis">
                    <label class="dms-label">Jenis Data</label>
                    <select name="medan[${idx}][jenis_data]" class="dms-input medan-jenis">${opsyenJenis}</select>
                </div>
                <div class="medan-col-wajib">
                    <label class="dms-label">&nbsp;</label>
                    <label class="medan-checkbox">
                        <input type="checkbox" name="medan[${idx}][is_required]" value="1" ${wajib ? 'checked' : ''}>
                        <span>Wajib</span>
                    </label>
                </div>
                <div class="medan-col-buang">
                    <label class="dms-label">&nbsp;</label>
                    <button type="button" class="btn-buang-medan" title="Buang Medan">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
            <div class="medan-pilihan-wrap" style="display:${adaPilihan ? 'flex' : 'none'};">
                <label class="dms-label">Senarai Pilihan (asingkan dengan koma)</label>
                <input type="text" name="medan[${idx}][pilihan]" class="dms-input medan-pilihan"
                       value="${escAttr(pilihanTeks)}" placeholder="Cth: Baru, Dalam Proses, Selesai">
                <label class="medan-lain-pilihan">
                    <input type="checkbox" name="medan[${idx}][lain]" value="1" ${lain ? 'checked' : ''}>
                    <span>Tambah pilihan "Lain-lain (nyatakan)"</span>
                </label>
            </div>
            <div class="medan-tambahan">
                <input type="text" name="medan[${idx}][bantuan]" class="dms-input medan-bantuan"
                       value="${escAttr(bantuan)}" maxlength="255" placeholder="Teks bantuan (pilihan), cth: Gunakan huruf kecil">
            </div>
        `;

        const kodInput = row.querySelector('.medan-kod');
        const namaInput = row.querySelector('.medan-nama');
        const jenisSelect = row.querySelector('.medan-jenis');
        const pilihanWrap = row.querySelector('.medan-pilihan-wrap');
        const btnBuang = row.querySelector('.btn-buang-medan');

        namaInput.addEventListener('blur', () => {
            if (!kodInput.value.trim() && namaInput.value.trim()) {
                kodInput.value = slugKod(namaInput.value);
            }
        });
        kodInput.addEventListener('input', () => {
            kodInput.value = slugKod(kodInput.value);
        });
        const wajibCol = row.querySelector('.medan-col-wajib');
        const bantuanInput = row.querySelector('.medan-bantuan');
        function kemaskiniIkutJenis() {
            const jenis = jenisSelect.value;
            pilihanWrap.style.display = ADA_PILIHAN.includes(jenis) ? 'flex' : 'none';
            // Tajuk seksyen tiada nilai: "Wajib" tidak berkaitan; teks bantuan menjadi keterangan seksyen
            const tanpaNilai = TANPA_NILAI.includes(jenis);
            wajibCol.style.visibility = tanpaNilai ? 'hidden' : '';
            if (tanpaNilai) wajibCol.querySelector('input').checked = false;
            bantuanInput.placeholder = tanpaNilai
                ? 'Keterangan seksyen (pilihan), cth: Untuk kegunaan Bahagian Digital sahaja'
                : 'Teks bantuan (pilihan), cth: Gunakan huruf kecil';
        }
        jenisSelect.addEventListener('change', kemaskiniIkutJenis);
        kemaskiniIkutJenis();
        btnBuang.addEventListener('click', () => buangBaris(row));

        container.appendChild(row);
        kemaskiniEmptyState();
    }

    if (btnTambah) {
        btnTambah.addEventListener('click', () => tambahBaris());
    }

    (window.__medanSediaAda || []).forEach(tambahBaris);
    kemaskiniEmptyState();

    // Sahkan setiap baris yang diisi Nama mesti ada Kod, sebelum hantar borang
    if (form) {
        form.addEventListener('submit', (e) => {
            const adaBarisTakLengkap = [...container.querySelectorAll('.medan-row')].some(row => {
                const nama = row.querySelector('.medan-nama').value.trim();
                const kod = row.querySelector('.medan-kod').value.trim();
                return nama && !kod;
            });
            if (adaBarisTakLengkap) {
                e.preventDefault();
                alert('Sila pastikan setiap Medan yang diisi Nama mempunyai Kod Medan.');
            }
        });
    }
})();
</script>
