<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0; padding:0; background-color:#f0f4f9; font-family: Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f4f9; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#1B5FAE 0%,#154a8a 100%); padding:24px 32px;">
                            <span style="color:#ffffff; font-size:17px; font-weight:700;">{{ $companyName }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px; color:#1f2937; font-size:15px; line-height:1.7;">
                            <div style="white-space:pre-wrap;">{{ $bodyText }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; border-top:1px solid #e5e7eb; color:#9ca3af; font-size:12px;">
                            Sent by {{ $companyName }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
