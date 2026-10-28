@props(['url', 'logo' => null])
{{-- Kepala emel DMS PKINK: simbol logo + nama sistem (selari dengan sidebar) --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<table cellpadding="0" cellspacing="0" role="presentation" align="center">
<tr>
<td style="padding-right: 12px; vertical-align: middle;">
<img src="{{ $logo ?? asset(\App\Support\LogoEmel::LALUAN) }}" class="logo" width="48" height="48" alt="Logo PKINK">
</td>
<td style="text-align: left; vertical-align: middle;">
<div class="jenama-tajuk">DMS PKINK</div>
<div class="jenama-sub">Sistem Arkib Digital &middot; Perbadanan Kemajuan Iktisad Negeri Kelantan</div>
</td>
</tr>
</table>
</a>
</td>
</tr>
