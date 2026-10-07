@extends('layouts.app')

@section('title', 'Edit Bahagian')

@section('content')

<!-- Bahagian Pengenalan Halaman -->
<div class="page-intro">
    <div>
        <h2>Kemaskini Bahagian</h2>
        <p>Kemaskini kod dan nama bahagian <strong>{{ $bahagian->nama_bahagian }}</strong>.</p>
    </div>
    <div>
        <a href="{{ route('bahagian.index') }}" class="outline-button text-decoration-none">
            <i class="bi bi-arrow-left"></i> <span>Kembali ke Senarai</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Borang Utama -->
    <div class="col-lg-8">
        <div class="glass-panel form-panel">
            <div class="section-header px-4 pt-4 pb-3">
                <div>
                    <h3>Maklumat Bahagian</h3>
                    <p>Sila lengkapkan medan bertanda <span class="text-danger">*</span></p>
                </div>
            </div>

            <form action="{{ route('bahagian.update', $bahagian->bahagian_id) }}" method="POST" id="bahagianForm" class="px-4 pb-4">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="dms-label">Kod Bahagian <span class="text-danger">*</span></label>
                    <input type="text"
                           name="kod_bahagian"
                           id="kod_bahagian"
                           class="dms-input @error('kod_bahagian') is-invalid @enderror"
                           value="{{ old('kod_bahagian', $bahagian->kod_bahagian) }}"
                           placeholder="Contoh: BTM"
                           maxlength="10"
                           autocomplete="off"
                           required>
                    <div class="dms-hint">Kod ringkas, huruf besar sahaja (cth: BTM, HR, KEW)</div>
                    @error('kod_bahagian') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <div class="mb-2">
                    <label class="dms-label">Nama Bahagian <span class="text-danger">*</span></label>
                    <input type="text"
                           name="nama_bahagian"
                           id="nama_bahagian"
                           class="dms-input @error('nama_bahagian') is-invalid @enderror"
                           value="{{ old('nama_bahagian', $bahagian->nama_bahagian) }}"
                           placeholder="Contoh: Bahagian Teknologi Maklumat"
                           maxlength="150"
                           autocomplete="off"
                           required>
                    <div class="d-flex justify-content-between">
                        <div class="dms-hint">Nama penuh bahagian mengikut struktur organisasi</div>
                        <div class="dms-charcount"><span id="charCount">{{ strlen($bahagian->nama_bahagian) }}</span>/150</div>
                    </div>
                    @error('nama_bahagian') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <hr class="dms-divider">

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route('bahagian.index') }}" class="outline-button text-decoration-none">
                        <span>Batal</span>
                    </a>
                    <button type="submit" class="btn text-white save-btn">
                        <i class="bi bi-check2-circle me-2"></i> Kemaskini Rekod
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Panel Bantuan / Pratonton -->
    <div class="col-lg-4">
        <div class="glass-panel preview-panel mb-4">
            <div class="summary-head px-1">
                <div>
                    <div class="summary-label">PRATONTON REKOD</div>
                </div>
                <div class="summary-icon"><i class="bi bi-eye"></i></div>
            </div>
            <div class="preview-row">
                <span class="badge kod-badge" id="previewKod">{{ $bahagian->kod_bahagian }}</span>
                <span class="preview-name" id="previewNama">{{ $bahagian->nama_bahagian }}</span>
            </div>
            <div class="dms-hint mt-2">Begini bahagian anda akan kelihatan dalam senarai.</div>
        </div>

        <div class="glass-panel tips-panel">
            <div class="summary-head px-1">
                <div>
                    <div class="summary-label">MAKLUMAT REKOD</div>
                </div>
                <div class="summary-icon" style="color: var(--dms-mint); background: rgba(37,169,121,.12);"><i class="bi bi-info-circle"></i></div>
            </div>
            <ul class="meta-list">
                <li>
                    <span>ID Bahagian</span>
                    <strong>#{{ $bahagian->bahagian_id }}</strong>
                </li>
                @if($bahagian->created_at)
                <li>
                    <span>Didaftarkan</span>
                    <strong>{{ $bahagian->created_at->translatedFormat('d M Y') }}</strong>
                </li>
                @endif
                @if($bahagian->updated_at)
                <li>
                    <span>Kemaskini Terakhir</span>
                    <strong>{{ $bahagian->updated_at->diffForHumans() }}</strong>
                </li>
                @endif
            </ul>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .form-panel, .preview-panel, .tips-panel { padding: 0; }
    .preview-panel, .tips-panel { padding: 20px; }

    .section-header { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; border-bottom: 1px solid var(--dms-line); }
    .section-header h3 { margin: 0; color: var(--dms-brand); font-size: 16px; font-weight: 800; }
    .section-header p { margin: 4px 0 0; color: var(--dms-muted); font-size: 11px; }

    .dms-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--dms-text); margin-bottom: 6px; }
    .dms-input {
        width: 100%;
        padding: 11px 14px;
        border: 1px solid var(--dms-line);
        border-radius: 10px;
        background: var(--dms-surface-strong);
        font-size: 13.5px;
        color: var(--dms-text);
        outline: none;
        transition: border-color .2s ease, box-shadow .2s ease;
    }
    .dms-input:focus { border-color: var(--dms-primary); box-shadow: 0 0 0 3px rgba(23,105,210,.12); }
    .dms-input.is-invalid { border-color: var(--dms-danger); }
    .dms-hint { font-size: 11px; color: var(--dms-muted); margin-top: 6px; }
    .dms-charcount { font-size: 11px; color: var(--dms-muted); margin-top: 6px; }
    .dms-error { font-size: 11.5px; color: var(--dms-danger); font-weight: 600; margin-top: 6px; }
    .dms-divider { border-top: 1px solid var(--dms-line); opacity: 1; margin: 8px 0; }

    #kod_bahagian { text-transform: uppercase; letter-spacing: .5px; font-weight: 700; }

    .save-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none;
        border-radius: 10px;
        font-weight: 700;
        padding: 10px 22px;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .save-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(23,105,210,.28); color: #fff; }

    .preview-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 14px;
        padding: 14px;
        border: 1px dashed var(--dms-line);
        border-radius: 10px;
        background: rgba(23, 105, 210, .03);
    }
    .kod-badge {
        background: rgba(23, 105, 210, .1);
        color: var(--dms-primary);
        font-weight: 800;
        padding: 7px 10px;
        border-radius: 6px;
        letter-spacing: .3px;
        white-space: nowrap;
    }
    .preview-name { font-size: 13px; font-weight: 700; color: var(--dms-brand); }

    .meta-list { list-style: none; padding: 0; margin: 14px 0 0; display: flex; flex-direction: column; gap: 12px; }
    .meta-list li { display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding-bottom: 10px; border-bottom: 1px dashed var(--dms-line); }
    .meta-list li:last-child { border-bottom: none; padding-bottom: 0; }
    .meta-list li span { color: var(--dms-muted); }
    .meta-list li strong { color: var(--dms-brand); font-weight: 700; }
</style>
@endpush

@push('scripts')
<script>
    (() => {
        const kodInput = document.getElementById('kod_bahagian');
        const namaInput = document.getElementById('nama_bahagian');
        const previewKod = document.getElementById('previewKod');
        const previewNama = document.getElementById('previewNama');
        const charCount = document.getElementById('charCount');

        function updatePreview() {
            previewKod.textContent = kodInput.value.trim() ? kodInput.value.trim().toUpperCase() : 'KOD';
            previewNama.textContent = namaInput.value.trim() ? namaInput.value.trim() : 'Nama bahagian akan dipaparkan di sini';
        }

        if (kodInput) {
            kodInput.addEventListener('input', () => {
                kodInput.value = kodInput.value.toUpperCase();
                updatePreview();
            });
        }

        if (namaInput) {
            namaInput.addEventListener('input', () => {
                charCount.textContent = namaInput.value.length;
                updatePreview();
            });
        }
    })();
</script>
@endpush