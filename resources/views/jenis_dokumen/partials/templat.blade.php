{{-- Templat borang kosong (Fasa TB): senarai versi + muat naik versi baharu --}}
<div class="glass-panel templat-panel mb-4" id="templat">
    <div class="summary-head px-1 mb-2">
        <div>
            <div class="summary-label">TEMPLAT BORANG KOSONG</div>
            <div class="dms-hint">AI membezakan teks bercetak borang dengan tulisan pengisi (Imbas & Arkib / Hantar).</div>
        </div>
    </div>

    @forelse($templat as $t)
        @php
            $kelas = match ($t->status_ocr) { 'Selesai' => 'ocr-selesai', 'Ralat' => 'ocr-ralat', default => 'ocr-pending' };
        @endphp
        <div class="templat-item {{ $t->aktif ? '' : 'templat-tidak-aktif' }}">
            <div class="d-flex align-items-start justify-content-between gap-2">
                <div class="min-w-0">
                    <div class="templat-nama" title="{{ $t->nama_asal }}">
                        <i class="bi bi-file-earmark-ruled me-1"></i> Versi {{ $t->versi }} &middot; {{ $t->nama_asal }}
                    </div>
                    <div class="dms-hint mb-0">
                        {{ $t->created_at?->translatedFormat('d M Y') }}{{ $t->pemuatNaik ? ' · '.$t->pemuatNaik->nama_staff : '' }}{{ $t->catatan ? ' · '.$t->catatan : '' }}
                    </div>
                </div>
                <span class="badge fail-status {{ $kelas }}" title="Status OCR templat">{{ $t->status_ocr }}</span>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <a href="{{ route('templat.papar', $t->templat_borang_id) }}" target="_blank" rel="noopener" class="outline-button kekal-label text-decoration-none">
                    <i class="bi bi-eye"></i> <span>Pratonton</span>
                </a>
                <form action="{{ route('templat.togol', $t->templat_borang_id) }}" method="POST">
                    @csrf
                    <button type="submit" class="outline-button kekal-label">
                        <i class="bi {{ $t->aktif ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i> <span>{{ $t->aktif ? 'Aktif' : 'Tidak aktif' }}</span>
                    </button>
                </form>
                <form action="{{ route('templat.destroy', $t->templat_borang_id) }}" method="POST" onsubmit="return confirm('Padam templat versi {{ $t->versi }}?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="outline-button kekal-label text-danger"><i class="bi bi-trash"></i> <span>Padam</span></button>
                </form>
            </div>
        </div>
    @empty
        <div class="dms-hint mb-2">Belum ada templat. Muat naik borang kosong (belum diisi) bagi kategori ini.</div>
    @endforelse

    <form action="{{ route('templat.store', $dokumen->jenis_dokumen_id) }}" method="POST" enctype="multipart/form-data" class="mt-3">
        @csrf
        <label class="dms-label" for="templat_fail">{{ $templat->isEmpty() ? 'Muat Naik Templat' : 'Muat Naik Versi Baharu' }}</label>
        <input type="file" name="templat" id="templat_fail" class="dms-input @error('templat') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png" required>
        @error('templat') <div class="dms-error">{{ $message }}</div> @enderror
        <input type="text" name="catatan_templat" class="dms-input mt-2" maxlength="255" placeholder="Catatan (pilihan), cth. versi 2026" aria-label="Catatan templat">
        <div class="dms-hint mt-1">PDF/JPG/PNG, maks 10MB. Versi lama kekal (imbasan lama masih dikenali); nyahaktifkan jika tidak digunakan lagi.</div>
        <button type="submit" class="btn text-white save-btn mt-2 w-100"><i class="bi bi-upload me-1"></i> Muat Naik</button>
    </form>
</div>

@push('styles')
<style>
    .templat-panel { padding: 18px; }
    .templat-item { padding: 10px 12px; border: 1px solid var(--dms-line); border-radius: 10px; margin-bottom: 8px; }
    .templat-tidak-aktif { opacity: .6; }
    .templat-nama { font-size: 12.5px; font-weight: 700; color: var(--dms-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .templat-panel .outline-button { height: 32px; font-size: 11.5px; padding: 0 10px; }
    .templat-panel .fail-status { font-weight: 700; padding: 4px 8px; border-radius: 6px; font-size: 10px; white-space: nowrap; }
    .templat-panel .ocr-selesai { background: rgba(37, 169, 121, .1); color: var(--dms-mint); }
    .templat-panel .ocr-pending { background: rgba(245, 158, 11, .14); color: #b45309; }
    .templat-panel .ocr-ralat { background: rgba(220, 53, 69, .1); color: var(--dms-danger); }
    .min-w-0 { min-width: 0; }
</style>
@endpush
