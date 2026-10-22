@extends('layouts.app')

@section('title', 'Tukar Kata Laluan')

@section('content')

<div class="page-intro">
    <div>
        <h2>Tukar Kata Laluan</h2>
        <p>Anda sedang menggunakan kata laluan sementara. Sila tetapkan kata laluan baharu sebelum meneruskan.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="glass-panel form-panel">
            <div class="section-header px-4 pt-4 pb-3">
                <div>
                    <h3>Kata Laluan Baharu</h3>
                    <p>Sekurang-kurangnya 8 aksara, mengandungi huruf dan nombor.</p>
                </div>
            </div>

            <form action="{{ route('kata-laluan.simpan') }}" method="POST" class="px-4 pb-4">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="dms-label" for="kata_laluan">Kata Laluan Baharu <span class="text-danger">*</span></label>
                    <div class="dms-input-group">
                        <input type="password"
                               name="kata_laluan"
                               id="kata_laluan"
                               class="dms-input @error('kata_laluan') is-invalid @enderror"
                               minlength="8"
                               autocomplete="new-password"
                               required
                               autofocus>
                        <button type="button" class="dms-input-icon-btn" data-toggle-for="kata_laluan" aria-label="Paparkan kata laluan" tabindex="-1">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    @error('kata_laluan') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label class="dms-label" for="kata_laluan_confirmation">Sahkan Kata Laluan Baharu <span class="text-danger">*</span></label>
                    <div class="dms-input-group">
                        <input type="password"
                               name="kata_laluan_confirmation"
                               id="kata_laluan_confirmation"
                               class="dms-input"
                               minlength="8"
                               autocomplete="new-password"
                               required>
                        <button type="button" class="dms-input-icon-btn" data-toggle-for="kata_laluan_confirmation" aria-label="Paparkan pengesahan kata laluan" tabindex="-1">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <hr class="dms-divider">

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn text-white save-btn">
                        <i class="bi bi-shield-lock me-2"></i> Simpan Kata Laluan Baharu
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="glass-panel tips-panel">
            <div class="summary-label">KENAPA PERLU DITUKAR?</div>
            <ul class="tips-list">
                <li><i class="bi bi-check-circle-fill"></i> Kata laluan sementara dihantar melalui emel atau ditetapkan oleh pentadbir, jadi ia mungkin diketahui orang lain.</li>
                <li><i class="bi bi-check-circle-fill"></i> Halaman lain dalam sistem tidak boleh dibuka sehingga kata laluan ditukar.</li>
                <li><i class="bi bi-check-circle-fill"></i> Kata laluan baharu mesti berbeza daripada kata laluan sementara.</li>
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
    .dms-divider { border-top: 1px solid var(--dms-line); opacity: 1; margin: 8px 0; }

    .dms-input-group { position: relative; }
    .dms-input-group .dms-input { padding-right: 42px; }
    .dms-input-icon-btn {
        position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
        border: none; background: transparent; color: var(--dms-muted);
        width: 30px; height: 30px; border-radius: 8px;
        display: grid; place-items: center; cursor: pointer;
    }
    .dms-input-icon-btn:hover { background: rgba(23,105,210,.08); color: var(--dms-primary); }

    .save-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none; border-radius: 10px; font-weight: 700; padding: 10px 22px;
    }
    .save-btn:hover { color: #fff; }

    .summary-label { font-size: 11px; font-weight: 800; letter-spacing: .5px; color: var(--dms-muted); }
    .tips-list { list-style: none; padding: 0; margin: 14px 0 0; display: flex; flex-direction: column; gap: 10px; }
    .tips-list li { font-size: 12px; color: var(--dms-muted); display: flex; align-items: flex-start; gap: 8px; }
    .tips-list li i { color: var(--dms-mint); margin-top: 1px; }
</style>
@endpush

@push('scripts')
<script>
    document.querySelectorAll('[data-toggle-for]').forEach((butang) => {
        butang.addEventListener('click', () => {
            const input = document.getElementById(butang.dataset.toggleFor);
            const papar = input.type === 'password';
            input.type = papar ? 'text' : 'password';
            butang.querySelector('i').className = papar ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
</script>
@endpush
