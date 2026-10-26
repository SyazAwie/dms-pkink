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

    /* Editor lajur bagi medan jadual */
    .medan-jadual-pembina { margin-top: 10px; padding: 10px; border: 1px solid var(--dms-line); border-radius: 10px; background: var(--dms-surface-strong); }
    .lajur-baris { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) auto; gap: 8px; align-items: center; margin-bottom: 8px; }
    .lajur-baris .lajur-pilihan { grid-column: 1 / 3; }
    .lajur-baris .dms-input { padding: 8px 10px; font-size: 12.5px; }
    .lajur-buang { border: 1px solid var(--dms-line); background: transparent; color: var(--dms-danger); border-radius: 8px; padding: 6px 10px; cursor: pointer; }
    /* Syarat "tunjuk jika" dan amaran tukar jenis (#16) */
    .medan-syarat { display: grid; grid-template-columns: auto minmax(0, 1fr) auto minmax(0, 1fr); gap: 6px; align-items: center; margin-top: 6px; }
    .medan-syarat .dms-input { padding: 7px 10px; font-size: 12px; }
    .medan-syarat-label { font-size: 11.5px; font-weight: 600; color: var(--dms-muted); white-space: nowrap; }
    @media (max-width: 576px) { .medan-syarat { grid-template-columns: 1fr; } }
    /* Auto-isi daripada profil (hanya jenis dalam JenisMedan::AUTO_ISI_SERASI) */
    .medan-auto-isi { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 6px; align-items: center; margin-top: 6px; }
    .medan-auto-isi .dms-input { padding: 7px 10px; font-size: 12px; }
    .medan-amaran-tukar { margin-top: 8px; padding: 8px 10px; border-radius: 8px; background: rgba(220, 150, 20, .1); color: #8a5a00; font-size: 12px; }
    .medan-amaran-tukar .medan-lain-pilihan { display: flex; color: #8a5a00; }
    .lajur-tambah { border: 1px dashed var(--dms-primary); background: rgba(23, 105, 210, .04); color: var(--dms-primary); border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
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
    const JENIS_LAJUR = @json(\App\Support\MedanJadual::JENIS_LAJUR);
    const SUMBER_AUTO_ISI = @json(\App\Support\JenisMedan::SUMBER_AUTO_ISI);
    const AUTO_ISI_SERASI = @json(\App\Support\JenisMedan::AUTO_ISI_SERASI);

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
        // Baris tetap jadual: tetapan.baris (DB, array) atau data.baris_tetap (old(), teks)
        const barisTetap = Array.isArray(tetapan.baris) ? tetapan.baris.join(', ') : (data.baris_tetap ?? '');
        // Syarat "tunjuk jika": tetapan.tunjuk_jika (DB) atau tunjuk_kod/tunjuk_nilai (old())
        const tunjukKod = tetapan.tunjuk_jika?.kod ?? data.tunjuk_kod ?? '';
        const tunjukNilai = tetapan.tunjuk_jika?.nilai ?? data.tunjuk_nilai ?? '';
        // Auto-isi daripada profil: tetapan.auto_isi (DB) atau auto_isi (old())
        let autoIsiDipilih = tetapan.auto_isi ?? data.auto_isi ?? '';
        // #16: bilangan nilai tersimpan & jenis asal (medan sedia ada sahaja)
        const bilNilai = Number(data.bil_nilai ?? 0);
        const jenisAsal = data.jenis_asal ?? (data.dokumen_field_id ? data.jenis_data : '');
        const sahkanTukar = Boolean(data.sahkan_tukar && data.sahkan_tukar !== '0');
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
            <div class="medan-jadual-pembina" style="display:none;">
                <label class="dms-label">Lajur Jadual</label>
                <div class="lajur-senarai"></div>
                <button type="button" class="lajur-tambah"><i class="bi bi-plus-lg"></i> Tambah Lajur</button>
                <label class="dms-label mt-3">Baris Tetap <span class="dms-optional">(pilihan, asingkan dengan koma)</span></label>
                <input type="text" name="medan[${idx}][baris_tetap]" class="dms-input medan-baris-tetap" maxlength="1000"
                       value="${escAttr(barisTetap)}" placeholder="Cth: Emel, Intranet, Makluman — kosongkan jika pengguna tambah baris sendiri">
            </div>
            <div class="medan-tambahan">
                <input type="text" name="medan[${idx}][bantuan]" class="dms-input medan-bantuan"
                       value="${escAttr(bantuan)}" maxlength="255" placeholder="Teks bantuan (pilihan), cth: Gunakan huruf kecil">
            </div>
            <div class="medan-syarat">
                <span class="medan-syarat-label">Tunjuk hanya jika</span>
                <input type="text" name="medan[${idx}][tunjuk_kod]" class="dms-input medan-syarat-kod" list="senaraiKodSumber"
                       value="${escAttr(tunjukKod)}" maxlength="100" placeholder="kod medan (pilihan)" aria-label="Kod medan syarat">
                <span class="medan-syarat-label">=</span>
                <input type="text" name="medan[${idx}][tunjuk_nilai]" class="dms-input medan-syarat-nilai"
                       value="${escAttr(tunjukNilai)}" maxlength="255" placeholder="nilai, cth: Khidmat Luar" aria-label="Nilai syarat">
            </div>
            <div class="medan-auto-isi" style="display:none;">
                <span class="medan-syarat-label">Isi automatik daripada profil</span>
                <select name="medan[${idx}][auto_isi]" class="dms-input medan-auto-isi-pilih" aria-label="Isi automatik daripada profil"></select>
            </div>
            <input type="hidden" name="medan[${idx}][bil_nilai]" value="${bilNilai}">
            <input type="hidden" name="medan[${idx}][jenis_asal]" value="${escAttr(jenisAsal)}">
            <div class="medan-amaran-tukar" style="display:none;">
                <i class="bi bi-exclamation-triangle"></i>
                <span>Medan ini sudah ada <strong>${bilNilai}</strong> nilai tersimpan. Menukar jenis data boleh menyebabkan nilai lama gagal validasi semasa dokumen diedit; sistem akan menolak jika ada nilai yang tidak serasi.</span>
                <label class="medan-lain-pilihan">
                    <input type="checkbox" name="medan[${idx}][sahkan_tukar]" value="1" ${sahkanTukar ? 'checked' : ''}>
                    <span>Teruskan tukar jenis walaupun ada nilai tidak serasi</span>
                </label>
            </div>
        `;

        // #16: amaran bila jenis data medan yang sudah ada nilai ditukar
        const amaranTukar = row.querySelector('.medan-amaran-tukar');
        function kemaskiniAmaranTukar() {
            amaranTukar.style.display = bilNilai > 0 && jenisAsal && row.querySelector('.medan-jenis').value !== jenisAsal ? '' : 'none';
        }

        // ---- Editor lajur jadual ----
        // Lajur: tetapan.lajur (DB, pilihan = array) atau data.lajur (old(), pilihan = teks)
        const lajurSenarai = row.querySelector('.lajur-senarai');
        let lajurCounter = 0;
        function tambahLajur(l = {}) {
            const j = lajurCounter++;
            const jenisLajur = l.jenis in JENIS_LAJUR ? l.jenis : 'teks';
            const pilihanLajur = Array.isArray(l.pilihan) ? l.pilihan.join(', ') : (l.pilihan ?? '');
            const opsyen = Object.entries(JENIS_LAJUR)
                .map(([kod, label]) => `<option value="${kod}" ${kod === jenisLajur ? 'selected' : ''}>${escAttr(label)}</option>`)
                .join('');
            const div = document.createElement('div');
            div.className = 'lajur-baris';
            // Kod lajur sedia ada dikekalkan (tersembunyi) supaya data lama masih sepadan bila nama ditukar
            div.innerHTML = `
                <input type="hidden" name="medan[${idx}][lajur][${j}][kod]" value="${escAttr(l.kod)}">
                <input type="text" name="medan[${idx}][lajur][${j}][nama]" class="dms-input" maxlength="100"
                       value="${escAttr(l.nama)}" placeholder="Nama lajur, cth: Kuantiti" aria-label="Nama lajur">
                <select name="medan[${idx}][lajur][${j}][jenis]" class="dms-input lajur-jenis" aria-label="Jenis lajur">${opsyen}</select>
                <button type="button" class="lajur-buang" title="Buang lajur" aria-label="Buang lajur"><i class="bi bi-x-lg"></i></button>
                <input type="text" name="medan[${idx}][lajur][${j}][pilihan]" class="dms-input lajur-pilihan" maxlength="1000"
                       value="${escAttr(pilihanLajur)}" placeholder="Pilihan dropdown (asingkan dengan koma)" aria-label="Pilihan lajur"
                       style="display:${jenisLajur === 'dropdown' ? '' : 'none'};">
            `;
            const jenisLajurSelect = div.querySelector('.lajur-jenis');
            jenisLajurSelect.addEventListener('change', () => {
                div.querySelector('.lajur-pilihan').style.display = jenisLajurSelect.value === 'dropdown' ? '' : 'none';
            });
            div.querySelector('.lajur-buang').addEventListener('click', () => div.remove());
            lajurSenarai.appendChild(div);
        }
        Object.values(tetapan.lajur ?? data.lajur ?? {}).forEach(tambahLajur);
        row.querySelector('.lajur-tambah').addEventListener('click', () => tambahLajur());

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
        const jadualPembina = row.querySelector('.medan-jadual-pembina');
        const autoIsiWrap = row.querySelector('.medan-auto-isi');
        const autoIsiPilih = row.querySelector('.medan-auto-isi-pilih');
        autoIsiPilih.addEventListener('change', () => { autoIsiDipilih = autoIsiPilih.value; });
        function kemaskiniIkutJenis() {
            const jenis = jenisSelect.value;
            // Auto-isi: hanya sumber yang serasi dengan jenis ini (pilihan diingat jika jenis ditukar balik)
            const serasi = AUTO_ISI_SERASI[jenis] ?? [];
            autoIsiWrap.style.display = serasi.length ? '' : 'none';
            autoIsiPilih.innerHTML = '<option value="">Tiada (pengguna isi sendiri)</option>' + serasi
                .map((kod) => `<option value="${kod}" ${kod === autoIsiDipilih ? 'selected' : ''}>${escAttr(SUMBER_AUTO_ISI[kod])}</option>`)
                .join('');
            pilihanWrap.style.display = ADA_PILIHAN.includes(jenis) ? 'flex' : 'none';
            jadualPembina.style.display = jenis === 'jadual' ? '' : 'none';
            // Jadual baharu: mula dengan satu lajur kosong
            if (jenis === 'jadual' && !lajurSenarai.children.length) tambahLajur();
            // Tajuk seksyen tiada nilai: "Wajib" tidak berkaitan; teks bantuan menjadi keterangan seksyen
            const tanpaNilai = TANPA_NILAI.includes(jenis);
            wajibCol.style.visibility = tanpaNilai ? 'hidden' : '';
            if (tanpaNilai) wajibCol.querySelector('input').checked = false;
            bantuanInput.placeholder = tanpaNilai
                ? 'Keterangan seksyen (pilihan), cth: Untuk kegunaan Bahagian Digital sahaja'
                : 'Teks bantuan (pilihan), cth: Gunakan huruf kecil';
        }
        jenisSelect.addEventListener('change', kemaskiniIkutJenis);
        jenisSelect.addEventListener('change', kemaskiniAmaranTukar);
        kemaskiniIkutJenis();
        kemaskiniAmaranTukar();
        btnBuang.addEventListener('click', () => buangBaris(row));

        container.appendChild(row);
        kemaskiniEmptyState();
    }

    if (btnTambah) {
        btnTambah.addEventListener('click', () => tambahBaris());
    }

    // Cadangan kod untuk syarat "tunjuk jika": medan berpilihan dalam pembina (dikemas kini bila pembina berubah)
    const SUMBER_SYARAT = @json(\App\Support\JenisMedan::SUMBER_SYARAT);
    const senaraiKodSumber = document.createElement('datalist');
    senaraiKodSumber.id = 'senaraiKodSumber';
    container.after(senaraiKodSumber);
    function kemaskiniKodSumber() {
        senaraiKodSumber.innerHTML = [...container.querySelectorAll('.medan-row')]
            .filter((r) => SUMBER_SYARAT.includes(r.querySelector('.medan-jenis').value) && r.querySelector('.medan-kod').value)
            .map((r) => `<option value="${escAttr(r.querySelector('.medan-kod').value)}">${escAttr(r.querySelector('.medan-nama').value)}</option>`)
            .join('');
    }
    container.addEventListener('input', kemaskiniKodSumber);
    container.addEventListener('change', kemaskiniKodSumber);

    (window.__medanSediaAda || []).forEach(tambahBaris);
    kemaskiniEmptyState();
    kemaskiniKodSumber();

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

    // Untuk pemilih templat (create.blade.php): gantikan semua baris dengan medan templat/salinan
    window.__pembinaMedan = {
        gantiSemua(senarai) {
            [...container.querySelectorAll('.medan-row')].forEach(buangBaris);
            (senarai || []).forEach(tambahBaris);
            kemaskiniEmptyState();
            kemaskiniKodSumber();
        },
        bilanganBaris: () => container.querySelectorAll('.medan-row').length,
    };
})();
</script>
