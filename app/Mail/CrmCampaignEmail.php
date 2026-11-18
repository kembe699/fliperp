<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * One marketing campaign email to one recipient: personalised body with
 * tracked links, an open-tracking pixel, and a one-click unsubscribe link
 * (also in the List-Unsubscribe headers mail apps show as "Unsubscribe").
 */
class CrmCampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyHtml,
        public string $companyName,
        public ?string $unsubscribeUrl = null,
        public ?string $pixelUrl = null,
        public ?string $replyToEmail = null,
        // The API endpoint mail apps POST to for one-click unsubscribe (RFC 8058);
        // $unsubscribeUrl is the page a person opens from the link in the email.
        public ?string $oneClickUnsubscribeUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->companyName),
            replyTo: $this->replyToEmail ? [new Address($this->replyToEmail, $this->companyName)] : [],
            subject: $this->subjectLine,
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: $this->oneClickUnsubscribeUrl ? [
            'List-Unsubscribe' => "<{$this->oneClickUnsubscribeUrl}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ] : []);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.crm-campaign', with: [
            'bodyHtml' => $this->bodyHtml,
            'companyName' => $this->companyName,
            'unsubscribeUrl' => $this->unsubscribeUrl,
            'pixelUrl' => $this->pixelUrl,
        ]);
    }
}
