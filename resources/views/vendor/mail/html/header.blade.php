@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('img/LighthouseMC_Logo.png') }}" class="logo" alt="Lighthouse MC">
</a>
@if (! app()->isProduction())
<div style="margin-top: 8px; padding: 6px 16px; background-color: #f59e0b; color: #1c1917; font-size: 13px; font-weight: bold; border-radius: 4px; display: inline-block; text-transform: uppercase; letter-spacing: 0.05em;">
    {{ strtoupper(config('app.env')) }} Environment — Not Production
</div>
@endif
</td>
</tr>
