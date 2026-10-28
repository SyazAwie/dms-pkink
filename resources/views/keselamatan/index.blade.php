@extends('layouts.app')

@section('title', 'Pusat Keselamatan')

@section('content')

<div class="page-intro">
    <div>
        <h2>Pusat Keselamatan</h2>
        <p>Ringkasan ancaman 24 jam, semakan pengerasan sistem dan tindakan sensitif terkini. Amaran automatik dihantar ke loceng & telefon pentadbir bila berlaku cubaan teka kata laluan, log masuk pentadbir dari IP baharu, fail berbahaya atau sandaran tertunggak.</p>
    </div>
    <a href="{{ route('keselamatan.pelan') }}" class="outline-button kekal-label text-decoration-none">
        <i class="bi bi-journal-medical" aria-hidden="true"></i> <span>Pelan Tindak Balas Insiden</span>
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif

<div class="ks-kpi mb-4">
    <div class="glass-panel ks-kad {{ $ringkasan['gagal24j'] >= \App\Support\Keselamatan::AMBANG_GAGAL_IP ? 'ks-bahaya' : '' }}">
        <i class="bi bi-door-closed" aria-hidden="true"></i><strong>{{ $ringkasan['gagal24j'] }}</strong><span>Log masuk gagal (24 j)</span>
    </div>
    <div class="glass-panel ks-kad"><i class="bi bi-door-open" aria-hidden="true"></i><strong>{{ $ringkasan['berjaya24j'] }}</strong><span>Log masuk berjaya (24 j)</span></div>
    <div class="glass-panel ks-kad {{ $ringkasan['padam24j'] >= 5 ? 'ks-bahaya' : '' }}"><i class="bi bi-trash3" aria-hidden="true"></i><strong>{{ $ringkasan['padam24j'] }}</strong><span>Rekod dipadam (24 j)</span></div>
    <div class="glass-panel ks-kad {{ $ringkasan['failBerbahaya7h'] ? 'ks-bahaya' : '' }}"><i class="bi bi-bug" aria-hidden="true"></i><strong>{{ $ringkasan['failBerbahaya7h'] }}</strong><span>Fail berbahaya ditolak (7 hari)</span></div>
</div>

@php $ralatK = $errors->kecemasan; @endphp
@if($ralatK->any())
    <div class="alert alert-danger" role="alert">{{ $ralatK->first() }}</div>
@endif

