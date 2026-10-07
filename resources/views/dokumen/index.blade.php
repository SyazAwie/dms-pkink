@extends('layouts.app')

@section('title', 'Senarai Dokumen')

@section('content')

<div class="page-intro">
    <div>
        <h2>Senarai Dokumen</h2>
        <p>Dokumen yang telah dimuat naik ke dalam Sistem Arkib Digital PKINK.</p>
    </div>
    <div>
        @can('create', \App\Models\Dokumen::class)
        <a href="{{ route('dokumen.create') }}" class="btn text-white save-btn text-decoration-none">
            <i class="bi bi-cloud-upload me-2"></i> Muat Naik Dokumen
        </a>
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@php
    $bilPenapis = collect(['q', 'jenis', 'bahagian', 'dari', 'hingga'])->filter(fn ($k) => request()->filled($k))->count();
    $labelSusun = ['tarikh_desc' => 'Tarikh dokumen: terbaru', 'tarikh_asc' => 'Tarikh dokumen: terlama', 'rujukan' => 'No. Rujukan (A-Z)'];

    $chips = [];
    if (request()->filled('q')) $chips['q'] = 'Carian: "' . request('q') . '"';
    if (request()->filled('jenis')) $chips['jenis'] = 'Jenis: ' . ($jenisDokumenSenarai->firstWhere('jenis_dokumen_id', request('jenis'))->nama_dokumen ?? '-');
    if (request()->filled('bahagian')) $chips['bahagian'] = 'Bahagian: ' . ($bahagianSenarai->firstWhere('bahagian_id', request('bahagian'))->kod_bahagian ?? '-');
    if (request()->filled('dari')) $chips['dari'] = 'Dari: ' . \Carbon\Carbon::parse(request('dari'))->format('d/m/Y');
    if (request()->filled('hingga')) $chips['hingga'] = 'Hingga: ' . \Carbon\Carbon::parse(request('hingga'))->format('d/m/Y');
    if (isset($labelSusun[request('susun')])) $chips['susun'] = 'Susunan: ' . $labelSusun[request('susun')];
@endphp

<div class="result-toolbar">
    <div class="result-left">
        <div class="result-info">
            <strong>{{ $senarai->total() }}</strong> dokumen {{ $adaPenapis ? 'ditemui' : 'keseluruhan' }}
            @if($senarai->total() > 0)
                <span class="dms-hint">&middot; paparan {{ $senarai->firstItem() }}-{{ $senarai->lastItem() }}</span>
            @endif
        </div>
        @if($chips)
            <div class="chip-list">
                @foreach($chips as $kunci => $label)
                    <a href="{{ route('dokumen.index', request()->except([$kunci, 'page'])) }}" class="filter-chip" title="Buang penapis ini">
                        <span>{{ $label }}</span> <i class="bi bi-x"></i>
                    </a>
                @endforeach
                <a href="{{ route('dokumen.index') }}" class="chip-reset">Set semula semua</a>
            </div>
        @endif
    </div>
    <button type="button" class="outline-button btn-toggle-cari" data-bs-toggle="collapse" data-bs-target="#panelPenapis" aria-controls="panelPenapis">
        <i class="bi bi-funnel"></i> <span>Carian &amp; Penapis</span>
        @if($bilPenapis)<span class="badge cari-badge">{{ $bilPenapis }}</span>@endif
    </button>
</div>

