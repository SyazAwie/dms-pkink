@extends('layouts.app')

@section('title', 'Log Audit')

@section('content')

@php
    // Lajur sensitif tidak dipaparkan walaupun tersimpan dalam rekod lama
    $tapis = fn ($data) => is_array($data) ? \Illuminate\Support\Arr::except($data, ['password', 'remember_token']) : $data;
    $json = fn ($data) => json_encode($tapis($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<div class="page-intro">
    <div>
        <h2>Log Audit</h2>
        <p>Jejak semua tindakan dalam sistem. Rekod bersifat baca sahaja dan tidak boleh diubah.</p>
    </div>
</div>

<!-- ============ Penapis ============ -->
<div class="glass-panel filter-panel mb-4">
    <form action="{{ route('log-audit.index') }}" method="GET">
        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="dms-label">Pengguna</label>
                <select name="pengguna" class="dms-input">
                    <option value="">Semua Pengguna</option>
                    @foreach($penggunaSenarai as $p)
                        <option value="{{ $p->user_id }}" {{ request('pengguna') == $p->user_id ? 'selected' : '' }}>{{ $p->nama_staff }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="dms-label">Jadual</label>
                <select name="jadual" class="dms-input">
                    <option value="">Semua Jadual</option>
                    @foreach($jadualSenarai as $j)
                        <option value="{{ $j }}" {{ request('jadual') === $j ? 'selected' : '' }}>{{ $j }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="dms-label">Tindakan</label>
                <select name="tindakan" class="dms-input">
                    <option value="">Semua Tindakan</option>
                    @foreach($tindakanSenarai as $t)
                        <option value="{{ $t }}" {{ request('tindakan') === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="dms-label">ID Rekod</label>
                <input type="number" min="1" name="rekod" value="{{ request('rekod') }}" class="dms-input" placeholder="Cth: 12">
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="dms-label">Dari</label>
                <input type="date" name="dari" value="{{ request('dari') }}" class="dms-input @error('dari') is-invalid @enderror">
                @error('dari') <div class="dms-error">{{ $message }}</div> @enderror
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="dms-label">Hingga</label>
                <input type="date" name="hingga" value="{{ request('hingga') }}" class="dms-input @error('hingga') is-invalid @enderror">
                @error('hingga') <div class="dms-error">{{ $message }}</div> @enderror
            </div>
            <div class="col-lg-6 d-flex gap-2">
                <button type="submit" class="btn text-white save-btn flex-grow-1 justify-content-center">
                    <i class="bi bi-funnel me-2"></i> Tapis
                </button>
                @if($adaPenapis)
                    <a href="{{ route('log-audit.index') }}" class="outline-button text-decoration-none" title="Set semula">
                        <i class="bi bi-x-circle"></i>
                    </a>
                @endif
            </div>
        </div>
    </form>
</div>

<div class="result-info">
    <strong>{{ $senarai->total() }}</strong> rekod {{ $adaPenapis ? 'ditemui' : 'keseluruhan' }}
    @if($senarai->total() > 0)
        <span class="dms-hint">&middot; paparan {{ $senarai->firstItem() }}-{{ $senarai->lastItem() }}</span>
    @endif
</div>

<div class="glass-panel">
    <div class="table-responsive">
        <table class="table dms-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Masa</th>
                    <th>Pengguna</th>
                    <th>Tindakan</th>
                    <th>Jadual</th>
                    <th>Rekod</th>
                    <th>Alamat IP</th>
                    <th class="text-end">Butiran</th>
                </tr>
            </thead>
            <tbody>
                @forelse($senarai as $log)
                    @php
                        $kelas = match (true) {
                            \Illuminate\Support\Str::startsWith($log->tindakan, 'Cipta') => 'aud-cipta',
                            \Illuminate\Support\Str::startsWith($log->tindakan, 'Padam') => 'aud-padam',
                            \Illuminate\Support\Str::startsWith($log->tindakan, 'Kemaskini') => 'aud-kemaskini',
                            default => 'aud-lain',
                        };
                        $adaButiran = $log->data_lama || $log->data_baharu || $log->peranti_pengguna;
                    @endphp
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $log->pengguna->nama_staff ?? '-' }}</td>
                        <td><span class="badge aud-badge {{ $kelas }}">{{ $log->tindakan }}</span></td>
                        <td><code>{{ $log->nama_jadual }}</code></td>
                        <td>{{ $log->rekod_id ?? '-' }}</td>
                        <td>{{ $log->alamat_ip ?? '-' }}</td>
                        <td class="text-end">
                            @if($adaButiran)
                                <button type="button" class="aksi-btn" data-bs-toggle="collapse" data-bs-target="#log-{{ $log->log_audit_id }}" aria-expanded="false" title="Papar butiran">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if($adaButiran)
                    <tr class="baris-butiran">
                        <td colspan="7" class="p-0">
                            <div class="collapse" id="log-{{ $log->log_audit_id }}">
                                <div class="butiran-wrap">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="butiran-label">Data Lama</div>
                                            <pre class="butiran-json">{{ $log->data_lama ? $json($log->data_lama) : '-' }}</pre>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="butiran-label">Data Baharu</div>
                                            <pre class="butiran-json">{{ $log->data_baharu ? $json($log->data_baharu) : '-' }}</pre>
                                        </div>
                                    </div>
                                    @if($log->peranti_pengguna)
                                        <div class="butiran-peranti"><i class="bi bi-pc-display"></i> {{ $log->peranti_pengguna }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 dms-hint">
                            @if($adaPenapis)
                                Tiada rekod sepadan dengan penapis. <a href="{{ route('log-audit.index') }}">Set semula penapis</a>.
                            @else
                                Belum ada rekod log audit.
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
    .dms-hint { font-size: 12px; color: var(--dms-muted); }
    .result-info { font-size: 13px; color: var(--dms-text); margin: 0 4px 12px; }

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
        font-size: 11px; text-transform: uppercase; letter-spacing: .4px;
        color: var(--dms-muted); border-bottom: 1px solid var(--dms-line); padding: 14px 16px;
    }
    .dms-table tbody td { padding: 12px 16px; border-bottom: 1px solid var(--dms-line); }
    .dms-table code { font-size: 12px; color: var(--dms-brand); background: rgba(23,105,210,.06); padding: 2px 7px; border-radius: 6px; }

    .aud-badge { font-weight: 700; padding: 6px 10px; border-radius: 6px; font-size: 11px; }
    .aud-cipta { background: rgba(37,169,121,.12); color: var(--dms-mint); }
    .aud-kemaskini { background: rgba(23,105,210,.1); color: var(--dms-primary); }
    .aud-padam { background: rgba(220,53,69,.1); color: var(--dms-danger); }
    .aud-lain { background: rgba(245,158,11,.14); color: #b45309; }

    .aksi-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px;
        border: 1px solid var(--dms-line); border-radius: 8px;
        background: var(--dms-surface-strong); color: var(--dms-primary); cursor: pointer;
        transition: background .2s ease, border-color .2s ease;
    }
    .aksi-btn:hover { background: rgba(23,105,210,.08); border-color: var(--dms-primary); }
    .aksi-btn[aria-expanded="true"] i { transform: rotate(180deg); }

    .baris-butiran td { border-bottom: none; }
    .butiran-wrap { padding: 16px 20px 20px; background: rgba(23,105,210,.03); border-bottom: 1px solid var(--dms-line); }
    .butiran-label { font-size: 11px; font-weight: 800; letter-spacing: .4px; text-transform: uppercase; color: var(--dms-muted); margin-bottom: 6px; }
    .butiran-json {
        margin: 0; padding: 12px 14px; max-height: 260px; overflow: auto;
        border: 1px solid var(--dms-line); border-radius: 10px; background: var(--dms-surface-strong);
        font-size: 11.5px; color: var(--dms-text); white-space: pre-wrap; word-break: break-word;
    }
    .butiran-peranti { margin-top: 12px; font-size: 11px; color: var(--dms-muted); word-break: break-word; }
</style>
@endpush