{{-- ================= Butang kecemasan (S3) ================= --}}
<div class="glass-panel mb-4 overflow-hidden ks-kecemasan-panel">
    <div class="section-header px-4 pt-4 pb-3"><h3><i class="bi bi-exclamation-octagon me-1" aria-hidden="true"></i> Tindakan Kecemasan</h3></div>
    <div class="row g-0">
        <div class="col-lg-6 ks-blok">
            <h4>Akaun disyaki dicuri</h4>
            <form method="POST" class="ks-borang">
                @csrf
                <label class="dms-label" for="user_id">Pengguna</label>
                <select name="user_id" id="user_id" class="dms-input" required>
                    <option value="">Pilih pengguna</option>
                    @foreach($sasaran as $u)
                        <option value="{{ $u->user_id }}">{{ $u->nama_staff }} ({{ $u->roles->pluck('nama_roles')->join(', ') }})</option>
                    @endforeach
                </select>
                <div class="d-flex gap-2 mt-2">
                    <select name="tempoh" class="dms-input" aria-label="Tempoh kunci">
                        @foreach(\App\Http\Controllers\KeselamatanController::TEMPOH as $kod => $label)<option value="{{ $kod }}">{{ $label }}</option>@endforeach
                    </select>
                    <input type="text" name="sebab" class="dms-input" maxlength="255" placeholder="Sebab dikunci" aria-label="Sebab dikunci">
                </div>
                @error('sebab') <div class="dms-error">{{ $message }}</div> @enderror
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <button type="submit" formaction="{{ route('keselamatan.kunci') }}" class="btn ks-butang-bahaya"><i class="bi bi-lock" aria-hidden="true"></i> Kunci Akaun</button>
                    <button type="submit" formaction="{{ route('keselamatan.log-keluar') }}" formnovalidate class="outline-button kekal-label"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> <span>Log Keluar Paksa</span></button>
                </div>
                <div class="dms-hint mt-1">Kunci = tidak boleh log masuk & semua sesi ditamatkan. Log keluar paksa = sesi ditamatkan tetapi boleh log masuk semula (gunakan selepas tukar kata laluannya).</div>
            </form>

            @if($dikunci->isNotEmpty())
                <div class="ks-subtajuk mt-3">Akaun dikunci</div>
                <ul class="ks-senarai mb-0">
                    @foreach($dikunci as $u)
                        <li>
                            <span>{{ $u->nama_staff }}<small class="d-block dms-hint">hingga {{ $u->dikunci_hingga->year >= 2099 ? 'dibuka' : $u->dikunci_hingga->translatedFormat('d M Y, h:i A') }}{{ $u->sebab_kunci ? ' · '.$u->sebab_kunci : '' }}</small></span>
                            <form method="POST" action="{{ route('keselamatan.buka-kunci') }}">@csrf<input type="hidden" name="user_id" value="{{ $u->user_id }}">
                                <button type="submit" class="outline-button kekal-label"><i class="bi bi-unlock" aria-hidden="true"></i> <span>Buka</span></button></form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="col-lg-6 ks-blok">
            <h4>Sekat alamat IP</h4>
            <form method="POST" action="{{ route('keselamatan.sekat-ip') }}" class="ks-borang">
                @csrf
                <div class="d-flex gap-2">
                    <input type="text" name="alamat_ip" class="dms-input" required placeholder="cth. 203.0.113.5" aria-label="Alamat IP" value="{{ old('alamat_ip') }}">
                    <select name="tempoh" class="dms-input" aria-label="Tempoh sekatan">
                        @foreach(\App\Http\Controllers\KeselamatanController::TEMPOH as $kod => $label)<option value="{{ $kod }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                @error('alamat_ip') <div class="dms-error">{{ $message }}</div> @enderror
                <div class="d-flex gap-2 mt-2">
                    <input type="text" name="sebab" class="dms-input" maxlength="255" placeholder="Sebab (pilihan)" aria-label="Sebab sekatan">
                    <button type="submit" class="btn ks-butang-bahaya text-nowrap"><i class="bi bi-slash-circle" aria-hidden="true"></i> Sekat</button>
                </div>
                <div class="dms-hint mt-1">IP yang gagal log masuk {{ \App\Support\Keselamatan::AMBANG_GAGAL_IP }} kali dalam {{ \App\Support\Keselamatan::MINIT_TETINGKAP }} minit disekat automatik selama 1 jam. Pelayan sendiri (localhost) tidak pernah disekat.</div>
            </form>

            @if($ipDisekat->isNotEmpty())
                <div class="ks-subtajuk mt-3">IP disekat</div>
                <ul class="ks-senarai mb-0">
                    @foreach($ipDisekat as $s)
                        <li>
                            <span><code>{{ $s->alamat_ip }}</code> {{ $s->automatik ? '(automatik)' : '' }}
                                <small class="d-block dms-hint">{{ $s->tamat_pada ? 'hingga '.$s->tamat_pada->translatedFormat('d M, h:i A') : 'kekal' }}{{ $s->sebab ? ' · '.$s->sebab : '' }}</small></span>
                            <form method="POST" action="{{ route('keselamatan.buka-ip', $s->ip_disekat_id) }}">@csrf
                                <button type="submit" class="outline-button kekal-label"><i class="bi bi-unlock" aria-hidden="true"></i> <span>Buka</span></button></form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($superadmin)
            <div class="col-12 ks-blok ks-blok-super">
                <h4>Super Admin: tindakan seluruh sistem <small class="dms-hint">(perlu kata laluan anda)</small></h4>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <form method="POST" action="{{ route('keselamatan.kecemasan') }}" class="ks-borang">
                            @csrf
                            <input type="hidden" name="tindakan" value="{{ $kecemasan ? 'mati' : 'hidup' }}">
                            <div class="ks-status-kecemasan {{ $kecemasan ? 'aktif' : '' }}">
                                <strong>Mod kecemasan: {{ $kecemasan ? 'AKTIF' : 'tidak aktif' }}</strong>
                                <span>{{ $kecemasan ? 'Sejak '.\Illuminate\Support\Carbon::parse($kecemasan['sejak'])->translatedFormat('d M, h:i A').' oleh '.($kecemasan['oleh'] ?? '-').($kecemasan['sebab'] ? ' · '.$kecemasan['sebab'] : '') : 'Hanya Super Admin boleh log masuk, pengguna lain dilog keluar, dan sistem jadi baca sahaja (bukti & data dilindungi semasa siasatan).' }}</span>
                            </div>
                            @unless($kecemasan)
                                <input type="text" name="sebab" class="dms-input mt-2" maxlength="255" placeholder="Sebab (cth. disyaki pencerobohan)" aria-label="Sebab mod kecemasan">
                            @endunless
                            <div class="d-flex gap-2 mt-2">
                                <input type="password" name="kata_laluan" class="dms-input" required placeholder="Kata laluan anda" autocomplete="current-password" aria-label="Kata laluan anda">
                                <button type="submit" class="btn {{ $kecemasan ? 'save-btn text-white' : 'ks-butang-bahaya' }} text-nowrap">{{ $kecemasan ? 'Matikan' : 'Hidupkan' }}</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-6">
                        <form method="POST" action="{{ route('keselamatan.log-keluar-semua') }}" class="ks-borang">
                            @csrf
                            <div class="ks-status-kecemasan">
                                <strong>Log keluar semua pengguna</strong>
                                <span>Tamatkan setiap sesi kecuali sesi anda, cth. selepas kata laluan bocor atau sebelum menukar semua kata laluan.</span>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <input type="password" name="kata_laluan" class="dms-input" required placeholder="Kata laluan anda" autocomplete="current-password" aria-label="Kata laluan anda untuk log keluar semua">
                                <button type="submit" class="btn ks-butang-bahaya text-nowrap">Log Keluar Semua</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="dms-hint mt-2">Terkunci di luar? Di pelayan: <code>php artisan keselamatan:kecemasan off</code></div>
            </div>
        @endif
    </div>
