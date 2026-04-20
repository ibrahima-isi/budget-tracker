@php $settings = \App\Models\Setting::instance(); @endphp
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
    <p style="color:#a1a1aa; font-size:12px; margin:0;">
        © {{ date('Y') }} {{ $settings->business_name }}
        @if($settings->business_email)
            &nbsp;·&nbsp; <a href="mailto:{{ $settings->business_email }}" style="color:#a1a1aa;">{{ $settings->business_email }}</a>
        @endif
        @if($settings->phone)
            &nbsp;·&nbsp; {{ $settings->phone }}
        @endif
    </p>
    <p style="color:#a1a1aa; font-size:11px; margin-top:6px;">
        Vous recevez cet email car vous avez un compte sur {{ $settings->business_name }}.
    </p>
</td>
</tr>
</table>
</td>
</tr>
