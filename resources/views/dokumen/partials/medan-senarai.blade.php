{{--
    Semua medan EAV bagi satu Jenis Dokumen. old() diutamakan supaya nilai yang
    ditaip kekal selepas ralat validasi.

    Parameter:
      $medanSenarai  koleksi App\Models\DokumenField (tersusun)
      $nilaiSedia    (pilihan) [dokumen_field_id => nilai tersimpan] daripada DB atau cadangan AI
      $diisiAi       (pilihan) true jika $nilaiSedia ialah cadangan AI (tag "Diisi AI")
--}}
@forelse($medanSenarai as $field)
    @php
        $fid = $field->dokumen_field_id;
        $nilaiAsal = $nilaiSedia[$fid] ?? null;
        [$nilaiInput, $nilaiLain] = \App\Support\JenisMedan::untukBorang($field, $nilaiAsal);
    @endphp
    @include('dokumen.partials.medan-input', [
        'field' => $field,
        'nilai' => old("medan_data.{$fid}", $nilaiInput),
        'nilaiLain' => old("medan_lain.{$fid}", $nilaiLain),
        'diisiAi' => ($diisiAi ?? false) && $nilaiAsal !== null,
    ])
@empty
    <div class="dms-hint py-2">Jenis Dokumen ini tiada medan didaftarkan.</div>
@endforelse
