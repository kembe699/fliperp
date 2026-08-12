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
                            <p style="margin:0 0 16px;">Hello {{ $recipientName }},</p>
                            <p style="margin:0 0 16px;">
                                Please find attached {{ $documentType }} <strong>{{ $referenceNumber }}</strong> from {{ $companyName }}.
                            </p>
                            @if ($documentType === 'Purchase Order')
                            <p style="margin:0 0 16px;">Please review the attached order and confirm receipt.</p>
                            @elseif ($documentType === 'Invoice')
                            <p style="margin:0 0 16px;">Please arrange payment by the due date shown on the attached invoice.</p>
                            @else
                            <p style="margin:0 0 16px;">Please review the attached document at your convenience.</p>
                            @endif
                            <p style="margin:0;">Thanks,<br>{{ $companyName }}</p>
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
