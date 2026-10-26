{{--
    Semua medan EAV bagi satu Jenis Dokumen. old() diutamakan supaya nilai yang
    ditaip kekal selepas ralat validasi.

    Parameter:
      $medanSenarai  koleksi App\Models\DokumenField (tersusun)
      $nilaiSedia    (pilihan) [dokumen_field_id => nilai tersimpan] daripada DB atau cadangan AI
      $diisiAi       (pilihan) true jika $nilaiSedia ialah cadangan AI (tag "Diisi AI")
      $nilaiAutoIsi  (pilihan) [dokumen_field_id => nilai profil] daripada JenisMedan::nilaiAutoIsi():
                     medan ini dikunci dan nilainya mengatasi old()/$nilaiSedia
--}}
@php
    // Selepas ralat validasi, guna nilai yang dihantar walaupun kosong (cth. semua kotak
    // semak dinyahtanda tidak dihantar langsung), bukan nilai asal daripada DB/AI.
    $adaOld = session()->hasOldInput();
@endphp
@forelse($medanSenarai as $field)
    @php
        $fid = $field->dokumen_field_id;
        $nilaiAsal = $nilaiSedia[$fid] ?? null;
        [$nilaiInput, $nilaiLain] = \App\Support\JenisMedan::untukBorang($field, $nilaiAsal);
        $autoIsi = array_key_exists($fid, $nilaiAutoIsi ?? []);
    @endphp
    @include('dokumen.partials.medan-input', [
        'field' => $field,
        'nilai' => $autoIsi ? $nilaiAutoIsi[$fid] : ($adaOld ? old("medan_data.{$fid}") : $nilaiInput),
        'nilaiLain' => $adaOld ? old("medan_lain.{$fid}") : $nilaiLain,
        'diisiAi' => ($diisiAi ?? false) && $nilaiAsal !== null,
        'autoIsi' => $autoIsi,
    ])
@empty
    <div class="dms-hint py-2">Jenis Dokumen ini tiada medan didaftarkan.</div>
@endforelse