<!-- ============ Carian & Penapis ============ -->
<div class="collapse {{ $errors->any() ? 'show' : '' }}" id="panelPenapis">
<div class="glass-panel filter-panel mb-4">
    <form action="{{ route('dokumen.index') }}" method="GET">
        <div class="row g-3 align-items-end">
            <div class="col-lg-5">
                <label class="dms-label">Carian</label>
                <div class="search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" value="{{ request('q') }}" class="dms-input search-input"
                           placeholder="No. rujukan, perkara, nilai medan (cth: no. invois), nama fail..." maxlength="100" autocomplete="off">
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="dms-label">Jenis Dokumen</label>
                <select name="jenis" class="dms-input">
                    <option value="">Semua Jenis</option>
                    @foreach($jenisDokumenSenarai as $jd)
                        <option value="{{ $jd->jenis_dokumen_id }}" {{ request('jenis') == $jd->jenis_dokumen_id ? 'selected' : '' }}>
                            {{ $jd->nama_dokumen }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4 col-md-6">
                <label class="dms-label">Bahagian</label>
                <select name="bahagian" class="dms-input">
                    <option value="">Semua Bahagian</option>
                    @foreach($bahagianSenarai as $bh)
                        <option value="{{ $bh->bahagian_id }}" {{ request('bahagian') == $bh->bahagian_id ? 'selected' : '' }}>
                            {{ $bh->kod_bahagian }} - {{ $bh->nama_bahagian }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="dms-label">Tarikh Dokumen: Dari</label>
                <input type="date" name="dari" value="{{ request('dari') }}" class="dms-input @error('dari') is-invalid @enderror">
                @error('dari') <div class="dms-error">{{ $message }}</div> @enderror
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="dms-label">Hingga</label>
                <input type="date" name="hingga" value="{{ request('hingga') }}" class="dms-input @error('hingga') is-invalid @enderror">
                @error('hingga') <div class="dms-error">{{ $message }}</div> @enderror
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="dms-label">Susunan</label>
                <select name="susun" class="dms-input">
                    <option value="terbaru" {{ request('susun', 'terbaru') === 'terbaru' ? 'selected' : '' }}>Terbaru dimuat naik</option>
                    <option value="tarikh_desc" {{ request('susun') === 'tarikh_desc' ? 'selected' : '' }}>Tarikh dokumen: terbaru</option>
                    <option value="tarikh_asc" {{ request('susun') === 'tarikh_asc' ? 'selected' : '' }}>Tarikh dokumen: terlama</option>
                    <option value="rujukan" {{ request('susun') === 'rujukan' ? 'selected' : '' }}>No. Rujukan (A-Z)</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 d-flex gap-2">
                <button type="submit" class="btn text-white save-btn flex-grow-1 justify-content-center">
                    <i class="bi bi-funnel me-2"></i> Cari
                </button>
                @if($adaPenapis || request('susun'))
                    <a href="{{ route('dokumen.index') }}" class="outline-button text-decoration-none" title="Set semula">
                        <i class="bi bi-x-circle"></i>
                    </a>
                @endif
            </div>
        </div>
    </form>
</div>
</div>

<div class="glass-panel">
    <div class="table-responsive">
        <table class="table dms-table align-middle mb-0">
            <thead>
                <tr>
                    <th>No. Rujukan</th>
                    <th>Jenis Dokumen</th>
                    <th>Tarikh</th>
                    <th>Perkara</th>
                    <th>Bahagian</th>
                    <th>Dimuat Naik Oleh</th>
                    <th>Status</th>
                    <th class="text-end">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($senarai as $dokumen)
                <tr>
                    <td><span class="badge kod-badge">{{ $dokumen->no_rujukan }}</span></td>
                    <td>{{ $dokumen->jenisDokumen->nama_dokumen ?? '-' }}</td>
                    <td>{{ $dokumen->tarikh_dokumen?->translatedFormat('d M Y') }}</td>
                    <td>{{ $dokumen->perkara ?: '-' }}</td>
                    <td>{{ $dokumen->bahagian->nama_bahagian ?? '-' }}</td>
                    <td>{{ $dokumen->pemuatNaik->nama_staff ?? '-' }}</td>
                    <td><span class="badge status-badge">{{ $dokumen->status }}</span></td>
                    <td class="text-end">
                        <div class="aksi-group">
                            <a href="{{ route('dokumen.show', $dokumen->dokumen_id) }}" class="aksi-btn" title="Lihat">
                                <i class="bi bi-eye"></i>
                            </a>
                            @can('update', $dokumen)
                            <a href="{{ route('dokumen.edit', $dokumen->dokumen_id) }}" class="aksi-btn" title="Kemaskini">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            @endcan
                            @can('delete', $dokumen)
                            <form action="{{ route('dokumen.destroy', $dokumen->dokumen_id) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Padam dokumen {{ $dokumen->no_rujukan }}?\nDokumen akan dialih keluar daripada senarai, tetapi rekod arkib dikekalkan.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="aksi-btn aksi-padam" title="Padam">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4 dms-hint">
                        @if($adaPenapis)
                            Tiada dokumen sepadan dengan carian/penapis anda. <a href="{{ route('dokumen.index') }}">Set semula penapis</a>.
                        @else
                            Belum ada dokumen dimuat naik.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 pb-4 pt-2">
        {{ $senarai->links() }}
    </div>
</div>

@endsection

@push('styles')
<style>
    /* ============ Carian & Penapis ============ */
    .filter-panel { padding: 20px; }
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
    .dms-error { font-size: 11.5px; color: var(--dms-danger); font-weight: 600; margin-top: 6px; }
    .search-wrap { position: relative; }
    .search-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--dms-muted); pointer-events: none; }
    .search-input { padding-left: 38px; }
    .result-toolbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin: 0 4px 14px; }
    .result-left { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
    .result-info { font-size: 13px; color: var(--dms-text); margin: 0; }
    .btn-toggle-cari { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; white-space: nowrap; }
    .cari-badge { background: var(--dms-primary); color: #fff; border-radius: 999px; font-size: 10.5px; padding: 3px 7px; }
    .chip-list { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .filter-chip {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(23, 105, 210, .1); color: var(--dms-primary);
        border-radius: 999px; padding: 5px 10px 5px 12px;
        font-size: 11.5px; font-weight: 700; text-decoration: none;
        transition: background .2s ease;
    }
    .filter-chip:hover { background: rgba(23, 105, 210, .18); color: var(--dms-primary); }
    .chip-reset { font-size: 11.5px; font-weight: 700; color: var(--dms-muted); text-decoration: underline; }
    .chip-reset:hover { color: var(--dms-danger); }

    .save-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none;
        border-radius: 10px;
        font-weight: 700;
        padding: 10px 22px;
        box-shadow: 0 8px 20px rgba(23, 105, 210, .2);
        transition: transform .2s ease, box-shadow .2s ease;
        display: inline-flex;
        align-items: center;
    }
    .save-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(23,105,210,.28); color: #fff; }

    .dms-table { font-size: 13px; }
    .dms-table thead th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .4px;
        color: var(--dms-muted);
        border-bottom: 1px solid var(--dms-line);
        padding: 14px 16px;
    }
    .dms-table tbody td { padding: 14px 16px; border-bottom: 1px solid var(--dms-line); }
    .kod-badge {
        background: rgba(23, 105, 210, .1);
        color: var(--dms-primary);
        font-weight: 700;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 11.5px;
    }
    .status-badge {
        background: rgba(37, 169, 121, .1);
        color: var(--dms-mint);
        font-weight: 700;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 11px;
    }
    .dms-hint { font-size: 12px; color: var(--dms-muted); }

    .aksi-group { display: inline-flex; gap: 6px; align-items: center; }
    .aksi-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px;
        border: 1px solid var(--dms-line); border-radius: 8px;
        background: var(--dms-surface-strong); color: var(--dms-primary);
        cursor: pointer; text-decoration: none;
        transition: background .2s ease, border-color .2s ease;
    }
    .aksi-btn:hover { background: rgba(23, 105, 210, .08); border-color: var(--dms-primary); color: var(--dms-primary); }
    .aksi-padam { color: var(--dms-danger); }
    .aksi-padam:hover { background: rgba(220, 53, 69, .08); border-color: var(--dms-danger); color: var(--dms-danger); }
</style>
@endpush

@push('scripts')
<script>
    // Fokus terus ke kotak carian bila panel dibuka
    const panelPenapis = document.getElementById('panelPenapis');
    if (panelPenapis) {
        panelPenapis.addEventListener('shown.bs.collapse', () => {
            const q = panelPenapis.querySelector('input[name="q"]');
            if (q) q.focus();
        });
    }
</script>
@endpush