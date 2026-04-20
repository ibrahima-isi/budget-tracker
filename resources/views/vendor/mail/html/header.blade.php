@props(['url'])
@php $settings = \App\Models\Setting::instance(); @endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
    @if ($settings->logo_path && file_exists(storage_path('app/public/' . $settings->logo_path)))
        <img src="{{ rtrim(config('app.url'), '/') . '/storage/' . $settings->logo_path }}"
             class="logo"
             alt="{{ $settings->business_name }}"
             style="max-height:60px; max-width:200px; width:auto; height:auto;">
    @else
        <span style="font-size:20px; font-weight:700; color:#18181b;">
            {{ $settings->business_name }}
        </span>
    @endif
</a>
</td>
</tr>
