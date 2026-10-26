@extends('layouts.app')

{{-- Dikongsi Imbas & Arkib ($mod = 'arkib') dan Imbas & Hantar ($mod = 'hantar') --}}
@php $hantar = $mod === 'hantar'; @endphp

@section('title', 'Semak Hasil Imbasan')

@section('content')

<div class="page-intro">
    <div>
        <h2>Semak &amp; Sahkan</h2>
        <p>{{ $jenisDokumen->nama_dokumen }} &middot; {{ $namaAsal }}</p>
    </div>
    <div>
        <a href="{{ route("imbas-{$mod}.create") }}" class="outline-button text-decoration-none">
            <i class="bi bi-arrow-left"></i> <span>Mula Semula</span>
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success"><i class="bi bi-check2-circle me-1"></i> {{ session('success') }}</div>
@endif

@if($mesejAmaran)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i> {{ $mesejAmaran }}
    </div>
@elseif($skorOcr !== null)
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        Skor keyakinan OCR: <strong>{{ $skorOcr }}%</strong>.
        @if(isset($masa['ocr']))
            <span class="masa-proses">Diproses dalam <strong>{{ number_format(($masa['ocr'] ?? 0) + ($masa['ai'] ?? 0), 1) }} saat</strong>
                (OCR {{ number_format($masa['ocr'], 1) }} s{{ isset($masa['ai']) ? ', AI ' . number_format($masa['ai'], 1) . ' s' : '' }}).</span>
        @endif
        @if($mukaVlm)
            <div class="mt-1"><i class="bi bi-pen me-1"></i>
                Muka surat {{ implode(', ', $mukaVlm) }} dibaca dengan <strong>AI penglihatan</strong> (tulisan tangan atau teks sukar dikesan) —
                <strong>sila semak teliti</strong> setiap medan berbanding fail imbasan.</div>
        @elseif($skorOcr < 70)
            Skor rendah — <strong>sila semak teliti</strong> setiap medan di bawah berbanding fail imbasan.
        @else
            Sila tetap semak setiap medan sebelum simpan.
        @endif
    </div>
@endif

