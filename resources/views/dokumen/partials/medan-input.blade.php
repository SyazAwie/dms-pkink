{{--
    Satu medan EAV dalam borang (label + input + ralat). Satu-satunya tempat input medan dirender:
    Muat Naik Dokumen, Kemaskini Dokumen dan skrin Semak Imbas & Arkib. Gaya: medan-gaya.blade.php.

    Parameter:
      $field      App\Models\DokumenField
      $nilai      nilai input semasa (sudah termasuk old()): string/null, atau senarai bagi kotak semak
      $nilaiLain  (pilihan) teks "Lain-lain (nyatakan)"
      $diisiAi    (pilihan) true jika nilai dicadangkan oleh AI
      $autoIsi    (pilihan) true jika nilai daripada profil (tetapan.auto_isi): input dikunci (readonly)
--}}
@php
    use App\Support\JenisMedan;

    $fid = $field->dokumen_field_id;
    $nama = "medan_data[{$fid}]";
    // Kotak semak: senarai nilai; jadual: senarai baris (tatasusunan bersarang)
    $nilaiJadual = $field->jenis_data === 'jadual' && is_array($nilai) ? $nilai : null;
    $nilaiSenarai = $field->jenis_data === 'kotak_semak' && is_array($nilai)
        ? array_map('strval', array_filter($nilai, 'is_scalar'))
        : [];
    $nilai = is_array($nilai) ? '' : (string) ($nilai ?? '');
    $nilaiLain = (string) ($nilaiLain ?? '');
    $wajib = $field->is_required ? 'required' : '';
    // Auto-isi hanya bagi jenis teks/emel (JenisMedan::AUTO_ISI_SERASI); pelayan abaikan nilai yang dihantar
    $kunci = ($autoIsi ?? false) ? 'readonly' : '';
    $kelasRalat = $errors->has("medan_data.{$fid}") ? 'is-invalid' : '';
    $bantuan = $field->tetapan['bantuan'] ?? null;
    $adaLain = JenisMedan::adaLain($field);
    $pilihan = match ($field->jenis_data) {
        'ya_tidak' => JenisMedan::YA_TIDAK,
        default => $field->pilihan ?? [],
    };
    // Nilai tersimpan yang tiada lagi dalam senarai pilihan (pilihan diubah selepas data diisi)
    $nilaiLama = in_array($field->jenis_data, ['dropdown', 'radio', 'ya_tidak'], true)
        && $nilai !== '' && $nilai !== JenisMedan::NILAI_LAIN && !in_array($nilai, $pilihan, true);
    $nilaiLamaSenarai = $field->jenis_data === 'kotak_semak'
        ? array_values(array_diff($nilaiSenarai, $pilihan, [JenisMedan::NILAI_LAIN]))
        : [];
    // Syarat "tunjuk jika": dinilai oleh medan-skrip (sembunyi + nyahaktif input) dan di pelayan
    $syarat = JenisMedan::syarat($field);
    $atributSyarat = $syarat
        ? 'data-tunjuk-kod="' . e($syarat['kod']) . '" data-tunjuk-nilai="' . e($syarat['nilai']) . '"'
        : '';
@endphp
@if(!JenisMedan::adaNilai($field))
    {{-- Tajuk seksyen: pemisah paparan sahaja, tiada input --}}
    <div class="medan-seksyen" data-kod="{{ $field->kod_field }}" {!! $atributSyarat !!}>
        <div class="medan-seksyen-tajuk">{{ $field->nama_field }}</div>
        @if($bantuan)<div class="dms-hint mt-0">{{ $bantuan }}</div>@endif
    </div>
