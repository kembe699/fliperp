<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once, at client onboarding — carries the ONE-TIME-visible temp password,
 * matching the platform-admin UI's own "shown once" convention (see
 * PlatformClientController::store()). Same non-queued, synchronous send pattern
 * as CrmDirectEmail (no queue worker in this deployment).
 */
class ClientWelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $companyName,
        public string $clientCode,
        public string $adminEmail,
        public string $tempPassword,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Welcome to Nile Hive — {$this->companyName} is ready");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.client-welcome', with: [
            'companyName' => $this->companyName,
            'clientCode' => $this->clientCode,
            'adminEmail' => $this->adminEmail,
            'tempPassword' => $this->tempPassword,
            'loginUrl' => $this->loginUrl,
        ]);
    }
}
