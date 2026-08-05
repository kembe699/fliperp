<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Free-form email composed from the CRM drawer's Email tab — as opposed to
 * DocumentMail, which always wraps a fixed "here's your PDF" message, this
 * carries whatever subject/body the sender wrote. Optionally carries a PDF
 * (the "send quotation to contact" flow attaches the real quotation PDF).
 */
class CrmDirectEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public string $companyName,
        public ?string $pdfContent = null,
        public ?string $pdfFilename = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.crm-direct', with: [
            'bodyText' => $this->bodyText,
            'companyName' => $this->companyName,
        ]);
    }

    public function attachments(): array
    {
        if (! $this->pdfContent) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
