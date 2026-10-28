<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Log masuk ke Sistem Arkib Digital DMS PKINK">
    <title>Log Masuk - DMS PKINK</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="icon" href="{{ asset('ikon/favicon-32.png') }}?v=2" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        :root {
            --brand: #1e3a5f;
            --primary: #2563eb;
            --mint: #10b981;
            --ink: #1e293b;
            --muted: #64748b;
            --line: rgba(148, 163, 184, .36);
            --glass: rgba(255, 255, 255, .68);
            --glass-border: rgba(255, 255, 255, .76);
        }

        * { box-sizing: border-box; }

        body {
            min-width: 320px;
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            color: var(--ink);
            font-family: "Plus Jakarta Sans", sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #f0f9ff 50%, #dbeafe 100%);
        }

        .login-page {
            position: relative;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 2.5rem 1.25rem;
            isolation: isolate;
        }

        .ambient {
            position: absolute;
            z-index: -1;
            border-radius: 999px;
            filter: blur(64px);
            pointer-events: none;
        }

        .ambient-one {
            top: -8rem;
            left: -6rem;
            width: 32.5rem;
            height: 32.5rem;
            background: rgba(125, 191, 242, .28);
        }

        .ambient-two {
            right: -4rem;
            bottom: -10rem;
            width: 35rem;
            height: 35rem;
            background: rgba(16, 185, 129, .16);
        }

        .login-wrap {
            width: 100%;
            max-width: 28rem;
            animation: rise .6s cubic-bezier(.22, 1, .36, 1) both;
        }

        .login-card {
            overflow: hidden;
            border: 1px solid var(--glass-border);
            border-radius: 1.5rem;
            background: var(--glass);
            box-shadow: 0 25px 60px -18px rgba(30, 58, 95, .2);
            backdrop-filter: blur(24px);
        }

        .login-header {
            padding: 2rem 2rem 1.5rem;
            border-bottom: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, .36);
        }

        .brand-row { display: flex; align-items: center; gap: .8rem; }

        .brand-mark {
            display: grid;
            width: 3rem;
            height: 3rem;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 1rem;
            color: #fff;
            background: linear-gradient(135deg, var(--brand), var(--primary));
            box-shadow: 0 10px 20px rgba(37, 99, 235, .24);
        }

        .brand-mark svg { width: 1.5rem; height: 1.5rem; }
        .brand-title { margin: 0; color: var(--brand); font-size: .95rem; font-weight: 800; }
        .brand-subtitle { margin: .25rem 0 0; color: var(--muted); font-size: .75rem; font-weight: 500; }
        .login-title { margin: 1.5rem 0 0; font-size: 1.5rem; font-weight: 800; }
        .login-copy { margin: .4rem 0 0; color: var(--muted); font-size: .875rem; line-height: 1.6; }
        .login-body { padding: 1.75rem 2rem; }

        .alert-custom {
            margin-bottom: 1.25rem;
            border: 1px solid rgba(251, 113, 133, .38);
            border-radius: 1rem;
            background: rgba(255, 241, 242, .82);
            color: #be123c;
            font-size: .8rem;
        }

        .alert-berjaya {
            margin-bottom: 1.25rem;
            border: 1px solid rgba(37, 169, 121, .35);
            border-radius: 1rem;
            background: rgba(236, 253, 245, .85);
            color: #047857;
            font-size: .8rem;
        }

        .alert-title { margin-bottom: .3rem; font-weight: 700; }
        .alert-custom ul { margin: 0; padding-left: 1.15rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-label { margin-bottom: .45rem; color: var(--ink); font-size: .8rem; font-weight: 700; }
        .input-wrap { position: relative; }

        .input-icon {
            position: absolute;
            top: 50%;
            left: .9rem;
            width: 1.1rem;
            height: 1.1rem;
            color: #7890aa;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .form-control {
            min-height: 3rem;
            border-color: var(--line);
            border-radius: .8rem;
            padding: .7rem 2.8rem .7rem 2.75rem;
            color: var(--ink);
            font-size: .875rem;
            background: rgba(255, 255, 255, .72);
            transition: border-color .2s, box-shadow .2s, background-color .2s;
        }

        .form-control::placeholder { color: #94a3b8; }
        .form-control:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 .2rem rgba(37, 99, 235, .14); }
        .form-control.is-invalid { border-color: #fb7185; }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: .45rem;
            display: grid;
            width: 2.2rem;
            height: 2.2rem;
            place-items: center;
            border: 0;
            border-radius: .6rem;
            color: #64748b;
            background: transparent;
            transform: translateY(-50%);
            transition: color .2s, background-color .2s;
        }

        .password-toggle:hover, .password-toggle:focus-visible { color: var(--brand); background: #e2e8f0; outline: none; }
        .password-toggle svg { width: 1.15rem; height: 1.15rem; }
        .invalid-feedback { font-size: .75rem; }

        .btn-login {
            display: flex;
            width: 100%;
            min-height: 3rem;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            border: 0;
            border-radius: .8rem;
            color: #fff;
            font-size: .875rem;
            font-weight: 700;
            background: linear-gradient(90deg, var(--brand), var(--primary));
            box-shadow: 0 12px 24px rgba(37, 99, 235, .24);
            transition: transform .2s, box-shadow .2s;
        }

        .btn-login:hover { color: #fff; box-shadow: 0 15px 28px rgba(37, 99, 235, .3); transform: translateY(-1px); }
        .btn-login:active { transform: scale(.99); }
        .btn-login:focus-visible { outline: 3px solid rgba(37, 99, 235, .28); outline-offset: 3px; }
        .btn-login svg { width: 1.1rem; height: 1.1rem; }

        .forgot-link { display: block; margin-top: 1.25rem; text-align: center; color: var(--primary); font-size: .8rem; font-weight: 700; text-decoration: none; }
        .forgot-link:hover { color: var(--brand); text-decoration: underline; text-underline-offset: 4px; }

        /* Daftar Akaun Baharu */
        .daftar-wrap { margin-top: 1.25rem; padding-top: 1.1rem; border-top: 1px solid var(--line); text-align: center; color: var(--muted); font-size: .8rem; }
        .btn-daftar {
            display: flex; width: 100%; min-height: 2.75rem; margin-top: .6rem;
            align-items: center; justify-content: center; gap: .5rem;
            border: 1px solid var(--primary); border-radius: .8rem;
            color: var(--primary); background: rgba(255, 255, 255, .7);
            font-size: .85rem; font-weight: 700; transition: background .2s, color .2s;
        }
        .btn-daftar:hover { color: #fff; background: var(--primary); }
        .modal-daftar .modal-content { border: 0; border-radius: 1.25rem; }
        .modal-daftar .modal-title { color: var(--brand); font-weight: 800; font-size: 1.05rem; }
        .modal-daftar .form-control,
        .modal-daftar .form-select { min-height: 2.75rem; padding: .6rem .9rem; border-radius: .7rem; font-size: .85rem; }
        .modal-daftar .form-select.is-invalid { border-color: #fb7185; }
        .modal-daftar .nota { color: var(--muted); font-size: .75rem; }
        /* Perangkap bot: tersembunyi daripada pengguna & pembaca skrin */
        .medan-perangkap { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        .copyright { margin: 1.5rem 0 0; color: var(--muted); font-size: .72rem; line-height: 1.6; text-align: center; }

        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 575.98px) {
            .login-page { align-items: flex-start; padding: 8rem 1.25rem 2rem; }
            .login-header { padding: 1.75rem 1.5rem 1.5rem; }
            .login-body { padding: 1.6rem 1.5rem; }
        }
        /* Mod Demo (Fasa D3) */
        .demo-kotak { margin-bottom: 14px; padding: 12px 14px; border: 1px solid #f3d38a; border-left: 4px solid #d99a00; border-radius: 10px; background: #fff8e6; color: #5c4400; font-size: 13.5px; line-height: 1.5; }
        .demo-akaun { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .demo-akaun form { margin: 0; }
        .btn-demo { display: flex; width: 100%; min-height: 64px; flex-direction: column; justify-content: center; gap: 2px; padding: 10px 12px; border: 1px solid #d5e1ee; border-radius: 12px; background: #fff; text-align: left; cursor: pointer; transition: border-color .15s, box-shadow .15s, transform .15s; }
        .btn-demo:hover, .btn-demo:focus-visible { border-color: #1769d2; box-shadow: 0 6px 16px rgba(23, 105, 210, .15); transform: translateY(-1px); outline: none; }
        .btn-demo-peranan { color: #183f68; font-size: 14px; font-weight: 800; }
        .btn-demo-nama { overflow: hidden; color: #68798d; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        @media (max-width: 360px) { .demo-akaun { grid-template-columns: 1fr; } }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; }
        }
    </style>
</head>
<body>
    @php $modDemo = ($akaunDemo ?? collect())->isNotEmpty(); @endphp
    <main class="login-page">
        <div class="ambient ambient-one" aria-hidden="true"></div>
        <div class="ambient ambient-two" aria-hidden="true"></div>

        <div class="login-wrap">
            <section class="login-card" aria-labelledby="login-title">
                <header class="login-header">
                    <div style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px; margin-bottom: 10px;">
                        <img src="{{ asset('images/Logo_PKINK_Tulisan_Jawi_Bawah.png') }}" alt="Logo PKINK" style="height: 85px; width: auto; object-fit: contain;">
                        <div>
                            <p class="brand-title" style="font-size: 1.1rem;">Sistem Arkib Digital DMS</p>
                            <p class="brand-subtitle">Bahagian Digital PKINK</p>
                        </div>
                    </div>
                    <h1 class="login-title" id="login-title">Log Masuk</h1>
                    <p class="login-copy">{{ $modDemo ? 'Pilih satu peranan di bawah. Tiada kata laluan diperlukan.' : 'Sila masukkan maklumat anda untuk meneruskan.' }}</p>
                </header>

                <div class="login-body">
                    {{-- Mesej berjaya (cth. selepas tetapan semula kata laluan) --}}
                    @if(session('success'))
                        <div class="alert alert-berjaya" role="status" aria-live="polite">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-custom" role="alert" aria-live="polite">
                            <div class="alert-title">Maklumat tidak sah</div>
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(session('demo_disekat'))
                        <div class="alert alert-custom" role="alert" aria-live="polite">{{ session('demo_disekat') }}</div>
                    @endif
                    @if(\App\Support\Demo::percubaan())
                        <div class="demo-kotak" role="note">
                            <strong>Mod Percubaan.</strong> Sistem sebenar untuk latihan. Belum ada akaun? Tekan <strong>Daftar Akaun Baharu</strong> di bawah dan tunggu kelulusan pentadbir.
                        </div>
                    @endif

                    @if($modDemo)
                    {{-- Mod Demo (Fasa D3): satu ketikan untuk masuk; borang kata laluan & pendaftaran ditutup --}}
                    <div class="demo-kotak" role="note">
                        <strong>Mod Demo.</strong> Data rekaan sahaja; semua perubahan akan dipadam selepas demo. Pilih peranan untuk mencuba sistem:
                    </div>
                    <div class="demo-akaun">
                        @foreach($akaunDemo as $akaun)
                            <form method="POST" action="{{ route('demo.masuk') }}">
                                @csrf
                                <input type="hidden" name="ic" value="{{ $akaun['ic'] }}">
                                <button type="submit" class="btn-demo">
                                    <span class="btn-demo-peranan">{{ $akaun['peranan'] }}</span>
                                    <span class="btn-demo-nama">{{ $akaun['nama'] }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                    @else
                    <form method="POST" action="{{ route('login.proses') }}">
                        @csrf

                        <div class="form-group">
                            <label class="form-label" for="ic_pekerja">No. Kad Pengenalan</label>
                            <div class="input-wrap">
                                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 11a1 1 0 0 0-1 1c0 2-1 3-1 3"></path><path d="M15 8a5 5 0 0 0-8 4c0 1.8-.4 3.1-1 4"></path><path d="M17 11a5 5 0 0 0-10 0"></path><path d="M19 11a7 7 0 0 0-14 0c0 3-1 5-2 6"></path><path d="M8 21c2-2 3-5 3-9a1 1 0 0 1 2 0c0 3-.5 6-2 9"></path><path d="M15 21c1-2 2-5 2-9"></path><path d="M19 17c.5-2 .7-4 .7-6"></path>
                                </svg>
                                <input
                                    type="text"
                                    class="form-control @error('ic_pekerja') is-invalid @enderror"
                                    id="ic_pekerja"
                                    name="ic_pekerja"
                                    value="{{ $errors->pendaftaran->any() ? '' : old('ic_pekerja') }}"
                                    placeholder="Contoh: 901201031234"
                                    inputmode="numeric"
                                    maxlength="12"
                                    autocomplete="username"
                                    required
                                    autofocus>
                            </div>
                            @error('ic_pekerja')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">Kata Laluan</label>
                            <div class="input-wrap">
                                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="7.5" cy="15.5" r="5.5"></circle><path d="m21 2-9.6 9.6"></path><path d="m15 7 2 2"></path><path d="m18 4 2 2"></path>
                                </svg>
                                <input
                                    type="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    id="password"
                                    name="password"
                                    placeholder="Masukkan kata laluan"
                                    autocomplete="current-password"
                                    required>
                                <button class="password-toggle" type="button" id="passwordToggle" aria-label="Paparkan kata laluan" aria-pressed="false" title="Paparkan kata laluan">
                                    <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2.1 12a10 10 0 0 1 19.8 0 10 10 0 0 1-19.8 0"></path><circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-login">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 13c0 5-3.5 7.5-7.7 8.9a1 1 0 0 1-.6 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1.2 1.2 0 0 1 1.6 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path>
                            </svg>
                            Log Masuk
                        </button>

                        <a href="{{ route('password.request') }}" class="forgot-link">Lupa Kata Laluan?</a>
                    </form>

                    <div class="daftar-wrap">
                        Staf baharu dan belum mempunyai akaun?
                        <button type="button" class="btn-daftar" data-bs-toggle="modal" data-bs-target="#modalDaftar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line>
                            </svg>
                            Daftar Akaun Baharu
                        </button>
                    </div>
                    @endif
                </div>
            </section>

            <p class="copyright">© {{ date('Y') }} Perbadanan Kemajuan Iktisad Negeri Kelantan</p>
        </div>
    </main>

    @unless($modDemo)
    {{-- Modal: Daftar Akaun Baharu (ralat validasi dalam error bag 'pendaftaran') --}}
    @php $ralatDaftar = $errors->pendaftaran; @endphp
    <div class="modal fade modal-daftar" id="modalDaftar" tabindex="-1" aria-labelledby="modalDaftarTajuk" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('pendaftaran.simpan') }}" novalidate>
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title" id="modalDaftarTajuk">Daftar Akaun Baharu</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="nota mb-3">Permohonan anda akan disemak oleh pentadbir sistem. Jika diluluskan, kata laluan sementara akan dihantar ke emel anda.</p>
                        @if(\App\Support\Demo::percubaan())
                            <div class="demo-kotak">Sesi latihan: akaun dan semua maklumat yang anda isi (termasuk No. KP dan emel) akan dipadam selepas sesi tamat.</div>
                        @endif

                        @if($ralatDaftar->has('pendaftaran'))
                            <div class="alert alert-custom" role="alert">{{ $ralatDaftar->first('pendaftaran') }}</div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label" for="daftar_ic">No. Kad Pengenalan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @if($ralatDaftar->has('ic_pekerja')) is-invalid @endif"
                                   id="daftar_ic" name="ic_pekerja" value="{{ $ralatDaftar->any() ? old('ic_pekerja') : '' }}"
                                   placeholder="Contoh: 901201031234" inputmode="numeric" maxlength="12" autocomplete="off" required>
                            @if($ralatDaftar->has('ic_pekerja'))<div class="invalid-feedback d-block">{{ $ralatDaftar->first('ic_pekerja') }}</div>@endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="daftar_nama">Nama Penuh <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @if($ralatDaftar->has('nama_staff')) is-invalid @endif"
                                   id="daftar_nama" name="nama_staff" value="{{ old('nama_staff') }}" maxlength="255" autocomplete="name" required>
                            @if($ralatDaftar->has('nama_staff'))<div class="invalid-feedback d-block">{{ $ralatDaftar->first('nama_staff') }}</div>@endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="daftar_emel">Emel <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @if($ralatDaftar->has('email')) is-invalid @endif"
                                   id="daftar_emel" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
                            @if($ralatDaftar->has('email'))<div class="invalid-feedback d-block">{{ $ralatDaftar->first('email') }}</div>@endif
                        </div>

                        <div class="mb-1">
                            <label class="form-label" for="daftar_bahagian">Bahagian <span class="text-danger">*</span></label>
                            <select class="form-select @if($ralatDaftar->has('bahagian_id')) is-invalid @endif" id="daftar_bahagian" name="bahagian_id" required>
                                <option value="">-- Pilih Bahagian --</option>
                                @foreach($senaraiBahagian as $b)
                                    <option value="{{ $b->bahagian_id }}" {{ (string) old('bahagian_id') === (string) $b->bahagian_id ? 'selected' : '' }}>{{ $b->kod_bahagian }} - {{ $b->nama_bahagian }}</option>
                                @endforeach
                            </select>
                            @if($ralatDaftar->has('bahagian_id'))<div class="invalid-feedback d-block">{{ $ralatDaftar->first('bahagian_id') }}</div>@endif
                        </div>

                        {{-- Perangkap bot: jangan isi --}}
                        <div class="medan-perangkap" aria-hidden="true">
                            <label for="laman_web">Laman web</label>
                            <input type="text" id="laman_web" name="laman_web" tabindex="-1" autocomplete="off">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary fw-bold">Hantar Permohonan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @endunless

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    @unless($modDemo)
    @if($ralatDaftar->any())
    <script>
        // Buka semula modal pendaftaran jika permohonan ditolak oleh validasi
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDaftar')).show();
    </script>
    @endif
    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('passwordToggle');
        const eyeIcon = document.getElementById('eyeIcon');

        passwordToggle.addEventListener('click', function () {
            const isVisible = passwordInput.type === 'text';
            passwordInput.type = isVisible ? 'password' : 'text';
            passwordToggle.setAttribute('aria-pressed', String(!isVisible));
            passwordToggle.setAttribute('aria-label', isVisible ? 'Paparkan kata laluan' : 'Sembunyikan kata laluan');
            passwordToggle.setAttribute('title', isVisible ? 'Paparkan kata laluan' : 'Sembunyikan kata laluan');
            eyeIcon.innerHTML = isVisible
                ? '<path d="M2.1 12a10 10 0 0 1 19.8 0 10 10 0 0 1-19.8 0"></path><circle cx="12" cy="12" r="3"></circle>'
                : '<path d="m2 2 20 20"></path><path d="M6.7 6.7A10.7 10.7 0 0 0 2.1 12a10 10 0 0 0 16.3 3.3"></path><path d="M10.7 10.7a2 2 0 0 0 2.6 2.6"></path><path d="M14.2 4.2A10.5 10.5 0 0 1 21.9 12a10.8 10.8 0 0 1-2.2 3.2"></path>';
        });
    </script>
    @endunless
</body>
</html>
