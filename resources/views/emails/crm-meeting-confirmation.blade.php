@php
    // Shown in the booking page's timezone, else the company's, else the app's.
    $tz = $meeting->bookingPage?->timezone
        ?: \App\Models\Company::find($meeting->company_id)?->timezone
        ?: config('app.timezone');
    $start = $meeting->scheduled_at->copy()->setTimezone($tz);
    $end = $start->copy()->addMinutes($meeting->duration_minutes);
    $label = 'padding:12px 0; width:130px; vertical-align:top; font-size:13px; color:#6b7280; border-top:1px solid #e5e7eb;';
    $value = 'padding:12px 0; vertical-align:top; font-size:14px; color:#111827; border-top:1px solid #e5e7eb;';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-top:4px solid #1B5FAE; border-radius:4px;">
                    <tr>
                        <td style="padding:24px 32px; border-bottom:1px solid #e5e7eb;">
                            <span style="font-size:16px; font-weight:700; color:#111827;">{{ $companyName }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px 0;">
                            <p style="margin:0; font-size:12px; font-weight:600; color:#1B5FAE; letter-spacing:0.08em; text-transform:uppercase;">Meeting confirmation</p>
                            <h1 style="margin:8px 0 0; font-size:22px; line-height:1.3; font-weight:700; color:#111827;">{{ $meeting->title }}</h1>
                            <p style="margin:20px 0 0; font-size:15px; line-height:1.6; color:#374151;">Dear {{ $recipientName }},</p>
                            <p style="margin:8px 0 0; font-size:15px; line-height:1.6; color:#374151;">
                                This is to confirm your meeting with {{ $companyName }}. The details are below.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px 4px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="{{ $label }}">Date</td>
                                    <td style="{{ $value }}; font-weight:600;">{{ $start->format('l, j F Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $label }}">Time</td>
                                    <td style="{{ $value }}">{{ $start->format('g:i A') }} – {{ $end->format('g:i A') }} ({{ $start->format('T') }})</td>
                                </tr>
                                <tr>
                                    <td style="{{ $label }}">Duration</td>
                                    <td style="{{ $value }}">{{ $meeting->duration_minutes }} minutes</td>
                                </tr>
                                @if($meeting->location)
                                <tr>
                                    <td style="{{ $label }}">Location</td>
                                    <td style="{{ $value }}">{{ $meeting->location }}</td>
                                </tr>
                                @endif
                                @if($meeting->meeting_link)
                                <tr>
                                    <td style="{{ $label }}">Meeting link</td>
                                    <td style="{{ $value }}; word-break:break-all;"><a href="{{ $meeting->meeting_link }}" style="color:#1B5FAE;">{{ $meeting->meeting_link }}</a></td>
                                </tr>
                                @endif
                                @if($meeting->organizer)
                                <tr>
                                    <td style="{{ $label }}">Host</td>
                                    <td style="{{ $value }}">{{ $meeting->organizer->name }}</td>
                                </tr>
                                @endif
                                @if($meeting->description)
                                <tr>
                                    <td style="{{ $label }}">Notes</td>
                                    <td style="{{ $value }}; line-height:1.5; white-space:pre-wrap;">{{ $meeting->description }}</td>
                                </tr>
                                @endif
                                <tr><td colspan="2" style="border-top:1px solid #e5e7eb; font-size:0; line-height:0;">&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>
                    @if($meeting->meeting_link)
                    <tr>
                        <td style="padding:12px 32px 0;">
                            <a href="{{ $meeting->meeting_link }}" style="display:inline-block; background-color:#1B5FAE; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600; padding:12px 24px; border-radius:4px;">Join meeting</a>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:24px 32px 28px;">
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#6b7280;">
                                A calendar invitation is attached to this email. If you need to reschedule, simply reply to this message.
                            </p>
                            <p style="margin:16px 0 0; font-size:14px; line-height:1.6; color:#374151;">
                                Kind regards,<br>{{ $meeting->organizer?->name ? $meeting->organizer->name.', ' : '' }}{{ $companyName }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; border-top:1px solid #e5e7eb; background-color:#f9fafb; font-size:12px; color:#9ca3af;">
                            {{ $companyName }} &middot; Sent with FlipERP
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
