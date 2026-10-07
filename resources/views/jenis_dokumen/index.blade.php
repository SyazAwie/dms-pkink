@extends('layouts.app')

@section('title', 'Kategori Dokumen')

@section('content')

<!-- Bahagian Pengenalan Halaman -->
<div class="page-intro">
    <div>
        <h2>Senarai Kategori Dokumen</h2>
        <p>Urus senarai kod dan klasifikasi fail untuk Sistem Arkib Digital PKINK.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="date-chip">
            <i class="bi bi-calendar3"></i>
            {{ now()->translatedFormat('d F Y') }}
        </div>
        <a href="{{ route('jenis-dokumen.create') }}" class="btn text-white shadow-sm add-category-btn">
            <i class="bi bi-plus-lg me-2"></i> Tambah Kategori
        </a>
    </div>
</div>

<!-- Notifikasi Berjaya -->
@if(session('success'))
    <div class="alert alert-dismissible fade show dms-alert" role="alert" id="successAlert">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Kad Ringkasan -->
<div class="summary-grid mb-4">
    <div class="glass-panel summary-card">
        <div class="summary-head">
            <div>
                <div class="summary-label">JUMLAH KATEGORI</div>
                <div class="summary-value">{{ $senarai->count() }}</div>
            </div>
            <div class="summary-icon"><i class="bi bi-folder2-open"></i></div>
        </div>
        <div class="summary-detail">Kod &amp; klasifikasi fail berdaftar</div>
    </div>

    <div class="glass-panel summary-card">
        <div class="summary-head">
            <div>
                <div class="summary-label">DIKEMASKINI TERKINI</div>
                <div class="summary-value" style="font-size: 17px;">
                    {{ optional($senarai->sortByDesc('updated_at')->first())->updated_at?->diffForHumans() ?? '—' }}
                </div>
            </div>
            <div class="summary-icon"><i class="bi bi-clock-history"></i></div>
        </div>
        <div class="summary-detail">Rekod terbaharu dikemas kini</div>
    </div>

    <div class="glass-panel summary-card">
        <div class="summary-head">
            <div>
                <div class="summary-label">STATUS SISTEM</div>
                <div class="summary-value" style="font-size: 17px; color: var(--dms-mint);">Aktif</div>
            </div>
            <div class="summary-icon" style="color: var(--dms-mint); background: rgba(37,169,121,.12);"><i class="bi bi-shield-check"></i></div>
        </div>
        <div class="summary-detail">Sistem Arkib Digital PKINK</div>
    </div>
</div>

