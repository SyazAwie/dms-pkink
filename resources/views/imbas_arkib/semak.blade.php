@extends('layouts.app')

@section('title', 'Semak Hasil Imbasan')

@section('content')

<div class="page-intro">
    <div>
        <h2>Semak &amp; Sahkan</h2>
        <p>{{ $jenisDokumen->nama_dokumen }} &middot; {{ $namaAsal }}</p>
    </div>
    <div>
        <a href="{{ route('imbas-arkib.create') }}" class="outline-button text-decoration-none">
            <i class="bi bi-arrow-left"></i> <span>Mula Semula</span>
        </a>
    </div>
</div>

@if($mesejAmaran)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i> {{ $mesejAmaran }}
    </div>
@elseif($skorOcr !== null)
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        Skor keyakinan OCR: <strong>{{ $skorOcr }}%</strong>.
        @if($skorOcr < 70)
            Skor rendah — <strong>sila semak teliti</strong> setiap medan di bawah berbanding teks asal.
        @else
            Sila tetap semak setiap medan sebelum simpan.
        @endif
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="glass-panel form-panel">
            <div class="section-header px-4 pt-4 pb-3">
                <h3>Langkah 2: Semak &amp; Sahkan Medan</h3>
            </div>

            <form action="{{ route('imbas-arkib.simpan') }}" method="POST" class="px-4 pb-4">
                @csrf
                <input type="hidden" name="jenis_dokumen_id" value="{{ $jenisDokumen->jenis_dokumen_id }}">
                <input type="hidden" name="laluan_sementara" value="{{ $laluanSementara }}">
                <input type="hidden" name="nama_asal" value="{{ $namaAsal }}">
                <input type="hidden" name="teks_ocr" value="{{ $teksOcr }}">
                <input type="hidden" name="skor_ocr" value="{{ $skorOcr }}">

                <div class="mb-4">
                    <label class="dms-label">Tarikh Dokumen <span class="text-danger">*</span></label>
                    <input type="date" name="tarikh_dokumen" class="dms-input @error('tarikh_dokumen') is-invalid @enderror" value="{{ old('tarikh_dokumen', date('Y-m-d')) }}">
                    @error('tarikh_dokumen') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <div class="mb-2">
                    <label class="dms-label">Perkara <span class="dms-optional">(Pilihan)</span></label>
                    <input type="text" name="perkara" class="dms-input" value="{{ old('perkara') }}" maxlength="255" placeholder="Ringkasan perkara dokumen">
                </div>

                <hr class="dms-divider">

                <label class="dms-label mb-2">Medan Dokumen — disahkan oleh AI, sila semak</label>

                @forelse($jenisDokumen->fields as $field)
                    @php
                        $fid = $field->dokumen_field_id;
                        $nama = "medan_data[{$fid}]";
                        $nilai = old("medan_data.{$fid}", $nilaiEkstrak[$fid] ?? '');
                        $wajib = $field->is_required ? 'required' : '';
                        $kelasRalat = $errors->has("medan_data.{$fid}") ? 'is-invalid' : '';
                        $diisiAi = ($nilaiEkstrak[$fid] ?? null) !== null;
                    @endphp
                    <div class="mb-3">
                        <label class="dms-label">
                            {{ $field->nama_field }}
                            @if($field->is_required)<span class="text-danger">*</span>@endif
                            @if($diisiAi)<span class="badge tag-ai">Diisi AI</span>@endif
                        </label>

                        @switch($field->jenis_data)
                            @case('nombor')
                                <input type="number" step="0.01" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }}>
                                @break
                            @case('tarikh')
                                <input type="date" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }}>
                                @break
                            @case('dropdown')
                                @php $pilihan = $field->pilihan ?? []; @endphp
                                <select name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" {{ $wajib }}>
                                    <option value="">Pilih...</option>
                                    @foreach($pilihan as $p)
                                        <option value="{{ $p }}" {{ (string) $nilai === (string) $p ? 'selected' : '' }}>{{ $p }}</option>
                                    @endforeach
                                </select>
                                @break
                            @case('textarea')
                                <textarea name="{{ $nama }}" class="dms-input" rows="3" {{ $wajib }}>{{ $nilai }}</textarea>
                                @break
                            @default
                                <input type="text" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }}>
                        @endswitch

                        @error("medan_data.{$fid}") <div class="dms-error">{{ $message }}</div> @enderror
                    </div>
                @empty
                    <div class="dms-hint py-2">Jenis Dokumen ini tiada medan didaftarkan.</div>
                @endforelse

                <hr class="dms-divider">

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route('imbas-arkib.create') }}" class="outline-button text-decoration-none"><span>Batal</span></a>
                    <button type="submit" class="btn text-white save-btn">
                        <i class="bi bi-check2-circle me-2"></i> Sahkan &amp; Simpan (Diluluskan)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass-panel tips-panel mb-4">
            <div class="summary-head px-1">
                <div><div class="summary-label">STATUS SELEPAS SIMPAN</div></div>
                <div class="summary-icon" style="color: var(--dms-mint); background: rgba(37,169,121,.12);"><i class="bi bi-patch-check"></i></div>
            </div>
            <p class="dms-hint mt-2 mb-0">Dokumen ini akan terus bertanda <strong>"Diluluskan"</strong> — sebab ia arkib dokumen yang sedia lulus secara fizikal, bukan permohonan kelulusan baharu.</p>
        </div>

        @if($teksOcr)
        <div class="glass-panel tips-panel">
            <div class="summary-head px-1">
                <div><div class="summary-label">TEKS OCR PENUH</div></div>
                <div class="summary-icon"><i class="bi bi-text-paragraph"></i></div>
            </div>
            <div class="dms-hint mt-1 mb-2">Rujuk teks asal untuk sahkan nilai di sebelah betul.</div>
            <pre class="ocr-preview">{{ $teksOcr }}</pre>
        </div>
        @endif
    </div>
</div>

@endsection

@push('styles')
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
    .tag-ai {
        background: rgba(124, 58, 237, .12); color: #7c3aed;
        font-size: 9.5px; font-weight: 800; padding: 2px 7px; border-radius: 999px; margin-left: 4px;
        text-transform: uppercase; letter-spacing: .3px;
    }
    .ocr-preview {
        margin: 0; max-height: 420px; overflow: auto; font-size: 11.5px;
        white-space: pre-wrap; word-break: break-word; color: var(--dms-text);
        font-family: inherit; background: rgba(23,105,210,.03); border: 1px dashed var(--dms-line);
        border-radius: 10px; padding: 10px 12px;
    }
</style>
@endpush