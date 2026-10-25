{{--
    Satu baris medan jadual (medan-input.blade.php). Juga dirender dalam <template>
    dengan $i = '__I__' untuk butang "Tambah Baris".

    Parameter: $field, $lajur (MedanJadual::lajur), $i (indeks baris), $b (nilai baris), $adaBarisTetap
--}}
@php
    $fid = $field->dokumen_field_id;
    $awalan = "medan_data[{$fid}][{$i}]";
@endphp
<tr>
    <td class="medan-jadual-label">
        @if($adaBarisTetap)
            {{ $b[\App\Support\MedanJadual::KUNCI_BARIS] ?? '' }}
            <input type="hidden" name="{{ $awalan }}[{{ \App\Support\MedanJadual::KUNCI_BARIS }}]" value="{{ $b[\App\Support\MedanJadual::KUNCI_BARIS] ?? '' }}">
        @else
            <span class="medan-jadual-bil"></span>
        @endif
    </td>
    @foreach($lajur as $l)
        @php
            $nama = "{$awalan}[{$l['kod']}]";
            $sel = $b[$l['kod']] ?? '';
            $sel = is_scalar($sel) ? (string) $sel : '';
            $ralat = $errors->has("medan_data.{$fid}.{$i}.{$l['kod']}") ? 'is-invalid' : '';
            $label = $l['nama'] . ($adaBarisTetap ? ' — ' . ($b[\App\Support\MedanJadual::KUNCI_BARIS] ?? '') : '');
        @endphp
        <td>
            @switch($l['jenis'] ?? 'teks')
                @case('nombor')
                    <input type="number" step="any" name="{{ $nama }}" value="{{ $sel }}" class="dms-input {{ $ralat }}" aria-label="{{ $label }}">
                    @break
                @case('wang')
                    <input type="number" step="0.01" min="0" name="{{ $nama }}" value="{{ $sel }}" class="dms-input {{ $ralat }}" placeholder="RM" aria-label="{{ $label }}">
                    @break
                @case('tarikh')
                    <input type="date" name="{{ $nama }}" value="{{ $sel }}" class="dms-input {{ $ralat }}" aria-label="{{ $label }}">
                    @break
                @case('masa')
                    <input type="time" name="{{ $nama }}" value="{{ $sel }}" class="dms-input {{ $ralat }}" aria-label="{{ $label }}">
                    @break
                @case('ya_tidak')
                @case('dropdown')
                    @php $pilihanSel = ($l['jenis'] ?? '') === 'ya_tidak' ? \App\Support\JenisMedan::YA_TIDAK : ($l['pilihan'] ?? []); @endphp
                    <select name="{{ $nama }}" class="dms-input {{ $ralat }}" aria-label="{{ $label }}">
                        <option value="">-</option>
                        @foreach($pilihanSel as $p)
                            <option value="{{ $p }}" {{ $sel === (string) $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                    @break
                @default
                    <input type="text" name="{{ $nama }}" value="{{ $sel }}" class="dms-input {{ $ralat }}" maxlength="{{ \App\Support\MedanJadual::MAKS_TEKS_SEL }}" aria-label="{{ $label }}">
            @endswitch
        </td>
    @endforeach
    @unless($adaBarisTetap)
        <td class="medan-jadual-tindakan">
            <button type="button" class="medan-jadual-buang" title="Buang baris" aria-label="Buang baris"><i class="bi bi-x-lg"></i></button>
        </td>
    @endunless
</tr>