@if($bolehBacaSemula)
    {{-- Tulisan tangan tidak dikesan secara automatik: staf boleh paksa bacaan model penglihatan --}}
    <form action="{{ route("imbas-{$mod}.baca-semula", $token) }}" method="POST" class="baca-semula mb-3" id="formBacaSemula">
        @csrf
        <span class="dms-hint m-0"><i class="bi bi-pen me-1"></i> Medan kosong atau salah kerana borang ditulis tangan? Baca semula dengan AI penglihatan (±30 saat). Nilai yang telah anda ubah akan diganti.</span>
        <button type="submit" class="outline-button" id="btnBacaSemula">
            <i class="bi bi-arrow-repeat"></i> <span>Baca semula (tulisan tangan)</span>
        </button>
    </form>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-6 col-xxl-7">
        <div class="glass-panel form-panel">
            <div class="section-header px-4 pt-4 pb-3">
                <h3>Langkah 2: Semak &amp; Sahkan Medan</h3>
            </div>

            {{-- Jenis dokumen, fail dan hasil OCR disimpan di pelayan (folder token), bukan dalam borang --}}
            <form action="{{ route("imbas-{$mod}.simpan", $token) }}" method="POST" class="px-4 pb-4" id="formSimpan">
                @csrf

                <div class="mb-4">
                    <label class="dms-label">Tarikh Dokumen <span class="text-danger">*</span>
                        @if($tarikhAi)<span class="badge tag-ai">Diisi AI</span>@endif
                    </label>
                    <input type="date" name="tarikh_dokumen" class="dms-input @error('tarikh_dokumen') is-invalid @enderror" value="{{ old('tarikh_dokumen', $tarikhAi ?? date('Y-m-d')) }}">
                    @unless($tarikhAi)<div class="dms-hint">Tarikh dokumen tidak dikesan; tarikh hari ini digunakan. Sila semak.</div>@endunless
                    @error('tarikh_dokumen') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <div class="mb-2">
                    <label class="dms-label">Perkara <span class="dms-optional">(Pilihan)</span>
                        @if($perkaraAi)<span class="badge tag-ai">Diisi AI</span>@endif
                    </label>
                    <input type="text" name="perkara" class="dms-input" value="{{ old('perkara', $perkaraAi) }}" maxlength="255" placeholder="Ringkasan perkara dokumen">
                </div>

                <hr class="dms-divider">

                <label class="dms-label mb-2">Medan Dokumen — diisi oleh AI, sila semak</label>

                @include('dokumen.partials.medan-senarai', [
                    'medanSenarai' => $jenisDokumen->fields,
                    'nilaiSedia' => $nilaiEkstrak,
                    'diisiAi' => true,
                    'nilaiAutoIsi' => $nilaiAutoIsi,
                    'medanPejabat' => $hantar ? 'sembunyi' : null,
                ])

                <hr class="dms-divider">

                @if($hantar)
                    <label class="pengesahan-kotak @error('pengesahan') is-invalid @enderror">
                        <input type="checkbox" name="pengesahan" value="1" {{ old('pengesahan') ? 'checked' : '' }} required>
                        <span>Saya sahkan maklumat di atas sepadan dengan borang fizikal yang saya isi.</span>
                    </label>
                    @error('pengesahan') <div class="dms-error">{{ $message }}</div> @enderror
                @endif

                <div class="status-selepas mt-3">
                    <i class="bi {{ $hantar ? 'bi-hourglass-split' : 'bi-patch-check' }}"></i>
                    @if($hantar)
                        <span>Selepas dihantar, borang berstatus <strong>"Dalam Proses"</strong> dan menunggu Penyokong, kemudian Pelulus. Medan bertanda "Daripada profil" diisi daripada profil anda.</span>
                    @else
                        <span>Dokumen ini akan terus bertanda <strong>"Diluluskan"</strong>, kerana ia arkib dokumen yang sedia lulus secara fizikal, bukan permohonan kelulusan baharu.</span>
                    @endif
                </div>

                <div class="d-flex justify-content-end align-items-center flex-wrap gap-2 mt-3">
                    <a href="{{ route("imbas-{$mod}.create") }}" class="outline-button text-decoration-none"><span>Batal</span></a>
                    <button type="submit" id="btnSimpan" class="btn text-white save-btn">
                        @if($hantar)
                            <i class="bi bi-send me-2"></i> Sahkan &amp; Hantar
                        @else
                            <i class="bi bi-check2-circle me-2"></i> Sahkan &amp; Simpan (Diluluskan)
                        @endif
                    </button>
                </div>
            </form>
        </div>

        @if($teksOcr)
        <div class="glass-panel tips-panel mt-4">
            <details>
                <summary class="summary-label">TEKS OCR PENUH</summary>
                <div class="dms-hint mt-2 mb-2">Teks yang dibaca daripada imbasan, untuk rujukan.</div>
                <pre class="ocr-preview">{{ $teksOcr }}</pre>
            </details>
        </div>
        @endif
    </div>

    <div class="col-lg-6 col-xxl-5">
        <div class="pratonton-lekat">
            {{-- Fail imbasan sementara (hanya pemilik token boleh buka), untuk banding dengan medan di sebelah --}}
            <div class="glass-panel tips-panel">
                <div class="summary-head px-1">
                    <div><div class="summary-label">FAIL IMBASAN</div></div>
                    <a href="{{ route("imbas-{$mod}.pratonton", $token) }}" target="_blank" rel="noopener" class="summary-icon text-decoration-none" title="Buka dalam tab baharu">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                @if($pratontonImej)
                    <img src="{{ route("imbas-{$mod}.pratonton", $token) }}" alt="Imbasan {{ $namaAsal }}" class="pratonton-fail mt-2">
                @else
                    <iframe src="{{ route("imbas-{$mod}.pratonton", $token) }}" title="Imbasan {{ $namaAsal }}" class="pratonton-fail mt-2"></iframe>
                @endif
            </div>

        </div>
    </div>
</div>

@endsection

