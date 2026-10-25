{{--
    Satu medan EAV dalam borang (label + input + ralat). Satu-satunya tempat input medan dirender:
    Muat Naik Dokumen, Kemaskini Dokumen dan skrin Semak Imbas & Arkib. Gaya: medan-gaya.blade.php.

    Parameter:
      $field      App\Models\DokumenField
      $nilai      nilai input semasa (sudah termasuk old()), string atau null
      $nilaiLain  (pilihan) teks "Lain-lain (nyatakan)"
      $diisiAi    (pilihan) true jika nilai dicadangkan oleh AI
--}}
@php
    use App\Support\JenisMedan;

    $fid = $field->dokumen_field_id;
    $nama = "medan_data[{$fid}]";
    $nilai = (string) ($nilai ?? '');
    $nilaiLain = (string) ($nilaiLain ?? '');
    $wajib = $field->is_required ? 'required' : '';
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
@endphp
<div class="mb-3 medan-eav" data-jenis="{{ $field->jenis_data }}">
    <label class="dms-label" @if(!in_array($field->jenis_data, ['radio', 'ya_tidak'], true)) for="medan_{{ $fid }}" @endif>
        {{ $field->nama_field }}
        @if($field->is_required)<span class="text-danger">*</span>@endif
        @if($diisiAi ?? false)<span class="badge tag-ai">Diisi AI</span>@endif
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
            <input type="email" id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" maxlength="255" placeholder="nama@contoh.com" {{ $wajib }}>
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
        @case('textarea')
            <textarea id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" rows="3" {{ $wajib }}>{{ $nilai }}</textarea>
            @break
        @default
            <input type="text" id="medan_{{ $fid }}" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }}>
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
