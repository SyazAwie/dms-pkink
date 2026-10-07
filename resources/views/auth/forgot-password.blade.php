<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pemulihan kata laluan Sistem Arkib Digital DMS PKINK">
    <title>Lupa Kata Laluan - DMS PKINK</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

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
            --danger: #be123c;
            --danger-soft: rgba(255, 241, 242, .82);
            --success: #047857;
            --success-soft: rgba(236, 253, 245, .86);
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

        .forgot-page {
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

        .forgot-wrap {
            width: 100%;
            max-width: 28rem;
            animation: rise .6s cubic-bezier(.22, 1, .36, 1) both;
        }

        .forgot-card {
            overflow: hidden;
            border: 1px solid var(--glass-border);
            border-radius: 1.5rem;
            background: var(--glass);
            box-shadow: 0 25px 60px -18px rgba(30, 58, 95, .2);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }

        .forgot-header {
            padding: 2rem 2rem 1.5rem;
            border-bottom: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, .36);
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: .8rem;
        }

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
        .forgot-title { margin: 1.5rem 0 0; font-size: 1.5rem; font-weight: 800; }
        .forgot-copy { margin: .4rem 0 0; color: var(--muted); font-size: .875rem; line-height: 1.6; }
        .forgot-body { padding: 1.75rem 2rem; }

        .status-box {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            margin-bottom: 1.25rem;
            padding: .9rem 1rem;
            border-radius: .85rem;
            font-size: .8rem;
            line-height: 1.6;
        }

        .status-box svg {
            width: 1.2rem;
            height: 1.2rem;
            flex: 0 0 auto;
            margin-top: .1rem;
        }

        .status-success {
            border: 1px solid rgba(16, 185, 129, .38);
            color: var(--success);
            background: var(--success-soft);
        }

        .status-error {
            border: 1px solid rgba(251, 113, 133, .38);
            color: var(--danger);
            background: var(--danger-soft);
        }

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
            padding: .7rem 1rem .7rem 2.75rem;
            color: var(--ink);
            font-size: .875rem;
            background: rgba(255, 255, 255, .72);
            transition: border-color .2s, box-shadow .2s, background-color .2s;
        }

        .form-control::placeholder { color: #94a3b8; }
        .form-control:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 .2rem rgba(37, 99, 235, .14);
        }
        .form-control.is-invalid { border-color: #fb7185; }
        .invalid-feedback { font-size: .75rem; }

        .btn-submit {
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

        .btn-submit:hover {
            color: #fff;
            box-shadow: 0 15px 28px rgba(37, 99, 235, .3);
            transform: translateY(-1px);
        }

        .btn-submit:active { transform: scale(.99); }
        .btn-submit:focus-visible { outline: 3px solid rgba(37, 99, 235, .28); outline-offset: 3px; }
        .btn-submit svg { width: 1.1rem; height: 1.1rem; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-top: 1.25rem;
            color: var(--primary);
            font-size: .8rem;
            font-weight: 700;
            text-decoration: none;
        }

        .back-link:hover { color: var(--brand); text-decoration: underline; text-underline-offset: 4px; }
        .back-link:focus-visible { border-radius: .25rem; outline: 3px solid rgba(37, 99, 235, .2); outline-offset: 3px; }
        .back-link svg { width: 1rem; height: 1rem; }
        .link-row { text-align: center; }
        .copyright { margin: 1.5rem 0 0; color: var(--muted); font-size: .72rem; line-height: 1.6; text-align: center; }

        @keyframes rise {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 575.98px) {
            .forgot-page { align-items: flex-start; padding: 6rem 1.25rem 2rem; }
            .forgot-header { padding: 1.75rem 1.5rem 1.5rem; }
            .forgot-body { padding: 1.6rem 1.5rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>
<body>
    <main class="forgot-page">
        <div class="ambient ambient-one" aria-hidden="true"></div>
        <div class="ambient ambient-two" aria-hidden="true"></div>

        <div class="forgot-wrap">
            <section class="forgot-card" aria-labelledby="forgot-title">
                <header class="forgot-header">
                    <div style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px; margin-bottom: 10px;">
                        <img src="{{ asset('images/Logo_PKINK_Tulisan_Jawi_Bawah.png') }}" alt="Logo PKINK" style="height: 85px; width: auto; object-fit: contain;">
                        <div>
                            <p class="brand-title" style="font-size: 1.1rem;">Sistem Arkib Digital DMS</p>
                            <p class="brand-subtitle">Bahagian Digital PKINK</p>
                        </div>
                    </div>

                    <h1 class="forgot-title" id="forgot-title">Lupa Kata Laluan?</h1>
                    <p class="forgot-copy">Sila masukkan emel rasmi anda untuk menerima pautan pemulihan sistem.</p>
                </header>

                <div class="forgot-body">
                    @if (session('success'))
                        <div class="status-box status-success" role="status" aria-live="polite">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"></path>
                                <path d="m9 11 3 3L22 4"></path>
                            </svg>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="status-box status-error" role="alert" aria-live="assertive">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" x2="12" y1="8" y2="12"></line>
                                <line x1="12" x2="12.01" y1="16" y2="16"></line>
                            </svg>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf

                        <div class="form-group">
                            <label class="form-label" for="email">Emel Rasmi Staf</label>
                            <div class="input-wrap">
                                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                                </svg>
                                <input
                                    type="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    id="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    placeholder="nama@pkink.com.my"
                                    maxlength="255"
                                    autocomplete="email"
                                    required
                                    autofocus>
                            </div>

                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m22 2-7 20-4-9-9-4Z"></path>
                                <path d="M22 2 11 13"></path>
                            </svg>
                            Hantar Pautan Sistem
                        </button>

                        <div class="link-row">
                            <a href="{{ route('login') }}" class="back-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m12 19-7-7 7-7"></path>
                                    <path d="M19 12H5"></path>
                                </svg>
                                Kembali ke Log Masuk
                            </a>
                        </div>
                    </form>
                </div>
            </section>

            <p class="copyright">© {{ date('Y') }} Perbadanan Kemajuan Iktisad Negeri Kelantan</p>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
