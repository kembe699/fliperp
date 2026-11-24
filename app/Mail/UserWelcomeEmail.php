<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a company admin adds a user (Settings > Users): the three things
 * sign-in needs (client code, email, password), the sign-in link and the
 * steps. Sent synchronously, like ClientWelcomeEmail (no queue worker).
 */
class UserWelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $companyName,
        public string $clientCode,
        public string $email,
        public string $password,
        public string $loginUrl,
        public ?string $invitedBy = null,
        public ?string $replyToEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), "{$this->companyName} via FlipERP"),
            replyTo: $this->replyToEmail ? [new Address($this->replyToEmail, $this->invitedBy)] : [],
            subject: "Your FlipERP account for {$this->companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.user-welcome');
    }
}
