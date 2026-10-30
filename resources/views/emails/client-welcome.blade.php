<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to FlipERP</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:Helvetica, Arial, sans-serif; color:#111827; -webkit-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden;">Your FlipERP workspace for {{ $companyName }} is ready. Your sign-in details are inside.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border:1px solid #e5e7eb; border-top:3px solid #1B5FAE;">
                    <tr>
                        <td style="padding:28px 40px 0;">
                            <span style="font-size:18px; font-weight:700; letter-spacing:-0.2px; color:#111827;">Flip<span style="color:#1B5FAE;">ERP</span></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 40px 8px;">
                            <h1 style="margin:0 0 16px; font-size:22px; line-height:1.3; font-weight:600; color:#111827;">Your workspace is ready</h1>
                            <p style="margin:0 0 12px; font-size:15px; line-height:1.6; color:#374151;">
                                An account has been set up for <strong style="color:#111827;">{{ $companyName }}</strong>. Use the details below to sign in.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e5e7eb; font-size:14px;">
                                <tr>
                                    <td style="padding:14px 0; border-bottom:1px solid #e5e7eb; color:#6b7280; width:42%;">Client code</td>
                                    <td style="padding:14px 0; border-bottom:1px solid #e5e7eb; color:#111827; font-weight:600; font-family:'Courier New', Courier, monospace; text-align:right;">{{ $clientCode }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 0; border-bottom:1px solid #e5e7eb; color:#6b7280;">Email</td>
                                    <td style="padding:14px 0; border-bottom:1px solid #e5e7eb; color:#111827; font-weight:600; text-align:right; word-break:break-all;">{{ $adminEmail }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 0; border-bottom:1px solid #e5e7eb; color:#6b7280;">Temporary password</td>
                                    <td style="padding:14px 0; border-bottom:1px solid #e5e7eb; color:#111827; font-weight:600; font-family:'Courier New', Courier, monospace; text-align:right;">{{ $tempPassword }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 40px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background-color:#1B5FAE;">
                                        <a href="{{ $loginUrl }}" style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">Sign in to FlipERP</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px 32px;">
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#6b7280;">
                                For security, change your password from your Profile after you sign in. This is the only time this password is sent.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px; border-top:1px solid #e5e7eb; font-size:12px; line-height:1.6; color:#9ca3af;">
                            Questions? Contact <a href="mailto:support@nilehc.tech" style="color:#6b7280;">support@nilehc.tech</a>.<br>
                            FlipERP &middot; You're receiving this because an account was created for {{ $companyName }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
