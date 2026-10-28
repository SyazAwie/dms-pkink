{{-- Kotak peringatan keselamatan (kuning) untuk emel yang membawa pautan atau kata laluan --}}
<table class="peringatan" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="peringatan-isi">
{{ Illuminate\Mail\Markdown::parse($slot) }}
</td>
</tr>
</table>
