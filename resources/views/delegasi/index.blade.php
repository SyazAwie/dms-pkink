@extends('layouts.app')

@section('title', 'Delegasi Kelulusan')

@section('content')

<div class="page-intro">
    <div>
        <h2>Delegasi Kelulusan</h2>
        <p>Lantik pegawai pengganti untuk menyokong atau meluluskan permohonan semasa anda bercuti. Pengganti memegang peranan itu dalam julat tarikh sahaja, dan setiap tindakannya direkod "bagi pihak" anda.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif

<div class="row g-4">
    @if($pemberi->isNotEmpty())
        <div class="col-lg-4">
            <div class="glass-panel">
                <div class="section-header px-4 pt-4 pb-3"><h3>Lantik Pengganti</h3></div>
                <form method="POST" action="{{ route('delegasi.store') }}" class="px-4 pb-4 pt-3">
                    @csrf
                    <div class="mb-3">
                        <label class="dms-label" for="pemberi_id">Pegawai yang bercuti</label>
                        @if($pentadbir)
                            <select name="pemberi_id" id="pemberi_id" class="dms-input @error('pemberi_id') is-invalid @enderror" required>
                                <option value="">Pilih pegawai</option>
                                @foreach($pemberi as $p)
                                    <option value="{{ $p->user_id }}" @selected(old('pemberi_id') == $p->user_id)>
                                        {{ $p->nama_staff }} ({{ collect($p->kodPerananSendiri())->intersect(\App\Models\DelegasiKelulusan::PERANAN)->join(', ') }})
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="pemberi_id" value="{{ Auth::id() }}">
                            <input type="text" id="pemberi_id" class="dms-input dms-baca" value="{{ Auth::user()->nama_staff }}" readonly>
                        @endif
                        @error('pemberi_id') <div class="dms-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="dms-label" for="kod_peranan">Peranan</label>
                        <select name="kod_peranan" id="kod_peranan" class="dms-input @error('kod_peranan') is-invalid @enderror" required>
                            @foreach(\App\Models\DelegasiKelulusan::PERANAN as $kod)
                                @if($pentadbir || in_array($kod, Auth::user()->kodPerananSendiri(), true))
                                    <option value="{{ $kod }}" @selected(old('kod_peranan') === $kod)>{{ $kod === 'PENYOKONG' ? 'Penyokong' : 'Pelulus' }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('kod_peranan') <div class="dms-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="dms-label" for="penerima_id">Pengganti</label>
                        <select name="penerima_id" id="penerima_id" class="dms-input @error('penerima_id') is-invalid @enderror" required>
                            <option value="">Pilih pengganti</option>
                            @foreach($penerima as $u)
                                <option value="{{ $u->user_id }}" @selected(old('penerima_id') == $u->user_id)>{{ $u->nama_staff }}{{ $u->jawatan ? ' — '.$u->jawatan : '' }}</option>
                            @endforeach
                        </select>
                        @error('penerima_id') <div class="dms-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="dms-label" for="tarikh_mula">Tarikh mula</label>
                            <input type="date" name="tarikh_mula" id="tarikh_mula" class="dms-input @error('tarikh_mula') is-invalid @enderror"
                                   value="{{ old('tarikh_mula', today()->toDateString()) }}" min="{{ today()->toDateString() }}" required>
                        </div>
                        <div class="col-6">
                            <label class="dms-label" for="tarikh_tamat">Tarikh tamat</label>
                            <input type="date" name="tarikh_tamat" id="tarikh_tamat" class="dms-input @error('tarikh_tamat') is-invalid @enderror"
                                   value="{{ old('tarikh_tamat') }}" min="{{ today()->toDateString() }}" required>
                        </div>
                        @error('tarikh_mula') <div class="dms-error">{{ $message }}</div> @enderror
                        @error('tarikh_tamat') <div class="dms-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="dms-label" for="sebab">Sebab <span class="dms-optional">(pilihan)</span></label>
                        <input type="text" name="sebab" id="sebab" maxlength="255" class="dms-input" value="{{ old('sebab') }}" placeholder="Cth. Cuti tahunan">
                    </div>
                    <button type="submit" class="btn text-white save-btn w-100"><i class="bi bi-person-check me-1" aria-hidden="true"></i> Simpan Delegasi</button>
                </form>
            </div>
        </div>
    @endif

    <div class="{{ $pemberi->isNotEmpty() ? 'col-lg-8' : 'col-12' }}">
        <div class="glass-panel overflow-hidden">
            <div class="table-responsive">
                <table class="table dms-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Pegawai</th>
                            <th>Pengganti</th>
                            <th>Peranan</th>
                            <th>Tempoh</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($senarai as $d)
                            @php
                                $status = $d->status();
                                $kelas = ['Aktif' => 'st-aktif', 'Akan Datang' => 'st-datang', 'Tamat' => 'st-tamat', 'Dibatalkan' => 'st-batal'][$status];
                                $bolehBatal = in_array($status, ['Aktif', 'Akan Datang'], true)
                                    && ($pentadbir || in_array((int) Auth::id(), [(int) $d->pemberi_id, (int) $d->dicipta_oleh], true));
                            @endphp
                            <tr>
                                <td class="ps-4 boleh-balut"><strong>{{ $d->pemberi->nama_staff ?? '-' }}</strong></td>
                                <td class="boleh-balut">{{ $d->penerima->nama_staff ?? '-' }}
                                    @if($d->sebab)<div class="dms-hint">{{ $d->sebab }}</div>@endif
                                </td>
                                <td>{{ $d->kod_peranan === 'PENYOKONG' ? 'Penyokong' : 'Pelulus' }}</td>
                                <td class="text-nowrap">{{ $d->tarikh_mula->format('d/m/Y') }} – {{ $d->tarikh_tamat->format('d/m/Y') }}</td>
                                <td><span class="badge st-badge {{ $kelas }}">{{ $status }}</span></td>
                                <td class="text-end pe-4">
                                    @if($bolehBatal)
                                        <form method="POST" action="{{ route('delegasi.batal', $d->delegasi_id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="outline-button kekal-label"><i class="bi bi-x-circle" aria-hidden="true"></i> <span>Batal</span></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 dms-hint"><i class="bi bi-people d-block mb-2" style="font-size: 26px;" aria-hidden="true"></i>Tiada delegasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($senarai->hasPages())
                <div class="px-4 pb-4 pt-2">{{ $senarai->links() }}</div>
            @endif
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .section-header { border-bottom: 1px solid var(--dms-line); }
    .section-header h3 { margin: 0; color: var(--dms-brand); font-size: 16px; font-weight: 800; }
    .dms-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--dms-text); margin-bottom: 6px; }
    .dms-optional { font-weight: 500; color: var(--dms-muted); font-size: 11.5px; }
    .dms-input { width: 100%; padding: 10px 12px; border: 1px solid var(--dms-line); border-radius: 10px; background: var(--dms-surface-strong); font-size: 13px; color: var(--dms-text); outline: none; }
    .dms-input:focus { border-color: var(--dms-primary); box-shadow: 0 0 0 3px rgba(23,105,210,.12); }
    .dms-input.is-invalid { border-color: var(--dms-danger); }
    .dms-baca { background: rgba(107, 122, 144, .08); color: var(--dms-muted); }
    .dms-error { font-size: 11.5px; color: var(--dms-danger); font-weight: 600; margin-top: 6px; }
    .dms-hint { font-size: 12px; color: var(--dms-muted); }
    .save-btn { background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary)); border: none; border-radius: 10px; font-weight: 700; }
    .save-btn:hover { color: #fff; }
    .dms-table { font-size: 13px; }
    .dms-table thead th { color: var(--dms-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .st-badge { font-weight: 700; padding: 5px 9px; border-radius: 6px; font-size: 10.5px; }
    .st-aktif { background: rgba(37, 169, 121, .12); color: var(--dms-mint); }
    .st-datang { background: rgba(23, 105, 210, .1); color: var(--dms-primary); }
    .st-tamat { background: rgba(107, 122, 144, .12); color: var(--dms-muted); }
    .st-batal { background: rgba(220, 53, 69, .1); color: var(--dms-danger); }
</style>
@endpush