@else
<div class="mb-3 medan-eav" data-jenis="{{ $field->jenis_data }}" data-kod="{{ $field->kod_field }}" {!! $atributSyarat !!}>
    <label class="dms-label" @if(!in_array($field->jenis_data, ['radio', 'ya_tidak', 'kotak_semak', 'jadual'], true)) for="medan_{{ $fid }}" @endif>
        {{ $field->nama_field }}
        @if($field->is_required)<span class="text-danger">*</span>@endif
        @if($autoIsi ?? false)<span class="badge tag-profil">Daripada profil</span>
        @elseif($diisiAi ?? false)<span class="badge tag-ai">Diisi AI</span>@endif
    </label>

    @switch($field->jenis_data)
        @case('nombor')
            <input type="number" step="any" id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }}>
            @break
        @case('wang')
            <div class="medan-wang">
                <span>RM</span>
                <input type="number" step="0.01" min="0" id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" placeholder="0.00" {{ $wajib }}>
            </div>
            @break
        @case('tarikh')
            <input type="date" id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }}>
            @break
        @case('masa')
            <input type="time" id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }}>
            @break
        @case('tarikh_masa')
            <input type="datetime-local" id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }}>
            @break
        @case('emel')
            <input type="email" id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" maxlength="255" placeholder="nama@contoh.com" {{ $wajib }} {{ $kunci }}>
            @break
        @case('telefon')
            <input type="tel" id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" maxlength="20" placeholder="Cth: 09-748 1234" {{ $wajib }}>
            @break
        @case('url')
            <input type="url" id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" maxlength="2048" placeholder="https://" {{ $wajib }}>
            @break
        @case('dropdown')
            <select id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input medan-pilih {{ $kelasRalat }}" {{ $wajib }}>
                <option value="">Pilih...</option>
                @foreach($pilihan as $p)
                    <option value="{{ $p }}" {{ $nilai === (string) $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
                @if($adaLain)
                    <option value="{{ JenisMedan::NILAI_LAIN }}" {{ $nilai === JenisMedan::NILAI_LAIN ? 'selected' : '' }}>Lain-lain (nyatakan)</option>
                @endif
                @if($nilaiLama)
                    <option value="{{ $nilai }}" selected>{{ $nilai }} (nilai lama)</option>
                @endif
            </select>
            @break
        @case('radio')
        @case('ya_tidak')
            <div class="medan-pilihan-kumpulan {{ $kelasRalat }}" role="radiogroup" aria-label="{{ $field->nama_field }}">
                @foreach($pilihan as $p)
                    <label class="medan-radio">
                        <input type="radio" name="{{ $nama }}" value="{{ $p }}" {{ $nilai === (string) $p ? 'checked' : '' }} {{ $wajib }}>
                        <span>{{ $p }}</span>
                    </label>
                @endforeach
                @if($adaLain)
                    <label class="medan-radio">
                        <input type="radio" name="{{ $nama }}" value="{{ JenisMedan::NILAI_LAIN }}" {{ $nilai === JenisMedan::NILAI_LAIN ? 'checked' : '' }} {{ $wajib }}>
                        <span>Lain-lain (nyatakan)</span>
                    </label>
                @endif
                @if($nilaiLama)
                    <label class="medan-radio">
                        <input type="radio" name="{{ $nama }}" value="{{ $nilai }}" checked {{ $wajib }}>
                        <span>{{ $nilai }} (nilai lama)</span>
                    </label>
                @endif
                @unless($field->is_required)
                    <label class="medan-radio medan-radio-kosong">
                        <input type="radio" name="{{ $nama }}" value="" {{ $nilai === '' ? 'checked' : '' }}>
                        <span>Tiada</span>
                    </label>
                @endunless
            </div>
            @break
        @case('kotak_semak')
            {{-- Tiada atribut required pada kotak (HTML akan wajibkan SEMUA kotak); wajib disemak di pelayan --}}
            <div class="medan-pilihan-kumpulan {{ $errors->has("medan_data.{$fid}") || $errors->has("medan_data.{$fid}.*") ? 'is-invalid' : '' }}" role="group" aria-label="{{ $field->nama_field }}">
                @foreach($pilihan as $p)
                    <label class="medan-radio">
                        <input type="checkbox" name="{{ $nama }}[]" value="{{ $p }}" {{ in_array((string) $p, $nilaiSenarai, true) ? 'checked' : '' }}>
                        <span>{{ $p }}</span>
                    </label>
                @endforeach
                @if($adaLain)
                    <label class="medan-radio">
                        <input type="checkbox" name="{{ $nama }}[]" value="{{ JenisMedan::NILAI_LAIN }}" {{ in_array(JenisMedan::NILAI_LAIN, $nilaiSenarai, true) ? 'checked' : '' }}>
                        <span>Lain-lain (nyatakan)</span>
                    </label>
                @endif
            </div>
            @if($nilaiLamaSenarai)
                <div class="dms-hint">Nilai lama yang tiada lagi dalam senarai pilihan: <strong>{{ implode(', ', $nilaiLamaSenarai) }}</strong> (akan dibuang bila disimpan).</div>
            @endif
            @error("medan_data.{$fid}.*") <div class="dms-error">{{ $message }}</div> @enderror
            @break
        @case('jadual')
            @php
                $lajurJadual = \App\Support\MedanJadual::lajur($field);
                $adaBarisTetap = (bool) \App\Support\MedanJadual::barisTetap($field);
                $barisJadual = \App\Support\MedanJadual::susunBaris($field, $nilaiJadual);
            @endphp
            {{-- Tambah/buang baris dikendalikan oleh medan-skrip (delegasi acara, berfungsi juga untuk HTML yang di-fetch) --}}
            <div class="medan-jadual" data-seterusnya="{{ max(array_map('intval', array_keys($barisJadual))) + 1 }}">
                <div class="medan-jadual-wrap">
                    <table class="medan-jadual-input {{ $adaBarisTetap ? 'ada-baris-tetap' : '' }}">
                        <thead>
                            <tr>
                                <th class="medan-jadual-label">{{ $adaBarisTetap ? '' : 'Bil' }}</th>
                                @foreach($lajurJadual as $l)<th>{{ $l['nama'] }}</th>@endforeach
                                @unless($adaBarisTetap)<th aria-label="Tindakan"></th>@endunless
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($barisJadual as $i => $b)
                                @include('dokumen.partials.medan-jadual-baris', ['field' => $field, 'lajur' => $lajurJadual, 'i' => $i, 'b' => $b, 'adaBarisTetap' => $adaBarisTetap])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @unless($adaBarisTetap)
                    <template class="medan-jadual-templat">
                        @include('dokumen.partials.medan-jadual-baris', ['field' => $field, 'lajur' => $lajurJadual, 'i' => '__I__', 'b' => [], 'adaBarisTetap' => false])
                    </template>
                    <button type="button" class="medan-jadual-tambah"><i class="bi bi-plus-lg"></i> Tambah Baris</button>
                @endunless
            </div>
            {{-- Ralat sel dipaparkan dalam BM dengan nombor baris & nama lajur --}}
            @php $kedudukanBaris = array_flip(array_map('strval', array_keys($barisJadual))); @endphp
            @foreach($errors->get("medan_data.{$fid}.*") as $kunciRalat => $_)
                @php
                    $bahagian = explode('.', $kunciRalat);
                    $namaLajur = collect($lajurJadual)->firstWhere('kod', $bahagian[3] ?? null)['nama'] ?? 'Baris';
                    $noBaris = ($kedudukanBaris[$bahagian[2] ?? ''] ?? 0) + 1;
                @endphp
                <div class="dms-error">Baris {{ $noBaris }}, {{ $namaLajur }}: nilai tidak sah.</div>
            @endforeach
            @break
        @case('textarea')
            <textarea id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" rows="3" {{ $wajib }}>{{ $nilai }}</textarea>
            @break
        @default
            <input type="text" id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }} {{ $kunci }}>
    @endswitch

    @if($adaLain)
        {{-- Dipaparkan oleh CSS (:has) hanya bila "Lain-lain" dipilih; lihat medan-gaya --}}
        <div class="medan-lain-wrap">
            <input type="text" name="medan_lain[{{ $fid }}]" class="dms-input {{ $errors->has("medan_lain.{$fid}") ? 'is-invalid' : '' }}"
                   value="{{ $nilaiLain }}" maxlength="255" placeholder="Sila nyatakan" aria-label="{{ $field->nama_field }}: nyatakan Lain-lain">
            @error("medan_lain.{$fid}") <div class="dms-error">{{ $message }}</div> @enderror
        </div>
    @endif

    @if($bantuan)<div class="dms-hint">{{ $bantuan }}</div>@endif
    @error("medan_data.{$fid}") <div class="dms-error">{{ $message }}</div> @enderror
</div>
@endif