<!-- Panel Jadual -->
<div class="glass-panel overflow-hidden">
    <div class="section-header px-4 pt-4">
        <div>
            <h3>Senarai Kategori</h3>
            <p>Klik pada tajuk lajur untuk menyusun rekod</p>
        </div>
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="text" id="tableSearch" placeholder="Cari kod atau nama dokumen..." aria-label="Cari kategori dokumen">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle dms-table" id="categoryTable">
            <thead>
                <tr>
                    <th class="ps-4 py-3" data-sort="text">BIL</th>
                    <th class="py-3 sortable" data-sort="text">KOD DOKUMEN <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="py-3 sortable" data-sort="text">NAMA DOKUMEN <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="py-3 sortable" data-sort="text">KATEGORI <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="pe-4 py-3 text-end">TINDAKAN</th>
                </tr>
            </thead>
            <tbody>
                @forelse($senarai as $item)
                <tr>
                    <td class="ps-4 fw-bold row-index" style="font-size: 13px;">{{ $loop->iteration }}</td>
                    <td>
                        <span class="badge kod-badge">{{ $item->kod_dokumen }}</span>
                    </td>
                    <td class="fw-bold" style="font-size: 14px; color: var(--dms-brand);">{{ $item->nama_dokumen }}</td>
                    <td style="font-size: 13px;">
                        @if($item->kategori)
                            <span class="badge kategori-badge">{{ $item->kategori }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="pe-4 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('jenis-dokumen.edit', $item->jenis_dokumen_id) }}"
                               class="outline-button text-decoration-none"
                               data-bs-toggle="tooltip" title="Kemaskini rekod">
                                <i class="bi bi-pencil-square"></i> <span>Edit</span>
                            </a>

                            <button type="button"
                                    class="outline-button btn-delete-trigger"
                                    style="color: var(--dms-danger);"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmDeleteModal"
                                    data-form-action="{{ route('jenis-dokumen.destroy', $item->jenis_dokumen_id) }}"
                                    data-item-name="{{ $item->nama_dokumen }}">
                                <i class="bi bi-trash3"></i> <span>Padam</span>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <div class="text-muted mb-2"><i class="bi bi-folder-x" style="font-size: 32px; opacity: 0.5;"></i></div>
                        <div style="font-size: 14px; font-weight: 700; color: var(--dms-brand);">Tiada rekod kategori dokumen ditemui.</div>
                        <div style="font-size: 12px; color: var(--dms-muted); margin-top: 4px;">Sila klik butang 'Tambah Kategori' untuk memulakan.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div id="noResults" class="text-center py-5 d-none">
            <div class="text-muted mb-2"><i class="bi bi-search" style="font-size: 28px; opacity: 0.5;"></i></div>
            <div style="font-size: 13px; font-weight: 700; color: var(--dms-brand);">Tiada padanan carian ditemui.</div>
        </div>
    </div>

    @if(method_exists($senarai, 'links') && $senarai->hasPages())
        <div class="px-4 pb-4 pt-2">
            {{ $senarai->links() }}
        </div>
    @endif
</div>

<!-- Modal Pengesahan Padam -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content dms-modal">
            <div class="modal-body text-center pt-4 pb-2">
                <div class="modal-warn-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <h5 class="mt-3 mb-2" id="confirmDeleteLabel" style="color: var(--dms-brand); font-weight: 800;">Padam Rekod?</h5>
                <p class="text-muted mb-0" style="font-size: 13px;">
                    Anda pasti mahu memadam kategori <strong id="deleteItemName" style="color: var(--dms-text);"></strong>?
                    Tindakan ini tidak boleh diundur.
                </p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="outline-button" data-bs-dismiss="modal">Batal</button>
                <form id="deleteForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn text-white btn-confirm-delete">
                        <i class="bi bi-trash3 me-1"></i> Ya, Padam
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .add-category-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none;
        border-radius: 10px;
        font-weight: 700;
        padding: 10px 20px;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .add-category-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(23,105,210,.28); color: #fff; }

    .dms-alert {
        border-radius: 12px;
        background: rgba(37, 169, 121, .1);
        border: 1px solid rgba(37, 169, 121, .3);
        color: var(--dms-mint);
        font-weight: 600;
    }

    .section-header { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; padding-bottom: 15px; border-bottom: 1px solid var(--dms-line); }
    .section-header h3 { margin: 0; color: var(--dms-brand); font-size: 16px; font-weight: 800; }
    .section-header p { margin: 4px 0 0; color: var(--dms-muted); font-size: 11px; }

    .table-search { position: relative; }
    .table-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--dms-muted); font-size: 13px; }
    .table-search input {
        width: 260px;
        padding: 9px 12px 9px 34px;
        border: 1px solid var(--dms-line);
        border-radius: 9px;
        background: var(--dms-surface-strong);
        font-size: 12px;
        color: var(--dms-text);
        outline: none;
        transition: border-color .2s ease, box-shadow .2s ease;
    }
    .table-search input:focus { border-color: var(--dms-primary); box-shadow: 0 0 0 3px rgba(23,105,210,.12); }

    .dms-table thead { background: rgba(23, 105, 210, .05); }
    .dms-table thead th { font-size: 11px; color: var(--dms-muted); letter-spacing: .5px; font-weight: 800; border-bottom: 1px solid var(--dms-line); white-space: nowrap; }
    .dms-table thead th.sortable { cursor: pointer; user-select: none; transition: color .2s ease; }
    .dms-table thead th.sortable:hover { color: var(--dms-primary); }
    .sort-icon { font-size: 10px; opacity: .5; margin-left: 4px; }
    .dms-table tbody tr { transition: background .2s ease; }
    .dms-table tbody tr:hover { background: rgba(23, 105, 210, .035); }
    .dms-table tbody { border-top: 1px solid var(--dms-line); }

    .kod-badge {
        background: rgba(23, 105, 210, .1);
        color: var(--dms-primary);
        font-weight: 800;
        padding: 7px 10px;
        border-radius: 6px;
        letter-spacing: .3px;
    }
    .kategori-badge {
        background: rgba(37, 169, 121, .1);
        color: var(--dms-mint);
        font-weight: 700;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 11px;
    }

    .btn-delete-trigger { cursor: pointer; }

    .dms-modal {
        border-radius: 16px;
        border: 1px solid var(--dms-border);
        background: var(--dms-surface-strong);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        box-shadow: var(--dms-shadow);
    }
    .modal-warn-icon {
        width: 56px; height: 56px;
        margin: 0 auto;
        display: grid; place-items: center;
        border-radius: 50%;
        background: rgba(196, 61, 78, .1);
        color: var(--dms-danger);
        font-size: 24px;
    }
    .btn-confirm-delete {
        background: var(--dms-danger);
        border: none;
        border-radius: 8px;
        font-weight: 700;
        font-size: 12px;
        padding: 9px 18px;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .btn-confirm-delete:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(196,61,78,.28); color: #fff; }

    #categoryTable tbody tr { animation: rowFadeIn .35s ease both; }
    @keyframes rowFadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
</style>
@endpush

@push('scripts')
<script>
    (() => {
        // Auto-dismiss success alert
        const alertEl = document.getElementById('successAlert');
        if (alertEl && window.bootstrap) {
            setTimeout(() => {
                bootstrap.Alert.getOrCreateInstance(alertEl).close();
            }, 4000);
        }

        // Delete confirmation modal wiring
        const deleteModal = document.getElementById('confirmDeleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', (event) => {
                const trigger = event.relatedTarget;
                if (!trigger) return;
                const action = trigger.getAttribute('data-form-action');
                const name = trigger.getAttribute('data-item-name');
                document.getElementById('deleteForm').setAttribute('action', action);
                document.getElementById('deleteItemName').textContent = name;
            });
        }

        // Tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            if (window.bootstrap) new bootstrap.Tooltip(el);
        });

        // Live search filter
        const searchInput = document.getElementById('tableSearch');
        const table = document.getElementById('categoryTable');
        const noResults = document.getElementById('noResults');
        if (searchInput && table) {
            searchInput.addEventListener('input', () => {
                const term = searchInput.value.trim().toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                let visibleCount = 0;
                rows.forEach(row => {
                    if (row.children.length < 2) return; // skip empty-state row
                    const text = row.textContent.toLowerCase();
                    const match = text.includes(term);
                    row.style.display = match ? '' : 'none';
                    if (match) visibleCount++;
                });
                noResults.classList.toggle('d-none', visibleCount !== 0 || term === '');
                table.style.display = (visibleCount === 0 && term !== '') ? 'none' : '';
            });
        }

        // Client-side column sorting
        const sortableHeaders = document.querySelectorAll('.dms-table th.sortable');
        sortableHeaders.forEach((th, colIndex) => {
            let asc = true;
            th.addEventListener('click', () => {
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.children.length > 1);
                const cellIndex = Array.from(th.parentElement.children).indexOf(th);

                rows.sort((a, b) => {
                    const aText = a.children[cellIndex]?.textContent.trim().toLowerCase() || '';
                    const bText = b.children[cellIndex]?.textContent.trim().toLowerCase() || '';
                    return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
                });

                rows.forEach(row => tbody.appendChild(row));
                asc = !asc;

                sortableHeaders.forEach(h => h.querySelector('.sort-icon')?.classList.remove('bi-arrow-up', 'bi-arrow-down'));
                const icon = th.querySelector('.sort-icon');
                icon.classList.remove('bi-arrow-down-up');
                icon.classList.add(asc ? 'bi-arrow-down' : 'bi-arrow-up');
            });
        });
    })();
</script>
@endpush