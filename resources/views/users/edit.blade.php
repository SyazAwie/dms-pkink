@extends('layouts.app')

@section('title', 'Kemaskini Kakitangan')

@section('content')

<!-- Bahagian Pengenalan Halaman -->
<div class="page-intro">
    <div>
        <h2>Kemaskini Profil Kakitangan</h2>
        <p>Kemaskini maklumat pengguna <strong>{{ $user->nama_staff }}</strong>.</p>
    </div>
    <div>
        <a href="{{ route('users.index') }}" class="outline-button text-decoration-none">
            <i class="bi bi-arrow-left"></i> <span>Kembali ke Senarai</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Borang Utama -->
    <div class="col-lg-8">
        <div class="glass-panel form-panel">
            <div class="section-header px-4 pt-4 pb-3">
                <div>
                    <h3>Maklumat Kakitangan</h3>
                    <p>Sila lengkapkan medan bertanda <span class="text-danger">*</span></p>
                </div>
            </div>

            <form action="{{ route('users.update', $user->user_id) }}" method="POST" id="userForm" class="px-4 pb-4">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="dms-label">No. Kad Pengenalan <span class="text-danger">*</span></label>
                        <input type="text"
                               name="ic_pekerja"
                               id="ic_pekerja"
                               class="dms-input @error('ic_pekerja') is-invalid @enderror"
                               value="{{ old('ic_pekerja', $user->ic_pekerja) }}"
                               inputmode="numeric"
                               maxlength="12"
                               autocomplete="off"
                               required>
                        <div class="dms-hint">12 digit tanpa sengkang (-)</div>
                        @error('ic_pekerja') <div class="dms-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="dms-label">Emel Rasmi <span class="text-danger">*</span></label>
                        <input type="email"
                               name="email"
                               id="email"
                               class="dms-input @error('email') is-invalid @enderror"
                               value="{{ old('email', $user->email) }}"
                               autocomplete="off"
                               required>
                        @error('email') <div class="dms-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label class="dms-label">Nama Penuh <span class="text-danger">*</span></label>
                    <input type="text"
                           name="nama_staff"
                           id="nama_staff"
                           class="dms-input @error('nama_staff') is-invalid @enderror"
                           value="{{ old('nama_staff', $user->nama_staff) }}"
                           autocomplete="off"
                           required>
                    @error('nama_staff') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="dms-label">Penempatan Bahagian <span class="text-danger">*</span></label>
                        <select name="bahagian_id" id="bahagian_id" class="dms-input dms-select @error('bahagian_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Bahagian --</option>
                            @foreach($senarai_bahagian as $b)
                                <option value="{{ $b->bahagian_id }}" {{ old('bahagian_id', $user->bahagian_id) == $b->bahagian_id ? 'selected' : '' }}>
                                    {{ $b->kod_bahagian }} - {{ $b->nama_bahagian }}
                                </option>
                            @endforeach
                        </select>
                        @error('bahagian_id') <div class="dms-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="dms-label">Tukar Kata Laluan</label>
                        <div class="dms-input-group">
                            <input type="password"
                                   name="password"
                                   id="password"
                                   class="dms-input @error('password') is-invalid @enderror"
                                   placeholder="Biar kosong jika tiada pertukaran"
                                   autocomplete="new-password">
                            <button type="button" class="dms-input-icon-btn" id="togglePassword" tabindex="-1">
                                <i class="bi bi-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                        <div class="dms-hint">Isi hanya jika mahu menetapkan kata laluan baharu</div>
                        @error('password') <div class="dms-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-2">
                    <label class="dms-label">Peranan (Role) Sistem @if(Auth::user()->hasRole('SUPERADMIN'))<span class="text-danger">*</span>@endif</label>

                    @if(Auth::user()->hasRole('SUPERADMIN'))
                        <input type="hidden" name="roles_dihantar" value="1">
                        <div class="role-chip-group">
                            @foreach($senarai_role as $role)
                            <label class="role-chip" for="role_{{ $role->roles_id }}">
                                <input class="role-chip-input" type="checkbox" name="roles[]" value="{{ $role->roles_id }}" id="role_{{ $role->roles_id }}"
                                       {{ in_array($role->roles_id, old('roles_dihantar') ? old('roles', []) : $idPerananSemasa) ? 'checked' : '' }}>
                                <span class="role-chip-check"><i class="bi bi-check-lg"></i></span>
                                <span class="role-chip-text">
                                    <strong>{{ $role->kod_roles }}</strong>
                                    <small>{{ $role->nama_roles }}</small>
                                </span>
                            </label>
                            @endforeach
                        </div>
                    @else
                        <div class="role-readonly">
                            @forelse($user->roles as $r)
                                <span class="role-badge">{{ $r->kod_roles }}</span>
                            @empty
                                <span class="dms-hint">Tiada peranan ditetapkan</span>
                            @endforelse
                        </div>
                        <div class="dms-hint">Hanya Super Admin boleh mengubah peranan pengguna.</div>
                    @endif
                    @error('roles') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <hr class="dms-divider">

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route('users.index') }}" class="outline-button text-decoration-none">
                        <span>Batal</span>
                    </a>
                    <button type="submit" class="btn text-white save-btn">
                        <i class="bi bi-check2-circle me-2"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Panel Bantuan / Pratonton -->
    <div class="col-lg-4">
        <div class="glass-panel preview-panel mb-4">
            <div class="summary-head px-1">
                <div>
                    <div class="summary-label">PRATONTON PROFIL</div>
                </div>
                <div class="summary-icon"><i class="bi bi-eye"></i></div>
            </div>
            <div class="profile-preview">
                <div class="avatar-circle-lg" id="previewAvatar">{{ strtoupper(substr($user->nama_staff, 0, 1)) }}</div>
                <div>
                    <div class="preview-name" id="previewNama">{{ $user->nama_staff }}</div>
                    <div class="preview-email" id="previewEmail">{{ $user->email }}</div>
                    <div class="preview-bahagian" id="previewBahagian">{{ $user->bahagian->nama_bahagian ?? 'Bahagian belum dipilih' }}</div>
                </div>
            </div>
        </div>

        <div class="glass-panel tips-panel">
            <div class="summary-head px-1">
                <div>
                    <div class="summary-label">MAKLUMAT REKOD</div>
                </div>
                <div class="summary-icon" style="color: var(--dms-mint); background: rgba(37,169,121,.12);"><i class="bi bi-info-circle"></i></div>
            </div>
            <ul class="meta-list">
                <li>
                    <span>ID Pengguna</span>
                    <strong>#{{ $user->user_id }}</strong>
                </li>
                @if($user->created_at)
                <li>
                    <span>Didaftarkan</span>
                    <strong>{{ $user->created_at->translatedFormat('d M Y') }}</strong>
                </li>
                @endif
                @if($user->updated_at)
                <li>
                    <span>Kemaskini Terakhir</span>
                    <strong>{{ $user->updated_at->diffForHumans() }}</strong>
                </li>
                @endif
            </ul>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .form-panel, .preview-panel, .tips-panel { padding: 0; }
    .preview-panel, .tips-panel { padding: 20px; }

    .section-header { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; border-bottom: 1px solid var(--dms-line); }
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
    .dms-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%236b7a90' d='M8 11 3 6h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 34px; }
    .dms-hint { font-size: 11px; color: var(--dms-muted); margin-top: 6px; }
    .dms-error { font-size: 11.5px; color: var(--dms-danger); font-weight: 600; margin-top: 6px; }
    .dms-divider { border-top: 1px solid var(--dms-line); opacity: 1; margin: 8px 0; }

    .dms-input-group { position: relative; }
    .dms-input-group .dms-input { padding-right: 42px; }
    .dms-input-icon-btn {
        position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
        border: none; background: transparent; color: var(--dms-muted);
        width: 30px; height: 30px; border-radius: 8px;
        display: grid; place-items: center;
        cursor: pointer; transition: background .2s ease, color .2s ease;
    }
    .dms-input-icon-btn:hover { background: rgba(23,105,210,.08); color: var(--dms-primary); }

    #ic_pekerja { letter-spacing: .5px; }

    .save-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none;
        border-radius: 10px;
        font-weight: 700;
        padding: 10px 22px;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .save-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(23,105,210,.28); color: #fff; }

    /* Preview */
    .profile-preview { display: flex; align-items: center; gap: 12px; margin-top: 14px; padding: 14px; border: 1px dashed var(--dms-line); border-radius: 10px; background: rgba(23,105,210,.03); }
    .avatar-circle-lg {
        width: 46px; height: 46px; min-width: 46px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--dms-brand), var(--dms-primary));
        color: #fff; display: grid; place-items: center;
        font-weight: 800; font-size: 16px;
    }
    .preview-name { font-size: 13.5px; font-weight: 700; color: var(--dms-brand); }
    .preview-email { font-size: 11.5px; color: var(--dms-muted); }
    .preview-bahagian { font-size: 11px; color: var(--dms-mint); font-weight: 600; margin-top: 2px; }

    .meta-list { list-style: none; padding: 0; margin: 14px 0 0; display: flex; flex-direction: column; gap: 12px; }
    .meta-list li { display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding-bottom: 10px; border-bottom: 1px dashed var(--dms-line); }
    .meta-list li:last-child { border-bottom: none; padding-bottom: 0; }
    .meta-list li span { color: var(--dms-muted); }
    .meta-list li strong { color: var(--dms-brand); font-weight: 700; }
    /* Peranan */
    .role-chip-group { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
    .role-chip {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px; border: 1px solid var(--dms-line); border-radius: 10px;
        background: var(--dms-surface-strong); cursor: pointer;
        transition: border-color .2s ease, background .2s ease;
    }
    .role-chip:hover { border-color: var(--dms-primary); }
    .role-chip-input { display: none; }
    .role-chip-check {
        width: 20px; height: 20px; border-radius: 6px; border: 1.5px solid var(--dms-line);
        display: grid; place-items: center; color: transparent; font-size: 12px; transition: all .2s ease;
    }
    .role-chip-input:checked ~ .role-chip-check { background: var(--dms-primary); border-color: var(--dms-primary); color: #fff; }
    .role-chip:has(.role-chip-input:checked) { border-color: var(--dms-primary); background: rgba(23,105,210,.06); }
    .role-chip-text { display: flex; flex-direction: column; line-height: 1.3; }
    .role-chip-text strong { font-size: 12.5px; color: var(--dms-brand); }
    .role-chip-text small { font-size: 11px; color: var(--dms-muted); }
    .role-readonly { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .role-badge { background: rgba(23,105,210,.1); color: var(--dms-primary); font-weight: 700; font-size: 11.5px; padding: 6px 12px; border-radius: 999px; }
</style>
@endpush

@push('scripts')
<script>
    (() => {
        const icInput = document.getElementById('ic_pekerja');
        const emailInput = document.getElementById('email');
        const namaInput = document.getElementById('nama_staff');
        const bahagianSelect = document.getElementById('bahagian_id');
        const previewAvatar = document.getElementById('previewAvatar');
        const previewNama = document.getElementById('previewNama');
        const previewEmail = document.getElementById('previewEmail');
        const previewBahagian = document.getElementById('previewBahagian');

        // Digits only for IC
        if (icInput) {
            icInput.addEventListener('input', () => {
                icInput.value = icInput.value.replace(/\D/g, '').slice(0, 12);
            });
        }

        function updatePreview() {
            const nama = namaInput.value.trim();
            previewAvatar.textContent = nama ? nama.charAt(0).toUpperCase() : '?';
            previewNama.textContent = nama || 'Nama akan dipaparkan di sini';
            previewEmail.textContent = emailInput.value.trim() || 'email@pkink.com';
            const selected = bahagianSelect.options[bahagianSelect.selectedIndex];
            previewBahagian.textContent = (selected && selected.value) ? selected.textContent : 'Bahagian belum dipilih';
        }

        [namaInput, emailInput, bahagianSelect].forEach(el => {
            if (el) el.addEventListener('input', updatePreview);
            if (el) el.addEventListener('change', updatePreview);
        });

        // Password show/hide
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePasswordIcon');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                const isHidden = passwordInput.type === 'password';
                passwordInput.type = isHidden ? 'text' : 'password';
                toggleIcon.classList.toggle('bi-eye', !isHidden);
                toggleIcon.classList.toggle('bi-eye-slash', isHidden);
            });
        }
    })();
</script>
@endpush