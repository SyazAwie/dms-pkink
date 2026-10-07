@extends('layouts.app')

@section('title', 'Kemaskini Dokumen')

@section('content')

<div class="page-intro">
    <div>
        <h2>Kemaskini Dokumen</h2>
        <p>Kemaskini maklumat dokumen <strong>{{ $dokumen->no_rujukan }}</strong>.</p>
    </div>
    <div>
        <a href="{{ route('dokumen.show', $dokumen->dokumen_id) }}" class="outline-button text-decoration-none">
            <i class="bi bi-arrow-left"></i> <span>Kembali ke Butiran</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="glass-panel form-panel">
            <div class="section-header px-4 pt-4 pb-3">
                <div>
                    <h3>Maklumat Dokumen</h3>
                    <p>Jenis Dokumen dan No. Rujukan dikunci dan tidak boleh diubah.</p>
                </div>
            </div>

            <form action="{{ route('dokumen.update', $dokumen->dokumen_id) }}" method="POST" id="dokumenForm" class="px-4 pb-4" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="dms-label">No. Rujukan</label>
                        <input type="text" class="dms-input dms-locked" value="{{ $dokumen->no_rujukan }}" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="dms-label">Jenis Dokumen</label>
                        <input type="text" class="dms-input dms-locked" value="{{ $dokumen->jenisDokumen->nama_dokumen ?? '-' }}" disabled>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="dms-label">Tarikh Dokumen <span class="text-danger">*</span></label>
                    <input type="date"
                           name="tarikh_dokumen"
                           class="dms-input @error('tarikh_dokumen') is-invalid @enderror"
                           value="{{ old('tarikh_dokumen', $dokumen->tarikh_dokumen?->format('Y-m-d')) }}">
                    <div class="dms-hint">Mengubah tarikh tidak menukar No. Rujukan sedia ada.</div>
                    @error('tarikh_dokumen') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <div class="mb-2">
                    <label class="dms-label">Perkara <span class="dms-optional">(Pilihan)</span></label>
                    <input type="text"
                           name="perkara"
                           class="dms-input @error('perkara') is-invalid @enderror"
                           value="{{ old('perkara', $dokumen->perkara) }}"
                           maxlength="255">
                    @error('perkara') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <hr class="dms-divider">

                <!-- ============ Medan Dokumen (EAV) ============ -->
                <label class="dms-label mb-2">Medan Dokumen</label>

                @forelse($dokumen->jenisDokumen->fields as $field)
                    @php
                        $fid = $field->dokumen_field_id;
                        $nama = "medan_data[{$fid}]";
                        $nilai = old("medan_data.{$fid}", $nilaiSedia[$fid] ?? '');
                        $wajib = $field->is_required ? 'required' : '';
                        $kelasRalat = $errors->has("medan_data.{$fid}") ? 'is-invalid' : '';
                    @endphp
                    <div class="mb-3">
                        <label class="dms-label">
                            {{ $field->nama_field }}
                            @if($field->is_required)<span class="text-danger">*</span>@endif
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
                                    @if($nilai !== '' && !in_array($nilai, $pilihan))
                                        <option value="{{ $nilai }}" selected>{{ $nilai }} (nilai lama)</option>
                                    @endif
                                </select>
                                @break
                            @case('textarea')
                                <textarea name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" rows="3" {{ $wajib }}>{{ $nilai }}</textarea>
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

                <!-- ============ Fail Imbasan ============ -->
                <label class="dms-label mb-2">Fail Imbasan Sedia Ada</label>
                @foreach($dokumen->scans as $scan)
                    <div class="fail-baris">
                        <i class="bi bi-file-earmark-text"></i>
                        <a href="{{ route('dokumen.scan.papar', $scan->dokumen_scan_id) }}" target="_blank" class="fail-nama text-decoration-none">{{ $scan->nama_fail }}</a>
                        <label class="fail-buang">
                            <input type="checkbox" name="hapus_scan[]" value="{{ $scan->dokumen_scan_id }}"
                                   {{ in_array($scan->dokumen_scan_id, old('hapus_scan', [])) ? 'checked' : '' }}>
                            <span>Buang</span>
                        </label>
                    </div>
                @endforeach

                <div class="mt-3 mb-2">
                    <label class="dms-label">Tambah Fail Imbasan Baharu <span class="dms-optional">(Pilihan)</span></label>
                    <input type="file"
                           name="fail_scan[]"
                           class="dms-input @error('fail_scan') is-invalid @enderror"
                           accept=".pdf,.jpg,.jpeg,.png"
                           multiple>
                    <div class="dms-hint">PDF, JPG, PNG. Maksimum 10MB setiap fail. Dokumen mesti kekal ada sekurang-kurangnya satu fail.</div>
                    @error('fail_scan') <div class="dms-error">{{ $message }}</div> @enderror
                    @error('fail_scan.*') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <hr class="dms-divider">

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route('dokumen.show', $dokumen->dokumen_id) }}" class="outline-button text-decoration-none">
                        <span>Batal</span>
                    </a>
                    <button type="submit" class="btn text-white save-btn">
                        <i class="bi bi-check2-circle me-2"></i> Kemaskini Dokumen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass-panel tips-panel">
            <div class="summary-head px-1">
                <div><div class="summary-label">MAKLUMAT REKOD</div></div>
                <div class="summary-icon" style="color: var(--dms-mint); background: rgba(37,169,121,.12);"><i class="bi bi-info-circle"></i></div>
            </div>
            <ul class="meta-list">
                <li><span>Bahagian</span><strong>{{ $dokumen->bahagian->nama_bahagian ?? '-' }}</strong></li>
                <li><span>Dimuat Naik Oleh</span><strong>{{ $dokumen->pemuatNaik->nama_staff ?? '-' }}</strong></li>
                <li><span>Didaftarkan</span><strong>{{ $dokumen->created_at?->translatedFormat('d M Y') }}</strong></li>
                <li><span>Kemaskini Terakhir</span><strong>{{ $dokumen->updated_at?->diffForHumans() }}</strong></li>
            </ul>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .form-panel, .tips-panel { padding: 0; }
    .tips-panel { padding: 20px; }

    .section-header { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; border-bottom: 1px solid var(--dms-line); }
    .section-header h3 { margin: 0; color: var(--dms-brand); font-size: 16px; font-weight: 800; }
    .section-header p { margin: 4px 0 0; color: var(--dms-muted); font-size: 11px; }

    .dms-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--dms-text); margin-bottom: 6px; }
    .dms-optional { font-weight: 500; color: var(--dms-muted); font-size: 11.5px; }
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
    .dms-locked { background: rgba(23, 105, 210, .04); color: var(--dms-muted); font-weight: 700; cursor: not-allowed; }
    .dms-hint { font-size: 11px; color: var(--dms-muted); margin-top: 6px; }
    .dms-error { font-size: 11.5px; color: var(--dms-danger); font-weight: 600; margin-top: 6px; }
    .dms-divider { border-top: 1px solid var(--dms-line); opacity: 1; margin: 8px 0; }

    .save-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none;
        border-radius: 10px;
        font-weight: 700;
        padding: 10px 22px;
        box-shadow: 0 8px 20px rgba(23, 105, 210, .2);
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .save-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(23,105,210,.28); color: #fff; }

    .fail-baris {
        display: flex; align-items: center; gap: 10px;
        padding: 12px; border: 1px solid var(--dms-line); border-radius: 10px;
        margin-bottom: 10px; color: var(--dms-text);
    }
    .fail-nama { flex: 1; font-size: 13px; font-weight: 600; color: var(--dms-text); }
    .fail-nama:hover { color: var(--dms-primary); }
    .fail-buang {
        display: flex; align-items: center; gap: 6px; cursor: pointer;
        font-size: 12px; font-weight: 700; color: var(--dms-danger);
    }

    .meta-list { list-style: none; padding: 0; margin: 14px 0 0; display: flex; flex-direction: column; gap: 12px; }
    .meta-list li { display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding-bottom: 10px; border-bottom: 1px dashed var(--dms-line); }
    .meta-list li:last-child { border-bottom: none; padding-bottom: 0; }
    .meta-list li span { color: var(--dms-muted); }
    .meta-list li strong { color: var(--dms-brand); font-weight: 700; }
</style>
@endpush