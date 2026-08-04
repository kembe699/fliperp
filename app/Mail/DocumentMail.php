<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic "here's your PDF" email shared by purchase orders, quotations, and
 * invoices — each of those only differs in subject line, recipient, and
 * which PDF gets attached, not in how the email itself looks.
 */
class DocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $documentType,
        public string $referenceNumber,
        public string $recipientName,
        public string $companyName,
        public string $pdfContent,
        public string $pdfFilename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->companyName} — {$this->documentType} {$this->referenceNumber}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.document');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
