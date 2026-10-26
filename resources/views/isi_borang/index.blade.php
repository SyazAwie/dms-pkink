@extends('layouts.app')

@section('title', 'Isi Borang')

@section('content')

<div class="page-intro">
    <div>
        <h2>Isi Borang</h2>
        <p>Pilih borang yang ingin dihantar. <strong>Isi Digital</strong> jika anda belum mengisi borang kertas, atau <strong>Imbas &amp; Hantar</strong> jika borang fizikal sudah diisi; sistem akan cuba isi borang digital daripada imbasan.</p>
    </div>
    <div class="carian-borang">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" id="cariBorang" class="dms-input" placeholder="Cari nama atau kod borang..." aria-label="Cari borang">
    </div>
</div>

@forelse($kumpulan as $kategori => $senarai)
    <section class="kumpulan-borang mb-4" data-kumpulan>
        <h3 class="kumpulan-tajuk">{{ $kategori }}</h3>
        <div class="grid-borang">
            @foreach($senarai as $jenis)
                @php
                    $bilMedan = $jenis->fields->filter(fn ($f) => \App\Support\JenisMedan::adaNilai($f))->count();
                    $bilAutoIsi = $jenis->fields->filter(fn ($f) => \App\Support\JenisMedan::autoIsi($f) !== null)->count();
                @endphp
                <article class="glass-panel kad-borang" data-cari="{{ mb_strtolower($jenis->nama_dokumen . ' ' . $jenis->kod_dokumen . ' ' . $kategori) }}">
                    <div class="kad-borang-kepala">
                        <span class="kad-borang-kod">{{ $jenis->kod_dokumen }}</span>
                        <h4>{{ $jenis->nama_dokumen }}</h4>
                    </div>
                    <div class="kad-borang-meta">
                        <span><i class="bi bi-ui-checks"></i> {{ $bilMedan }} medan</span>
                        @if($bilAutoIsi)
                            <span><i class="bi bi-person-check"></i> {{ $bilAutoIsi }} diisi daripada profil</span>
                        @endif
                    </div>
                    <div class="kad-borang-tindakan">
                        <a href="{{ route('dokumen.create', ['jenis' => $jenis->jenis_dokumen_id]) }}" class="btn text-white save-btn">
                            <i class="bi bi-pencil-square me-1"></i> Isi Digital
                        </a>
                        <a href="{{ route('imbas-hantar.create', ['jenis' => $jenis->jenis_dokumen_id]) }}" class="outline-button text-decoration-none">
                            <i class="bi bi-send-check"></i> <span>Imbas &amp; Hantar</span>
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@empty
    <div class="glass-panel p-4 dms-hint text-center">Belum ada kategori borang. Sila hubungi pentadbir sistem.</div>
@endforelse

<div id="tiadaPadanan" class="glass-panel p-4 dms-hint text-center" hidden>Tiada borang sepadan dengan carian.</div>

@endsection

@push('styles')
<style>
    .carian-borang { position: relative; min-width: 260px; }
    .carian-borang i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--dms-muted); }
    .dms-input {
        width: 100%; padding: 10px 14px 10px 36px; border: 1px solid var(--dms-line); border-radius: 10px;
        background: var(--dms-surface-strong); font-size: 13px; color: var(--dms-text); outline: none;
    }
    .dms-hint { font-size: 12px; color: var(--dms-muted); }
    .kumpulan-tajuk { margin: 0 0 10px; color: var(--dms-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
    .grid-borang { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
    .kad-borang { display: flex; flex-direction: column; gap: 12px; padding: 18px; }
    .kad-borang-kepala h4 { margin: 6px 0 0; color: var(--dms-brand); font-size: 14.5px; font-weight: 800; line-height: 1.35; }
    .kad-borang-kod {
        display: inline-block; padding: 2px 8px; border-radius: 999px; background: rgba(23,105,210,.08);
        color: var(--dms-primary); font-size: 10.5px; font-weight: 800; letter-spacing: .3px;
    }
    .kad-borang-meta { display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: 11.5px; color: var(--dms-muted); }
    .kad-borang-meta i { color: var(--dms-mint); }
    .kad-borang-tindakan { display: flex; flex-wrap: wrap; gap: 8px; margin-top: auto; }
    .kad-borang-tindakan .save-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none; border-radius: 8px; font-size: 12px; font-weight: 700; padding: 8px 14px;
    }
    .kad-borang-tindakan .save-btn:hover { color: #fff; }
    @media (max-width: 576px) { .carian-borang { min-width: 0; width: 100%; } }
</style>
@endpush

@push('scripts')
<script>
    // Tapis kad borang ikut carian (nama, kod, kategori); sembunyi kumpulan yang kosong
    (() => {
        const input = document.getElementById('cariBorang');
        const tiada = document.getElementById('tiadaPadanan');
        input.addEventListener('input', () => {
            const kata = input.value.trim().toLowerCase();
            let jumlah = 0;
            document.querySelectorAll('[data-kumpulan]').forEach((kumpulan) => {
                let ada = 0;
                kumpulan.querySelectorAll('.kad-borang').forEach((kad) => {
                    const padan = kad.dataset.cari.includes(kata);
                    kad.hidden = !padan;
                    ada += padan;
                });
                kumpulan.hidden = ada === 0;
                jumlah += ada;
            });
            tiada.hidden = jumlah > 0;
        });
    })();
</script>
@endpush