</div>

<div class="glass-panel mb-4 overflow-hidden">
    <div class="section-header px-4 pt-4 pb-3 d-flex justify-content-between align-items-center gap-2">
        <h3>Semakan Pengerasan</h3>
        <span class="badge ks-lencana-bil {{ $bilMasalah ? 'ks-ada' : 'ks-tiada' }}">{{ $bilMasalah ? $bilMasalah.' perlu tindakan' : 'Semua baik' }}</span>
    </div>
    @foreach($senaraiSemak as $s)
        <div class="ss-baris">
            <span class="ss-lencana ss-{{ $s['status'] }}" title="{{ ['ok' => 'Baik', 'amaran' => 'Amaran', 'ralat' => 'Bahaya'][$s['status']] }}">
                <i class="bi {{ ['ok' => 'bi-check-lg', 'amaran' => 'bi-exclamation-lg', 'ralat' => 'bi-x-lg'][$s['status']] }}" aria-hidden="true"></i>
            </span>
            <div class="ss-teks">
                <strong>{{ $s['nama'] }}</strong>
                <span>{{ $s['nilai'] }}</span>
                @if($s['nota'] && $s['status'] !== 'ok')<small>{{ $s['nota'] }}</small>@endif
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="glass-panel overflow-hidden h-100">
            <div class="section-header px-4 pt-4 pb-3"><h3>IP Dengan Log Masuk Gagal (24 j)</h3></div>
            <div class="table-responsive">
                <table class="table dms-table align-middle mb-0">
                    <thead><tr><th class="ps-4">Alamat IP</th><th class="text-end">Gagal</th><th>Terakhir</th><th class="text-end pe-4">Tindakan</th></tr></thead>
                    <tbody>
                        @forelse($ringkasan['ipGagal'] as $ip)
                            @php $sudahDisekat = $ipDisekat->contains('alamat_ip', $ip->alamat_ip); @endphp
                            <tr>
                                <td class="ps-4"><code>{{ $ip->alamat_ip ?? '-' }}</code></td>
                                <td class="text-end"><strong class="{{ $ip->bil >= \App\Support\Keselamatan::AMBANG_GAGAL_IP ? 'text-danger' : '' }}">{{ $ip->bil }}</strong></td>
                                <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($ip->terakhir)->diffForHumans() }}</td>
                                <td class="text-end pe-4">
                                    @if($sudahDisekat)
                                        <span class="badge ks-tindakan">Disekat</span>
                                    @elseif($ip->alamat_ip && ! in_array($ip->alamat_ip, \App\Models\IpDisekat::LOCALHOST, true) && $ip->alamat_ip !== request()->ip())
                                        <form method="POST" action="{{ route('keselamatan.sekat-ip') }}" class="d-inline">@csrf
                                            <input type="hidden" name="alamat_ip" value="{{ $ip->alamat_ip }}"><input type="hidden" name="tempoh" value="24j">
                                            <input type="hidden" name="sebab" value="{{ $ip->bil }} log masuk gagal (24 j)">
                                            <button type="submit" class="outline-button kekal-label"><i class="bi bi-slash-circle" aria-hidden="true"></i> <span>Sekat 24 j</span></button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-4 dms-hint">Tiada log masuk gagal dalam 24 jam.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="glass-panel overflow-hidden h-100">
            <div class="section-header px-4 pt-4 pb-3"><h3>Akaun Paling Disasarkan (24 j)</h3></div>
            <ul class="ks-senarai px-4 py-2 mb-0">
                @forelse($ringkasan['akaunDisasar'] as $a)
                    <li><span>{{ $a->pengguna->nama_staff ?? 'Akaun dipadam' }}</span><strong>{{ $a->bil }} gagal</strong></li>
                @empty
                    <li class="dms-hint">Tiada akaun disasarkan.</li>
                @endforelse
            </ul>
            <div class="px-4 pb-3"><a href="{{ route('log-masuk.index', ['status' => 'gagal']) }}" class="dms-hint">Sejarah Log Masuk penuh →</a></div>
        </div>
    </div>
