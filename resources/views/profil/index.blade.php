@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')
@php
    $ralatMaklumat = $errors->maklumat;
    $ralatKataLaluan = $errors->kataLaluan;
@endphp

<div class="page-intro">
    <div>
        <h2>Profil Saya</h2>
        <p>Kemas kini maklumat akaun dan kata laluan anda.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Maklumat akaun --}}
        <div class="glass-panel form-panel mb-4">
            <div class="section-header px-4 pt-4 pb-3">
                <div>
                    <h3>Maklumat Akaun</h3>
                    <p>No. Kad Pengenalan dan bahagian hanya boleh diubah oleh pentadbir sistem.</p>
                </div>
            </div>

            <form action="{{ route('profil.kemaskini') }}" method="POST" class="px-4 pb-4">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="dms-label" for="ic_paparan">No. Kad Pengenalan</label>
                        <input type="text" id="ic_paparan" class="dms-input dms-baca" value="{{ $user->ic_pekerja }}" readonly>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="dms-label" for="bahagian_paparan">Bahagian</label>
                        <input type="text" id="bahagian_paparan" class="dms-input dms-baca"
                               value="{{ $user->bahagian->nama_bahagian ?? 'Tiada Bahagian' }}{{ $user->bahagian?->trashed() ? ' (Dipadam)' : '' }}" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="dms-label" for="nama_staff">Nama Penuh <span class="text-danger">*</span></label>
                        <input type="text" name="nama_staff" id="nama_staff" maxlength="255" required autocomplete="name"
                               class="dms-input @if($ralatMaklumat->has('nama_staff')) is-invalid @endif"
                               value="{{ $ralatMaklumat->any() ? old('nama_staff') : $user->nama_staff }}">
                        @if($ralatMaklumat->has('nama_staff'))<div class="dms-error">{{ $ralatMaklumat->first('nama_staff') }}</div>@endif
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="dms-label" for="email">Emel <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" maxlength="255" required autocomplete="email"
                               data-asal="{{ $user->email }}"
                               class="dms-input @if($ralatMaklumat->has('email')) is-invalid @endif"
                               value="{{ $ralatMaklumat->any() ? old('email') : $user->email }}">
                        <div class="dms-hint">Emel digunakan untuk tetapan semula kata laluan.</div>
                        @if($ralatMaklumat->has('email'))<div class="dms-error">{{ $ralatMaklumat->first('email') }}</div>@endif
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="dms-label" for="jawatan">Jawatan / Gred <span class="dms-optional">(Pilihan)</span></label>
                        <input type="text" name="jawatan" id="jawatan" maxlength="150" autocomplete="organization-title"
                               placeholder="Cth: Pegawai Teknologi Maklumat F41"
                               class="dms-input @if($ralatMaklumat->has('jawatan')) is-invalid @endif"
                               value="{{ $ralatMaklumat->any() ? old('jawatan') : $user->jawatan }}">
                        @if($ralatMaklumat->has('jawatan'))<div class="dms-error">{{ $ralatMaklumat->first('jawatan') }}</div>@endif
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="dms-label" for="no_telefon">No. Telefon <span class="dms-optional">(Pilihan)</span></label>
                        <input type="tel" name="no_telefon" id="no_telefon" maxlength="20" autocomplete="tel"
                               placeholder="Cth: 012-345 6789"
                               class="dms-input @if($ralatMaklumat->has('no_telefon')) is-invalid @endif"
                               value="{{ $ralatMaklumat->any() ? old('no_telefon') : $user->no_telefon }}">
                        @if($ralatMaklumat->has('no_telefon'))<div class="dms-error">{{ $ralatMaklumat->first('no_telefon') }}</div>@endif
                    </div>
                </div>
                <div class="dms-hint mb-3" style="margin-top: -10px;">Jawatan dan no. telefon digunakan untuk mengisi borang secara automatik.</div>

                <div class="mb-4" id="sahEmel" @if(!$ralatMaklumat->has('kata_laluan_semasa')) hidden @endif>
                    <label class="dms-label" for="kata_laluan_semasa">Kata Laluan Semasa <span class="text-danger">*</span></label>
                    <input type="password" name="kata_laluan_semasa" id="kata_laluan_semasa" autocomplete="current-password"
                           class="dms-input @if($ralatMaklumat->has('kata_laluan_semasa')) is-invalid @endif">
                    <div class="dms-hint">Diperlukan untuk menukar emel.</div>
                    @if($ralatMaklumat->has('kata_laluan_semasa'))<div class="dms-error">{{ $ralatMaklumat->first('kata_laluan_semasa') }}</div>@endif
                </div>

                <hr class="dms-divider">
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn text-white save-btn">
                        <i class="bi bi-check2-circle me-2"></i> Simpan Maklumat
                    </button>
                </div>
            </form>
        </div>

        {{-- Tukar kata laluan --}}
        <div class="glass-panel form-panel" id="tukar-kata-laluan">
            <div class="section-header px-4 pt-4 pb-3">
                <div>
                    <h3>Tukar Kata Laluan</h3>
                    <p>Sekurang-kurangnya 8 aksara, mengandungi huruf dan nombor.</p>
                </div>
            </div>

            <form action="{{ route('profil.kata-laluan') }}" method="POST" class="px-4 pb-4">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="dms-label" for="kata_laluan_lama">Kata Laluan Semasa <span class="text-danger">*</span></label>
                    <input type="password" name="kata_laluan_lama" id="kata_laluan_lama" required autocomplete="current-password"
                           class="dms-input @if($ralatKataLaluan->has('kata_laluan_lama')) is-invalid @endif">
                    @if($ralatKataLaluan->has('kata_laluan_lama'))<div class="dms-error">{{ $ralatKataLaluan->first('kata_laluan_lama') }}</div>@endif
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="dms-label" for="kata_laluan">Kata Laluan Baharu <span class="text-danger">*</span></label>
                        <input type="password" name="kata_laluan" id="kata_laluan" minlength="8" required autocomplete="new-password"
                               class="dms-input @if($ralatKataLaluan->has('kata_laluan')) is-invalid @endif">
                        @if($ralatKataLaluan->has('kata_laluan'))<div class="dms-error">{{ $ralatKataLaluan->first('kata_laluan') }}</div>@endif
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="dms-label" for="kata_laluan_confirmation">Sahkan Kata Laluan Baharu <span class="text-danger">*</span></label>
                        <input type="password" name="kata_laluan_confirmation" id="kata_laluan_confirmation" minlength="8" required autocomplete="new-password"
                               class="dms-input">
                    </div>
                </div>

                <hr class="dms-divider">
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn text-white save-btn">
                        <i class="bi bi-shield-lock me-2"></i> Tukar Kata Laluan
                    </button>
                </div>
            </form>
        </div>

        {{-- Pengesahan dua faktor (P13) --}}
        @php $ralat2fa = $errors->duaFaktor; @endphp
        <div class="glass-panel form-panel mt-4" id="dua-faktor">
            <div class="section-header px-4 pt-4 pb-3 d-flex justify-content-between align-items-start gap-2">
                <div>
                    <h3>Pengesahan Dua Faktor (2FA)</h3>
                    <p>Selain kata laluan, log masuk memerlukan kod 6 digit daripada aplikasi Google atau Microsoft Authenticator di telefon anda.</p>
                </div>
                <span class="badge fa-status {{ $user->duaFaktorAktif() ? 'fa-aktif' : 'fa-mati' }}">{{ $user->duaFaktorAktif() ? 'Aktif' : 'Tidak aktif' }}</span>
            </div>
            <div class="px-4 pb-4 pt-3">
                @if(session('kod_pemulihan'))
                    <div class="fa-kod-pemulihan mb-3">
                        <strong><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i> Simpan kod pemulihan ini sekarang.</strong>
                        Setiap kod boleh digunakan sekali jika telefon anda hilang. Kod ini tidak akan dipaparkan lagi.
                        <div class="fa-kod-grid">
                            @foreach(session('kod_pemulihan') as $kod)<code>{{ $kod }}</code>@endforeach
                        </div>
                    </div>
                @endif

                @if($user->duaFaktorAktif())
                    <p class="dms-hint mb-3">Diaktifkan pada {{ $user->dua_faktor_disahkan_pada->translatedFormat('d M Y, h:i A') }} · Baki kod pemulihan: {{ count($user->dua_faktor_kod_pemulihan ?? []) }}</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('profil.dua-faktor.kod') }}">
                                @csrf
                                <label class="dms-label" for="kata_laluan_kod">Jana semula kod pemulihan</label>
                                <input type="password" name="kata_laluan" id="kata_laluan_kod" class="dms-input" placeholder="Kata laluan semasa" required autocomplete="current-password">
                                <button type="submit" class="outline-button w-100 mt-2"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Jana Kod Baharu</button>
                            </form>
                        </div>
                        @unless($duaFaktorWajib)
                            <div class="col-md-6">
                                <form method="POST" action="{{ route('profil.dua-faktor.matikan') }}">
                                    @csrf
                                    <label class="dms-label" for="kata_laluan_matikan">Matikan 2FA</label>
                                    <input type="password" name="kata_laluan" id="kata_laluan_matikan" class="dms-input" placeholder="Kata laluan semasa" required autocomplete="current-password">
                                    <button type="submit" class="outline-button fa-matikan w-100 mt-2"><i class="bi bi-shield-x" aria-hidden="true"></i> Matikan 2FA</button>
                                </form>
                            </div>
                        @endunless
                    </div>
                    @if($ralat2fa->has('kata_laluan'))<div class="dms-error">{{ $ralat2fa->first('kata_laluan') }}</div>@endif
                @elseif($rahsiaBaharu)
                    <div class="row g-4 align-items-center">
                        <div class="col-md-5 text-center">
                            <div class="fa-qr">{!! $qrDuaFaktor !!}</div>
                            <div class="dms-hint">Tidak boleh imbas? Masukkan kunci ini secara manual:</div>
                            <code class="fa-rahsia">{{ trim(chunk_split($rahsiaBaharu, 4, ' ')) }}</code>
                        </div>
                        <div class="col-md-7">
                            <ol class="fa-langkah">
                                <li>Pasang <strong>Google Authenticator</strong> atau <strong>Microsoft Authenticator</strong> di telefon.</li>
                                <li>Dalam aplikasi, pilih tambah akaun dan imbas kod QR ini.</li>
                                <li>Masukkan kod 6 digit yang dipaparkan untuk mengesahkan.</li>
                            </ol>
                            <form method="POST" action="{{ route('profil.dua-faktor.sahkan') }}">
                                @csrf
                                <label class="dms-label" for="kod_2fa">Kod daripada aplikasi</label>
                                <input type="text" name="kod" id="kod_2fa" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required
                                       class="dms-input fa-kod-input @if($ralat2fa->has('kod')) is-invalid @endif" placeholder="123456">
                                @if($ralat2fa->has('kod'))<div class="dms-error">{{ $ralat2fa->first('kod') }}</div>@endif
                                <button type="submit" class="btn text-white save-btn w-100 mt-3"><i class="bi bi-shield-check me-2"></i> Sahkan &amp; Aktifkan</button>
                            </form>
                        </div>
                    </div>
                @else
                    <form method="POST" action="{{ route('profil.dua-faktor.mula') }}" class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        @csrf
                        <div class="dms-hint m-0">{{ $duaFaktorWajib ? '2FA wajib bagi akaun pentadbir. Sila hidupkan sekarang.' : 'Disyorkan untuk akaun pentadbir dan pegawai kelulusan.' }}</div>
                        <button type="submit" class="btn text-white save-btn"><i class="bi bi-shield-plus me-2"></i> Hidupkan 2FA</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Aktiviti log masuk terkini (P13) --}}
        <div class="glass-panel form-panel mt-4">
            <div class="section-header px-4 pt-4 pb-3">
                <div>
                    <h3>Log Masuk Terkini</h3>
                    <p>Jika ada cubaan yang tidak anda kenali, tukar kata laluan dan hubungi pentadbir sistem.</p>
                </div>
            </div>
            <ul class="fa-log px-4 pb-3 pt-2 mb-0">
                @forelse($logMasukTerkini as $l)
                    <li>
                        <span class="badge fa-status {{ $l->berjaya ? 'fa-aktif' : 'fa-gagal' }}">{{ $l->berjaya ? 'Berjaya' : 'Gagal' }}</span>
                        <span>{{ $l->created_at?->translatedFormat('d M Y, h:i A') }}</span>
                        <span class="dms-hint m-0">{{ $l->alamat_ip }}{{ $l->sebab ? ' · '.$l->sebab : '' }}</span>
                    </li>
                @empty
                    <li class="dms-hint">Belum ada rekod.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass-panel tips-panel">
            <div class="profile-preview">
                <div class="avatar-circle-lg">{{ strtoupper(substr($user->nama_staff, 0, 1)) }}</div>
                <div>
                    <div class="preview-name">{{ $user->nama_staff }}</div>
                    <div class="preview-email">{{ $user->email }}</div>
                </div>
            </div>
            <ul class="meta-list">
                <li><span>Peranan</span><strong>{{ $user->roles->pluck('nama_roles')->join(', ') ?: 'Tiada peranan' }}</strong></li>
                <li><span>Bahagian</span><strong>{{ $user->bahagian->nama_bahagian ?? '-' }}</strong></li>
                <li><span>Jawatan</span><strong>{{ $user->jawatan ?: '-' }}</strong></li>
                <li><span>No. Telefon</span><strong>{{ $user->no_telefon ?: '-' }}</strong></li>
                <li><span>Akaun dicipta</span><strong>{{ $user->created_at?->format('d/m/Y') }}</strong></li>
            </ul>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .form-panel { padding: 0; }
    .tips-panel { padding: 20px; }

    .section-header { border-bottom: 1px solid var(--dms-line); }
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
    .dms-baca { background: rgba(107, 122, 144, .08); color: var(--dms-muted); cursor: not-allowed; }
    .dms-hint { font-size: 11px; color: var(--dms-muted); margin-top: 6px; }
    .dms-error { font-size: 11.5px; color: var(--dms-danger); font-weight: 600; margin-top: 6px; }
    .dms-divider { border-top: 1px solid var(--dms-line); opacity: 1; margin: 8px 0; }

    .fa-status { font-weight: 700; padding: 6px 10px; border-radius: 6px; font-size: 11px; white-space: nowrap; }
    .fa-aktif { background: rgba(37, 169, 121, .12); color: var(--dms-mint); }
    .fa-mati { background: rgba(107, 122, 144, .12); color: var(--dms-muted); }
    .fa-gagal { background: rgba(220, 53, 69, .1); color: var(--dms-danger); }
    .fa-qr { display: inline-block; padding: 10px; border: 1px solid var(--dms-line); border-radius: 12px; background: #fff; }
    .fa-qr svg { display: block; width: 180px; height: 180px; }
    .fa-rahsia { display: inline-block; margin-top: 4px; font-size: 12px; word-break: break-all; }
    .fa-langkah { padding-left: 18px; font-size: 13px; line-height: 1.7; }
    .fa-kod-input { font-size: 18px; font-weight: 700; letter-spacing: .3em; }
    .fa-kod-pemulihan { padding: 14px; border: 1px solid rgba(245, 158, 11, .45); border-radius: 10px; background: rgba(245, 158, 11, .08); font-size: 13px; }
    .fa-kod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 6px; margin-top: 10px; }
    .fa-kod-grid code { padding: 6px; border-radius: 6px; background: #fff; color: var(--dms-text); font-size: 13px; text-align: center; }
    .fa-matikan { color: var(--dms-danger); border-color: rgba(220, 53, 69, .4); }
    .fa-log { list-style: none; }
    .fa-log li { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px dashed var(--dms-line); font-size: 13px; }
    .fa-log li:last-child { border-bottom: 0; }

    .save-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none; border-radius: 10px; font-weight: 700; padding: 10px 22px;
    }
    .save-btn:hover { color: #fff; }

    .profile-preview { display: flex; align-items: center; gap: 12px; padding: 14px; border: 1px dashed var(--dms-line); border-radius: 10px; background: rgba(23,105,210,.03); }
    .avatar-circle-lg {
        width: 46px; height: 46px; min-width: 46px; border-radius: 50%;
        background: linear-gradient(135deg, var(--dms-brand), var(--dms-primary));
        color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 16px;
    }
    .preview-name { font-size: 13.5px; font-weight: 700; color: var(--dms-brand); }
    .preview-email { font-size: 11.5px; color: var(--dms-muted); word-break: break-all; }
    .meta-list { list-style: none; padding: 0; margin: 14px 0 0; display: flex; flex-direction: column; gap: 12px; }
    .meta-list li { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 12px; padding-bottom: 10px; border-bottom: 1px dashed var(--dms-line); }
    .meta-list li:last-child { border-bottom: none; padding-bottom: 0; }
    .meta-list li span { color: var(--dms-muted); }
    .meta-list li strong { color: var(--dms-brand); font-weight: 700; text-align: right; }
</style>
@endpush

@push('scripts')
<script>
    // Papar medan kata laluan semasa hanya bila emel diubah
    (() => {
        const emel = document.getElementById('email');
        const sah = document.getElementById('sahEmel');
        const kemaskini = () => {
            const berubah = emel.value.trim().toLowerCase() !== emel.dataset.asal.toLowerCase();
            sah.hidden = !berubah;
            document.getElementById('kata_laluan_semasa').required = berubah;
        };
        emel.addEventListener('input', kemaskini);
        kemaskini();
    })();
</script>
@endpush
