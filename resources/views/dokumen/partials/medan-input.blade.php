{{--
    Satu medan EAV dalam borang (label + input + ralat). Satu-satunya tempat input medan dirender:
    Muat Naik Dokumen, Kemaskini Dokumen dan skrin Semak Imbas & Arkib.

    Parameter:
      $field   App\Models\DokumenField
      $nilai   nilai semasa (sudah termasuk old() jika ada), string atau null
      $diisiAi (pilihan) true jika nilai dicadangkan oleh AI
--}}
@php
    $fid = $field->dokumen_field_id;
    $nama = "medan_data[{$fid}]";
    $nilai = (string) ($nilai ?? '');
    $wajib = $field->is_required ? 'required' : '';
    $kelasRalat = $errors->has("medan_data.{$fid}") ? 'is-invalid' : '';
@endphp
<div class="mb-3">
    <label class="dms-label">
        {{ $field->nama_field }}
        @if($field->is_required)<span class="text-danger">*</span>@endif
        @if($diisiAi ?? false)<span class="badge tag-ai">Diisi AI</span>@endif
    </label>

    @switch($field->jenis_data)
        @case('nombor')
            <input type="number" step="0.01" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }}>
            @break
        @case('tarikh')
            <input type="date" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }}>
            @break
        @case('dropdown')
            @php $pilihan = $field->pilihan ?? []; @endphp
            <select name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" {{ $wajib }}>
                <option value="">Pilih...</option>
                @foreach($pilihan as $p)
                    <option value="{{ $p }}" {{ $nilai === (string) $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
                @if($nilai !== '' && !in_array($nilai, $pilihan))
                    <option value="{{ $nilai }}" selected>{{ $nilai }} (nilai lama)</option>
                @endif
            </select>
            @break
        @case('textarea')
            <textarea name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" rows="3" {{ $wajib }}>{{ $nilai }}</textarea>
            @break
        @default
            <input type="text" name="{{ $nama }}" class="dms-input {{ $kelasRalat }}" value="{{ $nilai }}" {{ $wajib }}>
    @endswitch

    @error("medan_data.{$fid}") <div class="dms-error">{{ $message }}</div> @enderror
</div>
