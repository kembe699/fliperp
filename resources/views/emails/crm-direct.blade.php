<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#1B5FAE; padding:20px 32px;">
                            <span style="color:#ffffff; font-size:16px; font-weight:600;">{{ $companyName }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px; color:#1f2937; font-size:14px; line-height:1.6;">
                            <div style="white-space:pre-wrap;">{{ $bodyText }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; border-top:1px solid #e5e7eb; color:#6b7280; font-size:12px;">
                            Sent by {{ $companyName }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
