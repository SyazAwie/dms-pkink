@extends('layouts.app')

@section('title', 'Butiran Dokumen')

@section('content')

<div class="page-intro">
    <div>
        <h2>{{ $dokumen->no_rujukan }}</h2>
        <p>{{ $dokumen->jenisDokumen->nama_dokumen ?? '-' }} &middot; {{ $dokumen->tarikh_dokumen?->translatedFormat('d M Y') }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('dokumen.index') }}" class="outline-button text-decoration-none">
            <i class="bi bi-arrow-left"></i> <span>Kembali ke Senarai</span>
        </a>
        @can('update', $dokumen)
        <a href="{{ route('dokumen.edit', $dokumen->dokumen_id) }}" class="outline-button text-decoration-none">
            <i class="bi bi-pencil-square"></i> <span>Kemaskini</span>
        </a>
        @endcan
        @can('delete', $dokumen)
        <form action="{{ route('dokumen.destroy', $dokumen->dokumen_id) }}" method="POST" class="d-inline"
              onsubmit="return confirm('Padam dokumen {{ $dokumen->no_rujukan }}?\nDokumen akan dialih keluar daripada senarai, tetapi rekod arkib dikekalkan.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="outline-button btn-padam-outline">
                <i class="bi bi-trash3"></i> <span>Padam</span>
            </button>
        </form>
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="glass-panel">
            <div class="section-header px-4 pt-4 pb-3">
                <h3>Medan Dokumen</h3>
            </div>
            <div class="px-4 pb-4">
                @forelse($dokumen->data as $nilai)
                <div class="medan-baris">
                    <span class="medan-nama">{{ $nilai->field->nama_field ?? $nilai->dokumen_field_id }}</span>
                    <span class="medan-nilai">{{ $nilai->nilai_data }}</span>
                </div>
                @empty
                <div class="dms-hint py-3 text-center">Tiada medan EAV diisi untuk dokumen ini.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="glass-panel mb-4">
            <div class="section-header px-4 pt-4 pb-3">
                <h3>Maklumat Rekod</h3>
            </div>
            <ul class="meta-list px-4 pb-4">
                <li><span>Perkara</span><strong>{{ $dokumen->perkara ?: '-' }}</strong></li>
                <li><span>Bahagian</span><strong>{{ $dokumen->bahagian->nama_bahagian ?? '-' }}</strong></li>
                <li><span>Dimuat Naik Oleh</span><strong>{{ $dokumen->pemuatNaik->nama_staff ?? '-' }}</strong></li>
                <li><span>Status</span><strong>{{ $dokumen->status }}</strong></li>
                <li><span>Tarikh Dimuat Naik</span><strong>{{ $dokumen->created_at?->translatedFormat('d M Y, h:i A') }}</strong></li>
            </ul>
        </div>

        <div class="glass-panel mb-4">
            <div class="section-header px-4 pt-4 pb-3">
                <h3>Status Kelulusan</h3>
            </div>
            <div class="px-4 pb-4">
                @php
                    $statusSemasa = $dokumen->borang?->status_permohonan;
                    $kelasStatus = match ($statusSemasa) {
                        'Diluluskan' => 'kel-lulus',
                        'Disokong' => 'kel-sokong',
                        'Ditolak' => 'kel-tolak',
                        'Dalam Proses' => 'kel-proses',
                        default => 'kel-belum',
                    };
                @endphp

                <span class="badge kel-badge {{ $kelasStatus }}">{{ $statusSemasa ?? 'Belum Dihantar' }}</span>

                @if($dokumen->borang)
                    <ul class="meta-list mt-3">
                        <li><span>Pemohon</span><strong>{{ $dokumen->borang->pemohon->nama_staff ?? '-' }}</strong></li>
                        @if($dokumen->borang->penyokong)
                            <li><span>Disokong Oleh</span><strong>{{ $dokumen->borang->penyokong->nama_staff }}</strong></li>
                        @endif
                        @if($dokumen->borang->pelulus)
                            <li><span>Diluluskan Oleh</span><strong>{{ $dokumen->borang->pelulus->nama_staff }}</strong></li>
                        @endif
                    </ul>
                    @if($dokumen->borang->ulasan_penyokong)
                        <div class="kel-ulasan"><i class="bi bi-chat-left-text"></i> <strong>Penyokong:</strong> {{ $dokumen->borang->ulasan_penyokong }}</div>
                    @endif
                    @if($dokumen->borang->ulasan_pelulus)
                        <div class="kel-ulasan"><i class="bi bi-chat-left-text"></i> <strong>Pelulus:</strong> {{ $dokumen->borang->ulasan_pelulus }}</div>
                    @endif
                @endif

                {{-- Hantar / hantar semula --}}
                @can('update', $dokumen)
                    @if(!$dokumen->borang || $statusSemasa === 'Ditolak')
                        <form action="{{ route('kelulusan.hantar', $dokumen->dokumen_id) }}" method="POST" class="mt-3">
                            @csrf
                            <button type="submit" class="btn text-white save-btn w-100 justify-content-center">
                                <i class="bi bi-send me-2"></i> {{ $statusSemasa === 'Ditolak' ? 'Hantar Semula untuk Kelulusan' : 'Hantar untuk Kelulusan' }}
                            </button>
                        </form>
                    @endif
                @endcan

                {{-- Penyokong: sokong / tolak --}}
                @if($statusSemasa === 'Dalam Proses' && Auth::user()->hasAnyRole('PENYOKONG', 'SUPERADMIN', 'ADMIN'))
                    <form action="{{ route('kelulusan.sokong', $dokumen->dokumen_id) }}" method="POST" class="kel-form mt-3">
                        @csrf
                        <textarea name="ulasan_penyokong" class="dms-input" rows="2" placeholder="Ulasan (pilihan)"></textarea>
                        <div class="d-flex gap-2 mt-2">
                            <button type="submit" class="btn text-white save-btn flex-grow-1 justify-content-center">
                                <i class="bi bi-check2-circle me-2"></i> Sokong
                            </button>
                            <button type="submit" formaction="{{ route('kelulusan.tolak', $dokumen->dokumen_id) }}" formnovalidate class="outline-button btn-padam-outline flex-grow-1 justify-content-center">
                                Tolak
                            </button>
                        </div>
                        <div class="dms-hint mt-1">Ulasan diwajibkan jika memilih "Tolak".</div>
                    </form>
                @endif

                {{-- Pelulus: lulus / tolak --}}
                @if($statusSemasa === 'Disokong' && Auth::user()->hasAnyRole('PELULUS', 'SUPERADMIN', 'ADMIN'))
                    <form action="{{ route('kelulusan.lulus', $dokumen->dokumen_id) }}" method="POST" class="kel-form mt-3">
                        @csrf
                        <textarea name="ulasan_pelulus" class="dms-input" rows="2" placeholder="Ulasan (pilihan)"></textarea>
                        <div class="d-flex gap-2 mt-2">
                            <button type="submit" class="btn text-white save-btn flex-grow-1 justify-content-center">
                                <i class="bi bi-check2-circle me-2"></i> Luluskan
                            </button>
                            <button type="submit" formaction="{{ route('kelulusan.tolak', $dokumen->dokumen_id) }}" formnovalidate class="outline-button btn-padam-outline flex-grow-1 justify-content-center">
                                Tolak
                            </button>
                        </div>
                        <div class="dms-hint mt-1">Ulasan diwajibkan jika memilih "Tolak".</div>
                    </form>
                @endif
            </div>
        </div>

        <div class="glass-panel">
            <div class="section-header px-4 pt-4 pb-3">
                <h3>Fail Imbasan</h3>
            </div>
            <div class="px-4 pb-4">
                @forelse($dokumen->scans as $scan)
                    @php
                        $kelasOcr = match ($scan->status_ocr) {
                            'Selesai' => 'ocr-selesai',
                            'Ralat' => 'ocr-ralat',
                            'Tiada Teks Dikesan' => 'ocr-kosong',
                            default => 'ocr-pending',
                        };
                        $adaTeks = (bool) ($scan->ocr->teks_dibaca ?? null);
                    @endphp
                    <div class="fail-item">
                        <div class="fail-baris">
                            <a href="{{ route('dokumen.scan.papar', $scan->dokumen_scan_id) }}" target="_blank" class="fail-link text-decoration-none">
                                <i class="bi bi-file-earmark-text"></i>
                                <span class="fail-nama">{{ $scan->nama_fail }}</span>
                            </a>
                            <span class="badge fail-status {{ $kelasOcr }}">{{ $scan->status_ocr }}</span>
                            @if($adaTeks)
                                <button type="button" class="btn-teks-ocr" data-bs-toggle="collapse" data-bs-target="#ocr-{{ $scan->dokumen_scan_id }}" title="Papar teks OCR">
                                    <i class="bi bi-text-paragraph"></i>
                                </button>
                            @endif
                            @can('update', $dokumen)
                                @if(in_array($scan->status_ocr, ['Pending', 'Ralat']))
                                    <form action="{{ route('dokumen.scan.ocr', $scan->dokumen_scan_id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn-jalan-ocr">
                                            <i class="bi bi-play-fill"></i> {{ $scan->status_ocr === 'Ralat' ? 'Cuba Semula' : 'Jalankan OCR' }}
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                        @if($adaTeks)
                            <div class="collapse" id="ocr-{{ $scan->dokumen_scan_id }}">
                                <div class="ocr-teks-wrap">
                                    @if($scan->ocr->skor_padanan !== null)
                                        <div class="dms-hint mb-2">Skor keyakinan purata: {{ $scan->ocr->skor_padanan }}%</div>
                                    @endif
                                    <pre class="ocr-teks">{{ $scan->ocr->teks_dibaca }}</pre>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                <div class="dms-hint py-3 text-center">Tiada fail imbasan dilampirkan.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .section-header { border-bottom: 1px solid var(--dms-line); }
    .section-header h3 { margin: 0; color: var(--dms-brand); font-size: 16px; font-weight: 800; }

    .medan-baris {
        display: flex; justify-content: space-between; gap: 12px;
        padding: 12px 0; border-bottom: 1px dashed var(--dms-line);
        font-size: 13px;
    }
    .medan-baris:last-child { border-bottom: none; }
    .medan-nama { color: var(--dms-muted); font-weight: 600; }
    .medan-nilai { color: var(--dms-text); font-weight: 700; text-align: right; }

    .meta-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
    .meta-list li { display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding-bottom: 10px; border-bottom: 1px dashed var(--dms-line); }
    .meta-list li:last-child { border-bottom: none; padding-bottom: 0; }
    .meta-list li span { color: var(--dms-muted); }
    .meta-list li strong { color: var(--dms-brand); font-weight: 700; }

    .fail-item { margin-bottom: 10px; }
    .fail-item:last-child { margin-bottom: 0; }
    .fail-baris {
        display: flex; align-items: center; gap: 10px;
        padding: 12px; border: 1px solid var(--dms-line); border-radius: 10px;
        color: var(--dms-text);
        transition: border-color .2s ease;
    }
    .fail-baris:hover { border-color: var(--dms-primary); }
    .fail-link { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; color: var(--dms-text); }
    .fail-link:hover { color: var(--dms-primary); }
    .fail-nama { font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fail-status { font-weight: 700; padding: 5px 9px; border-radius: 6px; font-size: 10.5px; white-space: nowrap; }
    .ocr-selesai { background: rgba(37, 169, 121, .1); color: var(--dms-mint); }
    .ocr-pending { background: rgba(245, 158, 11, .14); color: #b45309; }
    .ocr-ralat { background: rgba(220, 53, 69, .1); color: var(--dms-danger); }
    .ocr-kosong { background: rgba(107, 122, 144, .12); color: var(--dms-muted); }

    .btn-teks-ocr {
        display: inline-flex; align-items: center; justify-content: center;
        width: 30px; height: 30px; border: 1px solid var(--dms-line); border-radius: 8px;
        background: var(--dms-surface-strong); color: var(--dms-primary); cursor: pointer;
    }
    .btn-teks-ocr:hover { background: rgba(23, 105, 210, .08); }
    .btn-jalan-ocr {
        display: inline-flex; align-items: center; gap: 4px;
        border: 1px solid var(--dms-primary); background: transparent;
        color: var(--dms-primary); font-size: 11px; font-weight: 700;
        padding: 6px 10px; border-radius: 8px; cursor: pointer;
        white-space: nowrap; transition: background .2s ease;
    }
    .btn-jalan-ocr:hover { background: rgba(23, 105, 210, .08); }
    .ocr-teks-wrap { margin-top: 8px; padding: 12px; border: 1px dashed var(--dms-line); border-radius: 10px; background: rgba(23,105,210,.02); }
    .ocr-teks { margin: 0; max-height: 260px; overflow: auto; font-size: 12px; white-space: pre-wrap; word-break: break-word; color: var(--dms-text); font-family: inherit; }
    .dms-hint { font-size: 12px; color: var(--dms-muted); }
    .btn-padam-outline { color: var(--dms-danger); border-color: var(--dms-danger); cursor: pointer; }

    .save-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none; border-radius: 10px; font-weight: 700; padding: 10px 18px;
        display: inline-flex; align-items: center;
    }
    .save-btn:hover { color: #fff; }
    .dms-input {
        width: 100%; padding: 10px 12px; border: 1px solid var(--dms-line); border-radius: 10px;
        background: var(--dms-surface-strong); font-size: 13px; color: var(--dms-text); resize: vertical;
    }

    .kel-badge { font-weight: 700; padding: 7px 12px; border-radius: 6px; font-size: 11.5px; }
    .kel-belum { background: rgba(107,122,144,.12); color: var(--dms-muted); }
    .kel-proses { background: rgba(245,158,11,.14); color: #b45309; }
    .kel-sokong { background: rgba(23,105,210,.1); color: var(--dms-primary); }
    .kel-lulus { background: rgba(37,169,121,.12); color: var(--dms-mint); }
    .kel-tolak { background: rgba(220,53,69,.1); color: var(--dms-danger); }
    .kel-ulasan { margin-top: 10px; padding: 10px 12px; border: 1px dashed var(--dms-line); border-radius: 10px; font-size: 12px; color: var(--dms-text); }
    .kel-form textarea { font-family: inherit; }
</style>
@endpush