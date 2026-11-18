<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:0; background-color:#f0f4f9; font-family: Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f4f9; padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#1B5FAE 0%,#154a8a 100%); padding:24px 32px;">
                            <span style="color:#ffffff; font-size:17px; font-weight:700;">{{ $companyName }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px; color:#1f2937; font-size:15px; line-height:1.7;">
                            {!! $bodyHtml !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; border-top:1px solid #e5e7eb; color:#9ca3af; font-size:12px; line-height:1.6;">
                            You're receiving this because you're in touch with {{ $companyName }}.
                            @if ($unsubscribeUrl)
                                <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">Unsubscribe</a>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @if ($pixelUrl)
        <img src="{{ $pixelUrl }}" alt="" width="1" height="1" style="display:block; width:1px; height:1px; border:0;">
    @endif
</body>
</html>