</div>

<div class="glass-panel overflow-hidden mb-4">
    <div class="section-header px-4 pt-4 pb-3"><h3>Tindakan Sensitif Terkini</h3></div>
    <div class="table-responsive">
        <table class="table dms-table align-middle mb-0">
            <thead><tr><th class="ps-4">Masa</th><th>Pengguna</th><th>Tindakan</th><th class="pe-4">Rekod</th></tr></thead>
            <tbody>
                @forelse($ringkasan['tindakanSensitif'] as $l)
                    <tr>
                        <td class="ps-4 text-nowrap">{{ $l->created_at?->translatedFormat('d M Y, h:i A') }}</td>
                        <td>{{ $l->pengguna->nama_staff ?? 'Sistem / tetamu' }}</td>
                        <td><span class="badge ks-tindakan">{{ $l->tindakan }}</span></td>
                        <td class="pe-4 dms-hint">{{ $l->nama_jadual }}{{ $l->rekod_id ? ' #'.$l->rekod_id : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-4 dms-hint">Tiada tindakan sensitif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('styles')
<style>
    .section-header { border-bottom: 1px solid var(--dms-line); }
    .section-header h3 { margin: 0; color: var(--dms-brand); font-size: 16px; font-weight: 800; }
    .dms-hint { font-size: 12px; color: var(--dms-muted); }
    .ks-kpi { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    @media (min-width: 992px) { .ks-kpi { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .ks-kad { display: grid; grid-template-columns: auto 1fr; grid-template-rows: auto auto; column-gap: 12px; align-items: center; padding: 14px 18px; }
    .ks-kad i { grid-row: span 2; font-size: 24px; color: var(--dms-primary); }
    .ks-kad strong { font-size: 22px; color: var(--dms-brand); line-height: 1; }
    .ks-kad span { color: var(--dms-muted); font-size: 12px; font-weight: 700; }
    .ks-kad.ks-bahaya { box-shadow: inset 3px 0 0 var(--dms-danger); }
    .ks-kad.ks-bahaya i, .ks-kad.ks-bahaya strong { color: var(--dms-danger); }
    .ks-lencana-bil { font-weight: 700; padding: 6px 10px; border-radius: 6px; font-size: 11px; }
    .ks-ada { background: rgba(220, 53, 69, .1); color: var(--dms-danger); }
    .ks-tiada { background: rgba(37, 169, 121, .12); color: var(--dms-mint); }
    .ss-baris { display: grid; grid-template-columns: 30px 1fr; gap: 12px; align-items: start; padding: 12px 24px; border-top: 1px solid var(--dms-line); }
    .section-header + .ss-baris { border-top: 0; padding-top: 16px; }
    .ss-lencana { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 9px; font-size: 16px; }
    .ss-lencana.ss-ok { color: var(--dms-mint); background: rgba(37, 169, 121, .12); }
    .ss-lencana.ss-amaran { color: #b45309; background: rgba(245, 158, 11, .14); }
    .ss-lencana.ss-ralat { color: var(--dms-danger); background: rgba(220, 53, 69, .1); }
    .ss-teks { display: grid; gap: 2px; min-width: 0; }
    .ss-teks strong { color: var(--dms-text); font-size: 13.5px; }
    .ss-teks span { color: var(--dms-muted); font-size: 12.5px; overflow-wrap: anywhere; }
    .ss-teks small { color: #8a5a0a; font-size: 12px; }
    .dms-table { font-size: 13px; }
    .dms-table thead th { color: var(--dms-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .ks-senarai { list-style: none; }
    .ks-senarai li { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px dashed var(--dms-line); font-size: 13px; }
    .ks-senarai li:last-child { border-bottom: 0; }
    .ks-tindakan { background: rgba(23, 105, 210, .08); color: var(--dms-primary); font-weight: 700; padding: 5px 9px; border-radius: 6px; font-size: 11px; }
    .ks-kecemasan-panel { box-shadow: inset 0 3px 0 var(--dms-danger); }
    .ks-blok { padding: 18px 24px; border-top: 1px solid var(--dms-line); }
    @media (min-width: 992px) { .ks-blok.col-lg-6 + .ks-blok.col-lg-6 { border-left: 1px solid var(--dms-line); } }
    .ks-blok h4 { margin: 0 0 10px; color: var(--dms-brand); font-size: 14px; font-weight: 800; }
    .ks-blok-super { background: rgba(220, 53, 69, .03); }
    .dms-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--dms-text); margin-bottom: 6px; }
    .dms-input { width: 100%; padding: 9px 12px; border: 1px solid var(--dms-line); border-radius: 10px; background: var(--dms-surface-strong); font-size: 13px; color: var(--dms-text); }
    .dms-error { font-size: 11.5px; color: var(--dms-danger); font-weight: 600; margin-top: 6px; }
    .ks-butang-bahaya { display: inline-flex; align-items: center; gap: 6px; border: 0; border-radius: 10px; background: var(--dms-danger); color: #fff; font-size: 13px; font-weight: 700; padding: 9px 16px; }
    .ks-butang-bahaya:hover { color: #fff; filter: brightness(1.08); }
    .save-btn { background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary)); border: none; border-radius: 10px; font-weight: 700; }
    .ks-subtajuk { color: var(--dms-brand); font-size: 12.5px; font-weight: 800; }
    .ks-senarai li form { flex: 0 0 auto; }
    .ks-status-kecemasan { display: grid; gap: 2px; padding: 10px 12px; border: 1px dashed var(--dms-line); border-radius: 10px; font-size: 12.5px; }
    .ks-status-kecemasan span { color: var(--dms-muted); }
    .ks-status-kecemasan.aktif { border: 1px solid var(--dms-danger); background: rgba(220, 53, 69, .08); }
    .ks-status-kecemasan.aktif strong { color: var(--dms-danger); }
    @media (max-width: 575.98px) { .ss-baris { padding: 12px 16px; } .ks-kad { padding: 12px; } .ks-blok { padding: 16px; } }
</style>
@endpush
