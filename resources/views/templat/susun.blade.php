@extends('layouts.app')

@section('title', 'Susun Kedudukan Medan')

@section('content')
<div class="page-intro">
    <div>
        <h2>Susun Kedudukan Medan</h2>
        <p>{{ $templat->jenisDokumen->nama_dokumen }} ({{ $templat->jenisDokumen->kod_dokumen }}) &middot; templat versi {{ $templat->versi }}.
            Letakkan kotak di ruang kosong borang tempat setiap nilai ditulis. Susunan ini digunakan untuk butang <strong>Muat Turun Borang Siap</strong> di Butiran Dokumen.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('jenis-dokumen.edit', $templat->jenis_dokumen_id) }}#templat" class="outline-button kekal-label text-decoration-none">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <span>Kembali</span>
        </a>
        <button type="button" class="outline-button kekal-label" id="butangCadang" title="Cari label medan dalam templat dan letak kotak bagi item yang belum diletak">
            <i class="bi bi-magic" aria-hidden="true"></i> <span>Cadang Automatik</span>
        </button>
        <button type="button" class="outline-button kekal-label" id="butangPratonton">
            <i class="bi bi-eye" aria-hidden="true"></i> <span>Pratonton PDF</span>
        </button>
        <button type="button" class="btn text-white save-btn" id="butangSimpan" @disabled($sekatDemo)>
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Simpan Susunan
        </button>
    </div>
</div>

@if(\App\Support\KedudukanMedan::perluSemak($kedudukan))
    <div class="alert alert-warning">Susunan ini diwarisi daripada versi templat sebelumnya. Semak setiap kotak (borang mungkin berubah susun atur), kemudian <strong>Simpan Susunan</strong>. Sehingga itu, muat turun borang siap terus menggunakan versi lama.</div>
@endif
@if($sekatDemo)
    <div class="alert alert-warning">Mod demo/percubaan: susunan boleh dilihat tetapi tidak boleh disimpan.</div>
@endif
<div class="alert d-none" id="mesejSusun" role="status"></div>