@push('styles')
@include('dokumen.partials.medan-gaya')
<style>
    .form-panel, .tips-panel { padding: 0; }
    .tips-panel { padding: 18px; }
    .section-header { border-bottom: 1px solid var(--dms-line); }
    .section-header h3 { margin: 0; color: var(--dms-brand); font-size: 16px; font-weight: 800; }
    .dms-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--dms-text); margin-bottom: 6px; }
    .dms-optional { font-weight: 500; color: var(--dms-muted); font-size: 11.5px; }
    .dms-input {
        width: 100%; padding: 11px 14px; border: 1px solid var(--dms-line); border-radius: 10px;
        background: var(--dms-surface-strong); font-size: 13.5px; color: var(--dms-text); outline: none;
    }
    .dms-input.is-invalid { border-color: var(--dms-danger); }
    .dms-hint { font-size: 11px; color: var(--dms-muted); }
    .dms-error { font-size: 11.5px; color: var(--dms-danger); font-weight: 600; margin-top: 6px; }
    .dms-divider { border-top: 1px solid var(--dms-line); opacity: 1; margin: 8px 0; }
    .save-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none; border-radius: 10px; font-weight: 700; padding: 10px 22px;
    }
    .save-btn:hover { color: #fff; }
    .save-btn:disabled { opacity: .7; cursor: wait; }
    .pengesahan-kotak {
        display: flex; align-items: flex-start; gap: 10px; margin-top: 12px; padding: 12px 14px;
        border: 1px solid var(--dms-line); border-radius: 10px; background: rgba(23,105,210,.04);
        font-size: 13px; font-weight: 600; color: var(--dms-text); cursor: pointer;
    }
    .pengesahan-kotak input { margin-top: 3px; width: 16px; height: 16px; flex-shrink: 0; }
    .pengesahan-kotak.is-invalid { border-color: var(--dms-danger); }
    /* Pratonton kekal kelihatan semasa skrol medan (skrin lebar sahaja) */
    .masa-proses { margin-left: 4px; }
    .baca-semula { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px;
        padding: 10px 14px; border: 1px dashed var(--dms-line); border-radius: 10px; background: rgba(23,105,210,.03); }
    .baca-semula .outline-button { white-space: nowrap; }
    .status-selepas { display: flex; gap: 8px; align-items: flex-start; font-size: 11.5px; color: var(--dms-muted); }
    .status-selepas i { color: var(--dms-mint); margin-top: 1px; }
    /* Pratonton kekal kelihatan semasa skrol medan (skrin lebar sahaja); di bawah topbar 80px */
    @media (min-width: 992px) {
        .pratonton-lekat { position: sticky; top: 96px; }
        iframe.pratonton-fail { height: calc(100vh - 190px); }
    }
    .pratonton-fail {
        display: block; width: 100%; height: 70vh; min-height: 360px; border: 1px solid var(--dms-line);
        border-radius: 10px; background: #fff;
    }
    img.pratonton-fail { height: auto; min-height: 0; max-height: 70vh; object-fit: contain; }
    details summary { cursor: pointer; }
    .ocr-preview {
        margin: 0; max-height: 420px; overflow: auto; font-size: 11.5px;
        white-space: pre-wrap; word-break: break-word; color: var(--dms-text);
        font-family: inherit; background: rgba(23,105,210,.03); border: 1px dashed var(--dms-line);
        border-radius: 10px; padding: 10px 12px;
    }
</style>
@endpush

@push('scripts')
@include('dokumen.partials.medan-skrip')
<script>
    // Elak klik dua kali (pelayan juga menyekat simpan berganda melalui token)
    document.getElementById('formSimpan').addEventListener('submit', function () {
        const btn = document.getElementById('btnSimpan');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan...';
    });

    // Baca semula: kunci kedua-dua butang dan papar masa berlalu
    document.getElementById('formBacaSemula')?.addEventListener('submit', function () {
        const btn = document.getElementById('btnBacaSemula');
        btn.disabled = true;
        document.getElementById('btnSimpan').disabled = true;
        const mula = Date.now();
        const papar = () => {
            btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> <span>Membaca semula... ${Math.floor((Date.now() - mula) / 1000)} s</span>`;
        };
        papar();
        setInterval(papar, 1000);
    });
</script>
@endpush
