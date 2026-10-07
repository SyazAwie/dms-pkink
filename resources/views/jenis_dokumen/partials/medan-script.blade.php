{{-- resources/views/jenis_dokumen/partials/medan-script.blade.php --}}
{{--
    Dikongsi antara create.blade.php & edit.blade.php.
    Sebelum @include ini, set window.__medanSediaAda = [] (create)
    atau window.__medanSediaAda = @json($dokumen->fields) (edit).
--}}
<script>
(() => {
    const container = document.getElementById('senaraiMedan');
    const emptyState = document.getElementById('medanKosong');
    const btnTambah = document.getElementById('btnTambahMedan');
    const inputDipadam = document.getElementById('medan_dipadam');
    const form = document.getElementById('jenisDokumenForm');

    let counter = 0;
    let idDipadam = [];

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

        const isDropdown = data.jenis_data === 'dropdown';
        const pilihanArr = Array.isArray(data.pilihan) ? data.pilihan : [];
        const pilihanTeks = pilihanArr.join(', ');
        const rowId = row.dataset.id;

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
                    <select name="medan[${idx}][jenis_data]" class="dms-input medan-jenis">
                        <option value="teks" ${data.jenis_data === 'teks' ? 'selected' : ''}>Teks</option>
                        <option value="nombor" ${data.jenis_data === 'nombor' ? 'selected' : ''}>Nombor</option>
                        <option value="tarikh" ${data.jenis_data === 'tarikh' ? 'selected' : ''}>Tarikh</option>
                        <option value="dropdown" ${isDropdown ? 'selected' : ''}>Dropdown</option>
                        <option value="textarea" ${data.jenis_data === 'textarea' ? 'selected' : ''}>Textarea Panjang</option>
                    </select>
                </div>
                <div class="medan-col-wajib">
                    <label class="dms-label">&nbsp;</label>
                    <label class="medan-checkbox">
                        <input type="checkbox" name="medan[${idx}][is_required]" value="1" ${data.is_required ? 'checked' : ''}>
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
            <div class="medan-pilihan-wrap" style="display:${isDropdown ? 'flex' : 'none'};">
                <label class="dms-label">Senarai Pilihan (asingkan dengan koma)</label>
                <input type="text" name="medan[${idx}][pilihan]" class="dms-input medan-pilihan"
                       value="${escAttr(pilihanTeks)}" placeholder="Cth: Baru, Dalam Proses, Selesai">
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
        jenisSelect.addEventListener('change', () => {
            pilihanWrap.style.display = jenisSelect.value === 'dropdown' ? 'flex' : 'none';
        });
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