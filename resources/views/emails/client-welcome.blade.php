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
                            <span style="color:#ffffff; font-size:17px; font-weight:700;">Nile Hive Concept Co. Ltd</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px; color:#1f2937; font-size:15px; line-height:1.7;">
                            <p style="margin:0 0 16px;">Welcome, <strong>{{ $companyName }}</strong> — your workspace is ready.</p>
                            <p style="margin:0 0 16px;">Sign in with the details below. You'll be asked to change your password on first use — this is the only time it will be shown.</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; margin:16px 0;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 8px; font-size:13px; color:#6b7280;">Client Code</p>
                                        <p style="margin:0 0 16px; font-size:16px; font-weight:700; font-family: 'Courier New', monospace;">{{ $clientCode }}</p>
                                        <p style="margin:0 0 8px; font-size:13px; color:#6b7280;">Email</p>
                                        <p style="margin:0 0 16px; font-size:16px; font-weight:700;">{{ $adminEmail }}</p>
                                        <p style="margin:0 0 8px; font-size:13px; color:#6b7280;">Temporary Password</p>
                                        <p style="margin:0; font-size:16px; font-weight:700; font-family: 'Courier New', monospace;">{{ $tempPassword }}</p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px;">
                                <a href="{{ $loginUrl }}" style="display:inline-block; background-color:#1B5FAE; color:#ffffff; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:600;">Sign In</a>
                            </p>
                            <p style="margin:0; color:#6b7280; font-size:13px;">Need help? Contact support@nilehc.tech.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; border-top:1px solid #e5e7eb; color:#9ca3af; font-size:12px;">
                            Sent by Nile Hive Concept Co. Ltd
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