<div class="d-lg-none alert alert-info">Editor ini direka untuk skrin komputer. Gunakan komputer untuk menyusun kedudukan medan.</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="susun-panduan glass-panel mb-3">
            <strong>Cara guna:</strong> pilih item di sebelah kanan, kemudian <strong>seret pada borang</strong> untuk melukis kotaknya.
            Seret kotak untuk mengalih, seret bucu kanan bawah untuk mengubah saiz. <kbd>Delete</kbd> membuang kotak dipilih; anak panah menggerakkannya sedikit.
            Bagi jadual: <em>Baris pertama</em> = kedudukan & tinggi satu baris; kotak setiap lajur hanya menentukan lebar lajur.
        </div>
        @foreach($muka as $i => $m)
            <div class="susun-muka-wrap glass-panel mb-3">
                @if(count($muka) > 1)<div class="susun-muka-label">Muka surat {{ $i + 1 }}</div>@endif
                <div class="susun-muka" data-muka="{{ $i + 1 }}" style="aspect-ratio: {{ $m['lebar'] }} / {{ $m['tinggi'] }};">
                    <img src="{{ route('templat.muka', [$templat->templat_borang_id, $i + 1]) }}" alt="Templat muka surat {{ $i + 1 }}" draggable="false">
                </div>
            </div>
        @endforeach
    </div>

    <div class="col-lg-4">
        <div class="glass-panel susun-senarai" id="senaraiItem">
            <div class="susun-senarai-kepala">
                <strong>Medan</strong>
                <span class="dms-hint" id="bilDiletak"></span>
            </div>
            <div id="senaraiIsi"></div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .susun-panduan { padding: 12px 16px; font-size: 12.5px; color: var(--dms-muted); line-height: 1.6; }
    .susun-muka-wrap { padding: 10px; }
    .susun-muka-label { font-size: 12px; font-weight: 700; color: var(--dms-brand); margin-bottom: 6px; }
    .susun-muka { position: relative; width: 100%; user-select: none; touch-action: none; cursor: crosshair; background: #fff; }
    .susun-muka img { display: block; width: 100%; height: 100%; pointer-events: none; }
    .susun-kotak { position: absolute; border: 2px solid var(--warna); background: color-mix(in srgb, var(--warna) 14%, transparent); cursor: move; }
    .susun-kotak.dipilih { box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--warna); z-index: 3; }
    .susun-kotak span { position: absolute; left: 0; bottom: 100%; max-width: 260px; overflow: hidden; padding: 1px 5px; border-radius: 4px 4px 0 0; color: #fff; background: var(--warna); font-size: 10px; font-weight: 700; white-space: nowrap; text-overflow: ellipsis; pointer-events: none; }
    .susun-kotak .susun-pemegang { position: absolute; right: -6px; bottom: -6px; width: 12px; height: 12px; border: 2px solid #fff; border-radius: 3px; background: var(--warna); cursor: nwse-resize; }
    .susun-kotak[data-jenis="kotak"], .susun-kotak[data-jenis="asas"] { --warna: #1769d2; }
    .susun-kotak[data-jenis="pilihan"] { --warna: #d97706; }
    .susun-kotak[data-jenis="baris"], .susun-kotak[data-jenis="lajur"] { --warna: #15803d; }
    .susun-kotak[data-jenis="cop"] { --warna: #c81e33; }
    .susun-lukis { position: absolute; border: 2px dashed var(--dms-primary); background: rgba(23, 105, 210, .08); pointer-events: none; }
    .susun-senarai { position: sticky; top: 90px; max-height: calc(100vh - 110px); overflow: auto; padding: 0 0 10px; }
    .susun-senarai-kepala { position: sticky; top: 0; z-index: 2; display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--dms-line); background: var(--dms-surface-strong); color: var(--dms-brand); }
    .susun-kumpulan { padding: 10px 16px 2px; color: var(--dms-brand); font-size: 12px; font-weight: 800; }
    .susun-tetapan { display: flex; align-items: center; gap: 8px; padding: 4px 16px 4px 26px; font-size: 11.5px; color: var(--dms-muted); }
    .susun-tetapan select, .susun-tetapan input { width: auto; padding: 3px 6px; border: 1px solid var(--dms-line); border-radius: 6px; font-size: 11.5px; background: var(--dms-surface-strong); }
    .susun-tetapan input { width: 64px; }
    .susun-item { display: flex; align-items: center; gap: 8px; width: 100%; padding: 6px 16px; border: 0; background: transparent; color: var(--dms-text); font-size: 12.5px; text-align: left; }
    .susun-item.bersarang { padding-left: 26px; }
    .susun-item:hover { background: rgba(23, 105, 210, .05); }
    .susun-item.aktif { background: rgba(23, 105, 210, .12); font-weight: 700; }
    .susun-item .susun-titik { flex: 0 0 10px; width: 10px; height: 10px; border: 2px solid var(--dms-muted); border-radius: 50%; }
    .susun-item.diletak .susun-titik { border-color: var(--dms-mint); background: var(--dms-mint); }
    .susun-item .susun-label { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .susun-item .susun-buang { flex: 0 0 auto; padding: 0 4px; border: 0; background: none; color: var(--dms-danger); font-size: 13px; }
    .save-btn { background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary)); border: none; border-radius: 10px; font-weight: 700; }
    .save-btn:hover { color: #fff; }
    .dms-hint { font-size: 11.5px; color: var(--dms-muted); }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const ITEM = @json($item);
    const URL_SIMPAN = @json(route('templat.susun.simpan', $templat->templat_borang_id));
    const URL_PRATONTON = @json(route('templat.pratonton', $templat->templat_borang_id));
    const URL_CADANG = @json(route('templat.cadang', $templat->templat_borang_id));
    const SEKAT = @json($sekatDemo);
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;

    let K = @json($kedudukan);
    const normal = () => {
        if (!K || typeof K !== 'object' || Array.isArray(K)) K = {};
        if (!K.medan || Array.isArray(K.medan)) K.medan = {};
    };
    normal();

    let dipilih = null;      // kunci item aktif
    let berubah = false;
    const MIN = 0.005;

    // ---- akses kotak bagi satu item ----
    const medan = (it, cipta) => {
        if (!K.medan[it.medan] && cipta) K.medan[it.medan] = {};
        return K.medan[it.medan];
    };
    const dapat = (it) => {
        if (it.jenis === 'cop') return K.cop || null;
        if (it.jenis === 'asas') return (K.asas || {})[it.kod] || null;
        const m = medan(it, false);
        if (!m) return null;
        if (it.jenis === 'kotak') return m.kotak || null;
        if (it.jenis === 'pilihan') return (m.pilihan || {})[it.pilihan] || null;
        if (it.jenis === 'baris') return m.baris || null;
        if (it.jenis === 'lajur') return (m.lajur || {})[it.kod] || null;
        return null;
    };
    const tetap = (it, k) => {
        if (it.jenis === 'cop') { k ? (K.cop = k) : delete K.cop; tandaBerubah(); return; }
        if (it.jenis === 'asas') {
            if (!K.asas || Array.isArray(K.asas)) K.asas = {};
            k ? (K.asas[it.kod] = k) : delete K.asas[it.kod];
            tandaBerubah(); return;
        }
        const m = medan(it, true);
        if (it.jenis === 'kotak') { k ? (m.kotak = k) : delete m.kotak; }
        if (it.jenis === 'pilihan') { m.pilihan = m.pilihan || {}; k ? (m.pilihan[it.pilihan] = k) : delete m.pilihan[it.pilihan]; if (!m.gaya) m.gaya = 'tanda'; }
        if (it.jenis === 'baris') { if (k) { k.bilangan = (m.baris && m.baris.bilangan) || 3; m.baris = k; } else delete m.baris; }
        if (it.jenis === 'lajur') { m.lajur = m.lajur || {}; k ? (m.lajur[it.kod] = k) : delete m.lajur[it.kod]; }
        tandaBerubah();
    };
    const item = (kunci) => ITEM.find(i => i.kunci === kunci);

    const tandaBerubah = () => { berubah = true; };
    window.addEventListener('beforeunload', (e) => { if (berubah && !SEKAT) { e.preventDefault(); e.returnValue = ''; } });

    // ---- senarai item ----
    const senarai = document.getElementById('senaraiIsi');
    const binaSenarai = () => {
        senarai.innerHTML = '';
        let kumpulanTerakhir = null;
        let diletak = 0;
        ITEM.forEach((it) => {
            if (it.kumpulan && it.kumpulan !== kumpulanTerakhir) {
                kumpulanTerakhir = it.kumpulan;
                const h = document.createElement('div');
                h.className = 'susun-kumpulan';
                h.textContent = it.kumpulan;
                senarai.appendChild(h);
                const m = (it.medan && K.medan[it.medan]) || {};
                if (it.jenis === 'baris') {
                    senarai.appendChild(tetapan('Bilangan baris pada borang', 'number', (m.baris || {}).bilangan || 3, (v) => {
                        const mm = medan(it, true);
                        if (mm.baris) { mm.baris.bilangan = Math.max(1, Math.min(100, parseInt(v, 10) || 1)); tandaBerubah(); }
                    }, !m.baris));
                }
                if (it.medan && ITEM.some(x => x.medan === it.medan && x.jenis === 'pilihan')) {
                    senarai.appendChild(tetapan('Tanda pilihan', 'select', m.gaya || 'tanda', (v) => {
                        medan(it, true).gaya = v; tandaBerubah();
                    }, false));
                }
            } else if (!it.kumpulan) {
                kumpulanTerakhir = null;
            }
            const ada = !!dapat(it);
            diletak += ada ? 1 : 0;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'susun-item' + (it.kumpulan ? ' bersarang' : '') + (ada ? ' diletak' : '') + (dipilih === it.kunci ? ' aktif' : '');
            b.innerHTML = '<span class="susun-titik"></span><span class="susun-label"></span>';
            b.querySelector('.susun-label').textContent = it.label;
            b.title = ada ? 'Sudah diletak' : 'Belum diletak';
            b.addEventListener('click', () => pilih(it.kunci, true));
            if (ada) {
                const buang = document.createElement('span');
                buang.className = 'susun-buang';
                buang.setAttribute('role', 'button');
                buang.title = 'Buang kotak';
                buang.innerHTML = '<i class="bi bi-x-lg"></i>';
                buang.addEventListener('click', (e) => { e.stopPropagation(); tetap(it, null); lukis(); binaSenarai(); });
                b.appendChild(buang);
            }
            senarai.appendChild(b);
            // Medan wang: borang yang sudah mencetak "RM" sebelum ruang isian → tulis nombor sahaja
            if (it.wang && ada) {
                const d = document.createElement('label');
                d.className = 'susun-tetapan';
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.checked = !!dapat(it).tanpa_unit;
                cb.addEventListener('change', () => {
                    const k = dapat(it);
                    if (cb.checked) k.tanpa_unit = true; else delete k.tanpa_unit;
                    tandaBerubah();
                });
                d.append(cb, 'Tanpa "RM" (borang sudah mencetak RM)');
                senarai.appendChild(d);
            }
        });
        document.getElementById('bilDiletak').textContent = `${diletak} / ${ITEM.length} diletak`;
    };
    const tetapan = (label, jenis, nilai, ubah, nyahaktif) => {
        const d = document.createElement('label');
        d.className = 'susun-tetapan';
        d.append(label + ':');
        let el;
        if (jenis === 'select') {
            el = document.createElement('select');
            [['tanda', '✓ pada pilihan dipilih'], ['potong', 'Garis potong pilihan lain']].forEach(([v, t]) => {
                const o = new Option(t, v); o.selected = v === nilai; el.add(o);
            });
        } else {
            el = document.createElement('input');
            el.type = 'number'; el.min = 1; el.max = 100; el.value = nilai;
        }
        el.disabled = nyahaktif;
        el.addEventListener('change', () => ubah(el.value));
        d.appendChild(el);
        return d;
    };

    // ---- lukis kotak atas muka surat ----
    const muka = [...document.querySelectorAll('.susun-muka')];
    const lukis = () => {
        document.querySelectorAll('.susun-kotak').forEach(e => e.remove());
        ITEM.forEach((it) => {
            const k = dapat(it);
            const halaman = k && muka[(k.muka || 1) - 1];
            if (!halaman) return;
            const d = document.createElement('div');
            d.className = 'susun-kotak' + (dipilih === it.kunci ? ' dipilih' : '');
            d.dataset.jenis = it.jenis;
            d.dataset.kunci = it.kunci;
            Object.assign(d.style, { left: k.x * 100 + '%', top: k.y * 100 + '%', width: k.w * 100 + '%', height: k.h * 100 + '%' });
            const label = document.createElement('span');
            label.textContent = (it.kumpulan ? it.kumpulan + ' · ' : '') + it.label;
            d.append(label);
            const pemegang = document.createElement('div');
            pemegang.className = 'susun-pemegang';
            d.append(pemegang);
            halaman.appendChild(d);
        });
    };

    const pilih = (kunci, tatal) => {
        dipilih = kunci;
        binaSenarai();
        lukis();
        const el = document.querySelector(`.susun-kotak[data-kunci="${CSS.escape(kunci)}"]`);
        if (tatal && el) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    };

    // ---- interaksi tetikus / sentuhan ----
    const had = (v, min, maks) => Math.min(Math.max(v, min), maks);
    let seret = null;   // {mod: 'lukis'|'alih'|'saiz', halaman, mula, asal, it, el}
    const titik = (halaman, e) => {
        const r = halaman.getBoundingClientRect();
        return { x: had((e.clientX - r.left) / r.width, 0, 1), y: had((e.clientY - r.top) / r.height, 0, 1) };
    };

    muka.forEach((halaman) => {
        halaman.addEventListener('pointerdown', (e) => {
            if (e.button !== 0) return;
            const kotak = e.target.closest('.susun-kotak');
            const p = titik(halaman, e);
            if (kotak) {
                const it = item(kotak.dataset.kunci);
                pilih(it.kunci, false);
                seret = { mod: e.target.classList.contains('susun-pemegang') ? 'saiz' : 'alih', halaman, mula: p, asal: { ...dapat(it) }, it };
            } else {
                const it = item(dipilih);
                if (!it) { tunjuk('Pilih item di senarai sebelah kanan dahulu.', 'info'); return; }
                const el = document.createElement('div');
                el.className = 'susun-lukis';
                halaman.appendChild(el);
                seret = { mod: 'lukis', halaman, mula: p, it, el };
            }
            halaman.setPointerCapture(e.pointerId);
            e.preventDefault();
        });
        halaman.addEventListener('pointermove', (e) => {
            if (!seret || seret.halaman !== halaman) return;
            const p = titik(halaman, e);
            if (seret.mod === 'lukis') {
                const x = Math.min(p.x, seret.mula.x), y = Math.min(p.y, seret.mula.y);
                Object.assign(seret.el.style, { left: x * 100 + '%', top: y * 100 + '%',
                    width: Math.abs(p.x - seret.mula.x) * 100 + '%', height: Math.abs(p.y - seret.mula.y) * 100 + '%' });
                return;
            }
            const a = seret.asal;
            const k = seret.mod === 'alih'
                ? { ...a, x: had(a.x + p.x - seret.mula.x, 0, 1 - a.w), y: had(a.y + p.y - seret.mula.y, 0, 1 - a.h) }
                : { ...a, w: had(a.w + p.x - seret.mula.x, MIN, 1 - a.x), h: had(a.h + p.y - seret.mula.y, MIN, 1 - a.y) };
            tetapKotak(seret.it, k);
        });
        const tamat = (e) => {
            if (!seret || seret.halaman !== halaman) return;
            if (seret.mod === 'lukis') {
                const p = titik(halaman, e);
                seret.el.remove();
                const w = Math.abs(p.x - seret.mula.x), h = Math.abs(p.y - seret.mula.y);
                if (w > MIN && h > MIN) {
                    const lama = dapat(seret.it);
                    const k = { muka: +halaman.dataset.muka, x: Math.min(p.x, seret.mula.x), y: Math.min(p.y, seret.mula.y), w, h };
                    if (lama && lama.saiz) k.saiz = lama.saiz;
                    if (lama && lama.tanpa_unit) k.tanpa_unit = true;
                    tetap(seret.it, k);
                    binaSenarai();
                    lukis();
                }
            } else {
                binaSenarai();
            }
            seret = null;
        };
        halaman.addEventListener('pointerup', tamat);
        halaman.addEventListener('pointercancel', tamat);
    });

    // Kemas kini satu kotak semasa diseret tanpa membina semula semua
    const tetapKotak = (it, k) => {
        const lama = dapat(it);
        Object.assign(lama, k);
        tandaBerubah();
        const el = document.querySelector(`.susun-kotak[data-kunci="${CSS.escape(it.kunci)}"]`);
        if (el) Object.assign(el.style, { left: k.x * 100 + '%', top: k.y * 100 + '%', width: k.w * 100 + '%', height: k.h * 100 + '%' });
    };

    document.addEventListener('keydown', (e) => {
        const it = item(dipilih);
        const k = it && dapat(it);
        if (!k || ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName)) return;
        if (e.key === 'Delete' || e.key === 'Backspace') {
            tetap(it, null); lukis(); binaSenarai(); e.preventDefault(); return;
        }
        const langkah = e.shiftKey ? 0.01 : 0.002;
        const gerak = { ArrowLeft: [-langkah, 0], ArrowRight: [langkah, 0], ArrowUp: [0, -langkah], ArrowDown: [0, langkah] }[e.key];
        if (gerak) {
            tetapKotak(it, { ...k, x: had(k.x + gerak[0], 0, 1 - k.w), y: had(k.y + gerak[1], 0, 1 - k.h) });
            e.preventDefault();
        }
    });

    // ---- simpan & pratonton ----
    const mesej = document.getElementById('mesejSusun');
    const tunjuk = (teks, jenis) => {
        mesej.className = 'alert alert-' + (jenis === 'ok' ? 'success' : jenis === 'info' ? 'info' : 'danger');
        mesej.textContent = teks;
    };
    const simpan = async () => {
        const r = await fetch(URL_SIMPAN, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ kedudukan: K }),
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data.mesej || data.message || 'Susunan tidak dapat disimpan.');
        K = data.kedudukan; normal();
        berubah = false;
        binaSenarai(); lukis();
        tunjuk(data.mesej, 'ok');
    };
    document.getElementById('butangSimpan').addEventListener('click', () => simpan().catch(e => tunjuk(e.message, 'ralat')));
    document.getElementById('butangPratonton').addEventListener('click', async () => {
        const tetingkap = window.open('about:blank', '_blank');
        try {
            if (berubah && !SEKAT) await simpan();
            if (tetingkap) tetingkap.location = URL_PRATONTON; else window.location = URL_PRATONTON;
        } catch (e) {
            tetingkap?.close();
            tunjuk(e.message, 'ralat');
        }
    });

    // ---- cadangan automatik: isi item yang BELUM diletak sahaja ----
    const butangCadang = document.getElementById('butangCadang');
    butangCadang.addEventListener('click', async () => {
        butangCadang.disabled = true;
        tunjuk('Membaca templat dan mencari label medan... (beberapa saat)', 'info');
        try {
            const r = await fetch(URL_CADANG, { headers: { 'Accept': 'application/json' } });
            const data = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(data.mesej || 'Cadangan automatik gagal.');
            const C = data.kedudukan || {};
            let diisi = 0;
            ITEM.forEach((it) => {
                if (dapat(it)) return;
                const cm = it.medan ? (C.medan || {})[it.medan] || {} : {};
                const k = it.jenis === 'asas' ? (C.asas || {})[it.kod]
                    : it.jenis === 'kotak' ? cm.kotak
                    : it.jenis === 'pilihan' ? (cm.pilihan || {})[it.pilihan]
                    : it.jenis === 'baris' ? cm.baris
                    : it.jenis === 'lajur' ? (cm.lajur || {})[it.kod] : null;
                if (!k) return;
                tetap(it, { ...k });
                if (it.jenis === 'baris' && k.bilangan) medan(it, true).baris.bilangan = k.bilangan;
                if (it.jenis === 'pilihan' && cm.gaya) medan(it, true).gaya = cm.gaya;
                diisi++;
            });
            binaSenarai(); lukis();
            const tidak = (data.tidak || []).length ? ' Tidak dijumpai: ' + data.tidak.join(', ') + '.' : '';
            tunjuk(`${diisi} kotak dicadangkan. Semak setiap kotak, betulkan jika perlu, kemudian Simpan.${tidak}`, diisi ? 'ok' : 'info');
        } catch (e) {
            tunjuk(e.message, 'ralat');
        } finally {
            butangCadang.disabled = false;
        }
    });

    binaSenarai();
    lukis();
})();
</script>
@endpush
