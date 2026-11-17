@props(['url'])
{{-- FlipERP logo at the top of every notification email (PNG: email clients don't show SVG). --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ \App\Support\Brand::logoUrl() }}" alt="FlipERP" width="180" style="width:180px; max-width:100%; height:auto; border:0;">
</a>
</td>
</tr>
