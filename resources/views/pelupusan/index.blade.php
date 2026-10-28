@extends('layouts.app')

@section('title', 'Pelupusan Dokumen')

@section('content')

<div class="page-intro">
    <div>
        <h2>Pelupusan Dokumen</h2>
        <p>Dokumen yang tamat tempoh simpanan ikut tetapan kategori. Putuskan sama ada dikekalkan (dengan tempoh lanjutan) atau dilupuskan. Dokumen yang dilupuskan dipindah ke Tong Sampah; rekod dan fail kekal untuk jejak audit.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach(\App\Http\Controllers\PelupusanController::TAB as $kod => $label)
        <a href="{{ route('pelupusan.index', ['tab' => $kod]) }}" class="tab-lupus {{ $tab === $kod ? 'aktif' : '' }}">
            {{ $label }} <span class="tab-bil">{{ $bilangan[$kod] }}</span>
        </a>
    @endforeach
</div>

<div class="glass-panel overflow-hidden">
    <div class="table-responsive">
        <table class="table dms-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">No. Rujukan</th>
                    <th>Kategori</th>
                    <th>Tarikh Dokumen</th>
                    @if($tab === 'selesai')
                        <th>Keputusan</th>
                        <th class="pe-4">Oleh / Catatan</th>
                    @else
                        <th>Tamat Simpanan</th>
                        <th class="text-end pe-4">Keputusan</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($senarai as $d)
                    <tr>
                        <td class="ps-4">
                            @if($tab === 'selesai')
                                <span class="badge kod-badge">{{ $d->no_rujukan }}</span>
                            @else
                                <a href="{{ route('dokumen.show', $d->dokumen_id) }}" class="badge kod-badge text-decoration-none">{{ $d->no_rujukan }}</a>
                            @endif
                            @if($d->perkara)<div class="dms-hint boleh-balut">{{ $d->perkara }}</div>@endif
                        </td>
                        <td class="boleh-balut">
                            {{ $d->jenisDokumen->nama_dokumen ?? '-' }}
                            <div class="dms-hint">{{ $d->jenisDokumen?->tempoh_simpanan_tahun ? $d->jenisDokumen->tempoh_simpanan_tahun.' tahun' : 'Kekal' }}
                                · Syor: {{ $d->jenisDokumen->tindakan_tamat_tempoh ?? 'Semak Semula' }}</div>
                        </td>
                        <td class="text-nowrap">{{ $d->tarikh_dokumen?->format('d/m/Y') }}</td>
                        @if($tab === 'selesai')
                            <td><span class="badge st-badge st-lupus">{{ $d->status_pelupusan }}</span>
                                <div class="dms-hint">{{ $d->tarikh_pelupusan?->translatedFormat('d M Y, h:i A') }}</div></td>
                            <td class="pe-4 boleh-balut">{{ $d->pelupus->nama_staff ?? '-' }}<div class="dms-hint">{{ $d->catatan_pelupusan }}</div></td>
                        @else
                            <td class="text-nowrap">
                                {{ $d->tarikh_tamat_simpanan?->format('d/m/Y') }}
                                @if($d->status_pelupusan === 'Dikekalkan')
                                    <div><span class="badge st-badge st-kekal" title="{{ $d->catatan_pelupusan }}">Pernah dikekalkan</span></div>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <details class="keputusan">
                                    <summary class="outline-button kekal-label"><i class="bi bi-ui-checks" aria-hidden="true"></i> <span>Keputusan</span></summary>
                                    <div class="keputusan-panel">
                                        <form method="POST" action="{{ route('pelupusan.kekalkan', $d->dokumen_id) }}">
                                            @csrf
                                            <div class="keputusan-tajuk">Kekalkan</div>
                                            <div class="d-flex gap-2">
                                                <select name="tahun" class="dms-input" aria-label="Tempoh lanjutan">
                                                    @foreach([1, 2, 5, 10] as $t)<option value="{{ $t }}">+{{ $t }} tahun</option>@endforeach
                                                </select>
                                                <input type="text" name="catatan" class="dms-input" maxlength="255" required placeholder="Sebab dikekalkan" aria-label="Sebab dikekalkan">
                                            </div>
                                            <button type="submit" class="outline-button w-100 mt-2"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Kekalkan</button>
                                        </form>
                                        @if($tab === 'tamat')
                                            <form method="POST" action="{{ route('pelupusan.lupus', $d->dokumen_id) }}" class="mt-3">
                                                @csrf
                                                <div class="keputusan-tajuk">Lupuskan</div>
                                                <div class="d-flex gap-2">
                                                    <select name="kaedah" class="dms-input" aria-label="Kaedah pelupusan">
                                                        @foreach(\App\Models\Dokumen::STATUS_DILUPUS as $k)
                                                            <option value="{{ $k }}" @selected(($d->jenisDokumen->tindakan_tamat_tempoh ?? '') === 'Serah Arkib Negara' && $k === 'Diserah Arkib Negara')>{{ $k }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="text" name="catatan" class="dms-input" maxlength="255" required placeholder="No. kelulusan / catatan" aria-label="Catatan pelupusan">
                                                </div>
                                                <button type="submit" class="outline-button btn-lupus w-100 mt-2"><i class="bi bi-archive" aria-hidden="true"></i> Lupuskan</button>
                                            </form>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 dms-hint"><i class="bi bi-archive d-block mb-2" style="font-size: 26px;" aria-hidden="true"></i>
                            {{ ['tamat' => 'Tiada dokumen yang tamat tempoh simpanan.', 'akan' => 'Tiada dokumen yang akan tamat dalam '.\App\Http\Controllers\PelupusanController::HARI_AMARAN.' hari.', 'selesai' => 'Belum ada dokumen yang dilupuskan.'][$tab] }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($senarai->hasPages())
        <div class="px-4 pb-4 pt-2">{{ $senarai->links() }}</div>
    @endif
</div>

<p class="dms-hint mt-3"><i class="bi bi-info-circle" aria-hidden="true"></i> Tempoh simpanan ditetapkan bagi setiap kategori di halaman Kategori Dokumen (kira dari Tarikh Dokumen).</p>

@endsection

@push('styles')
<style>
    .tab-lupus { display: inline-flex; align-items: center; gap: 8px; padding: 7px 14px; border: 1px solid var(--dms-line); border-radius: 999px; color: var(--dms-text); background: var(--dms-surface-strong); font-size: 12.5px; font-weight: 700; text-decoration: none; }
    .tab-lupus.aktif { border-color: var(--dms-primary); color: #fff; background: var(--dms-primary); }
    .tab-bil { min-width: 22px; padding: 1px 7px; border-radius: 10px; background: rgba(104, 121, 141, .15); font-size: 11px; text-align: center; }
    .tab-lupus.aktif .tab-bil { background: rgba(255, 255, 255, .25); }
    .dms-table { font-size: 13px; }
    .dms-table thead th { color: var(--dms-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .kod-badge { background: rgba(23, 105, 210, .08); color: var(--dms-primary); font-weight: 700; padding: 6px 10px; border-radius: 6px; font-size: 11.5px; }
    .dms-hint { font-size: 12px; color: var(--dms-muted); }
    .st-badge { font-weight: 700; padding: 5px 9px; border-radius: 6px; font-size: 10.5px; }
    .st-lupus { background: rgba(107, 122, 144, .12); color: var(--dms-muted); }
    .st-kekal { background: rgba(23, 105, 210, .1); color: var(--dms-primary); }
    .keputusan { display: inline-block; position: relative; text-align: left; }
    .keputusan summary { list-style: none; }
    .keputusan summary::-webkit-details-marker { display: none; }
    .keputusan-panel {
        position: absolute; right: 0; z-index: 5; width: 340px; max-width: calc(100vw - 48px); margin-top: 6px; padding: 14px;
        border: 1px solid var(--dms-line); border-radius: 12px; background: var(--dms-surface-strong); box-shadow: 0 12px 32px rgba(24, 63, 104, .14);
    }
    .keputusan-tajuk { margin-bottom: 6px; color: var(--dms-brand); font-size: 12px; font-weight: 800; }
    .dms-input { width: 100%; padding: 8px 10px; border: 1px solid var(--dms-line); border-radius: 9px; background: #fff; font-size: 12.5px; color: var(--dms-text); }
    .keputusan select.dms-input { width: 120px; flex: 0 0 auto; }
    .btn-lupus { color: var(--dms-danger); border-color: rgba(220, 53, 69, .4); }
</style>
@endpush
