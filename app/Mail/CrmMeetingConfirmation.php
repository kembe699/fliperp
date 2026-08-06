<?php

namespace App\Mail;

use App\Models\CrmMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent automatically the moment a meeting is scheduled — no manual "send"
 * step. Carries the meeting details plus its .ics file so the recipient can
 * drop it straight into their calendar app.
 */
class CrmMeetingConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CrmMeeting $meeting,
        public string $companyName,
        public string $recipientName,
        public string $icsContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Meeting confirmed: {$this->meeting->title}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.crm-meeting-confirmation', with: [
            'meeting' => $this->meeting,
            'companyName' => $this->companyName,
            'recipientName' => $this->recipientName,
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->icsContent, 'meeting-'.$this->meeting->id.'.ics')
                ->withMime('text/calendar'),
        ];
    }
}
