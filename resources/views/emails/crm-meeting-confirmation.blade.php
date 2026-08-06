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
                        <td style="background:linear-gradient(135deg,#1B5FAE 0%,#154a8a 100%); padding:32px;">
                            <div style="display:inline-block; background-color:rgba(255,255,255,0.15); border-radius:999px; padding:6px 14px; font-size:12px; font-weight:600; color:#ffffff; letter-spacing:0.04em; text-transform:uppercase;">
                                Meeting Confirmed
                            </div>
                            <p style="margin:16px 0 0; color:#ffffff; font-size:22px; font-weight:700; line-height:1.3;">
                                {{ $meeting->title }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <p style="margin:0; color:#1f2937; font-size:15px; line-height:1.6;">
                                Hi {{ $recipientName }},
                            </p>
                            <p style="margin:8px 0 0; color:#4b5563; font-size:14px; line-height:1.6;">
                                {{ $companyName }} has scheduled a meeting with you. Here are the details:
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; border:1px solid #e5e7eb; border-radius:10px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding-bottom:14px; width:28px; vertical-align:top; color:#1B5FAE; font-size:16px;">&#128197;</td>
                                                <td style="padding-bottom:14px;">
                                                    <p style="margin:0; font-size:13px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:0.03em;">Date &amp; Time</p>
                                                    <p style="margin:2px 0 0; font-size:15px; color:#111827; font-weight:600;">{{ $meeting->scheduled_at->format('l, F j, Y') }}</p>
                                                    <p style="margin:2px 0 0; font-size:14px; color:#374151;">{{ $meeting->scheduled_at->format('g:i A') }} &middot; {{ $meeting->duration_minutes }} minutes</p>
                                                </td>
                                            </tr>
                                            @if($meeting->location)
                                            <tr>
                                                <td style="padding-bottom:14px; width:28px; vertical-align:top; color:#1B5FAE; font-size:16px;">&#128205;</td>
                                                <td style="padding-bottom:14px;">
                                                    <p style="margin:0; font-size:13px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:0.03em;">Location</p>
                                                    <p style="margin:2px 0 0; font-size:14px; color:#374151;">{{ $meeting->location }}</p>
                                                </td>
                                            </tr>
                                            @endif
                                            @if($meeting->meeting_link)
                                            <tr>
                                                <td style="padding-bottom:14px; width:28px; vertical-align:top; color:#1B5FAE; font-size:16px;">&#128279;</td>
                                                <td style="padding-bottom:14px;">
                                                    <p style="margin:0; font-size:13px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:0.03em;">Meeting Link</p>
                                                    <a href="{{ $meeting->meeting_link }}" style="display:inline-block; margin-top:8px; background-color:#1B5FAE; color:#ffffff; text-decoration:none; font-size:13px; font-weight:600; padding:9px 18px; border-radius:6px;">Join Meeting</a>
                                                </td>
                                            </tr>
                                            @endif
                                            @if($meeting->description)
                                            <tr>
                                                <td style="width:28px; vertical-align:top; color:#1B5FAE; font-size:16px;">&#128221;</td>
                                                <td>
                                                    <p style="margin:0; font-size:13px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:0.03em;">Notes</p>
                                                    <p style="margin:2px 0 0; font-size:14px; color:#374151; line-height:1.5; white-space:pre-wrap;">{{ $meeting->description }}</p>
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:4px 32px 28px;">
                            <p style="margin:0; font-size:13px; color:#6b7280; line-height:1.6;">
                                A calendar invite (.ics) is attached — add it to Google, Outlook, or Apple Calendar with one click.
                            </p>
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
