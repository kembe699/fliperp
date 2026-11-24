<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your FlipERP account</title>
</head>
@php
    $label = 'padding:14px 0; border-bottom:1px solid #e5e7eb; color:#6b7280; width:42%;';
    $value = 'padding:14px 0; border-bottom:1px solid #e5e7eb; color:#111827; font-weight:600; text-align:right; word-break:break-all;';
    $mono = "font-family:'Courier New', Courier, monospace;";
    $step = 'margin:0 0 8px; font-size:14px; line-height:1.6; color:#374151;';
@endphp
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:Helvetica, Arial, sans-serif; color:#111827; -webkit-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden;">Your sign-in details for {{ $companyName }} on FlipERP.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border:1px solid #e5e7eb; border-top:3px solid #1B5FAE;">
                    <tr>
                        <td style="padding:28px 40px 0;">
                            <img src="{{ \App\Support\Brand::logoUrl() }}" alt="FlipERP" width="160" style="display:block; width:160px; height:auto; border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 40px 8px;">
                            <h1 style="margin:0 0 16px; font-size:22px; line-height:1.3; font-weight:600; color:#111827;">Your account is ready</h1>
                            <p style="margin:0 0 12px; font-size:15px; line-height:1.6; color:#374151;">Dear {{ $userName }},</p>
                            <p style="margin:0 0 12px; font-size:15px; line-height:1.6; color:#374151;">
                                {{ $invitedBy ? $invitedBy.' has' : 'An administrator has' }} created a FlipERP account for you at <strong style="color:#111827;">{{ $companyName }}</strong>. Your sign-in details are below.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e5e7eb; font-size:14px;">
                                <tr><td style="{{ $label }}">Client code</td><td style="{{ $value }} {{ $mono }}">{{ $clientCode }}</td></tr>
                                <tr><td style="{{ $label }}">Email</td><td style="{{ $value }}">{{ $email }}</td></tr>
                                <tr><td style="{{ $label }}">Password</td><td style="{{ $value }} {{ $mono }}">{{ $password }}</td></tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 40px 8px;">
                            <p style="margin:0 0 12px; font-size:15px; font-weight:600; color:#111827;">How to sign in</p>
                            <p style="{{ $step }}">1. Open <a href="{{ $loginUrl }}" style="color:#1B5FAE;">{{ $loginUrl }}</a>.</p>
                            <p style="{{ $step }}">2. Enter the client code <strong style="{{ $mono }}">{{ $clientCode }}</strong>, your email and the password above.</p>
                            <p style="{{ $step }}">3. Select <strong>Sign in</strong>.</p>
                            <p style="{{ $step }}">4. Change your password under your Profile once you are signed in.</p>
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
                                Keep these details private. If you forget your password, ask your administrator to reset it.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px; border-top:1px solid #e5e7eb; font-size:12px; line-height:1.6; color:#9ca3af;">
                            Questions? Reply to this email or contact <a href="mailto:support@nilehc.tech" style="color:#6b7280;">support@nilehc.tech</a>.<br>
                            {{ $companyName }} &middot; Sent with FlipERP
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
