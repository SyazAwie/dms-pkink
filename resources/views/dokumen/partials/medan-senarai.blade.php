{{--
    Semua medan EAV bagi satu Jenis Dokumen. old() diutamakan supaya nilai yang
    ditaip kekal selepas ralat validasi.

    Parameter:
      $medanSenarai  koleksi App\Models\DokumenField (tersusun)
      $nilaiSedia    (pilihan) [dokumen_field_id => nilai tersimpan] daripada DB atau cadangan AI
      $diisiAi       (pilihan) true jika $nilaiSedia ialah cadangan AI (tag "Diisi AI")
      $nilaiAutoIsi  (pilihan) [dokumen_field_id => nilai profil] daripada JenisMedan::nilaiAutoIsi():
                     medan ini dikunci dan nilainya mengatasi old()/$nilaiSedia
      $medanPejabat  (pilihan) paparan medan seksyen pejabat (JenisMedan::idMedanPejabat):
                     null      seperti biasa (Imbas & Arkib)
                     'lipat'   dalam <details> berlipat, tidak wajib (Muat Naik)
                     'pilihan' dipaparkan biasa, tidak wajib (Kemaskini)
                     'sembunyi' tidak dipaparkan (Imbas & Hantar; pelayan juga mengabaikannya)
--}}
@php
    use App\Support\JenisMedan;

    // Selepas ralat validasi, guna nilai yang dihantar walaupun kosong (cth. semua kotak
    // semak dinyahtanda tidak dihantar langsung), bukan nilai asal daripada DB/AI.
    $adaOld = session()->hasOldInput();
    $modPejabat = $medanPejabat ?? null;
    $idPejabat = $modPejabat ? JenisMedan::idMedanPejabat($medanSenarai) : [];
    $lipatTerbuka = false;
@endphp
@forelse($medanSenarai as $field)
    @php
        $fid = $field->dokumen_field_id;
        $pejabat = in_array($fid, $idPejabat, true);
    @endphp
    {{-- Tutup kumpulan berlipat bila keluar dari seksyen pejabat (atau seksyen pejabat baharu bermula) --}}
    @if($lipatTerbuka && (!$pejabat || JenisMedan::seksyenPejabat($field)))
        </div></details>
        @php $lipatTerbuka = false; @endphp
    @endif
    @continue($pejabat && $modPejabat === 'sembunyi')
    @if($modPejabat === 'lipat' && JenisMedan::seksyenPejabat($field))
        <details class="medan-pejabat">
            <summary>
                <span class="medan-pejabat-tajuk">{{ $field->nama_field }}</span>
                <span class="medan-pejabat-nota">Untuk kegunaan pejabat (pilihan)</span>
            </summary>
            <div class="medan-pejabat-isi">
            @if(!empty($field->tetapan['bantuan']))<div class="dms-hint mb-2">{{ $field->tetapan['bantuan'] }}</div>@endif
        @php $lipatTerbuka = true; @endphp
        @continue
    @endif
    @php
        $nilaiAsal = $nilaiSedia[$fid] ?? null;
        [$nilaiInput, $nilaiLain] = JenisMedan::untukBorang($field, $nilaiAsal);
        $autoIsi = array_key_exists($fid, $nilaiAutoIsi ?? []);
    @endphp
    @include('dokumen.partials.medan-input', [
        'field' => $field,
        'nilai' => $autoIsi ? $nilaiAutoIsi[$fid] : ($adaOld ? old("medan_data.{$fid}") : $nilaiInput),
        'nilaiLain' => $adaOld ? old("medan_lain.{$fid}") : $nilaiLain,
        'diisiAi' => ($diisiAi ?? false) && $nilaiAsal !== null,
        'autoIsi' => $autoIsi,
        'tidakWajib' => $pejabat,
    ])
@empty
    <div class="dms-hint py-2">Jenis Dokumen ini tiada medan didaftarkan.</div>
@endforelse
@if($lipatTerbuka)
    </div></details>
@endif
