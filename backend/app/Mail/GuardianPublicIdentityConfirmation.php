<?php

namespace App\Mail;

use App\Models\PublicIdentityAuthorization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuardianPublicIdentityConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $confirmationUrl;

    public readonly string $privacyUrl;

    public function __construct(
        public readonly PublicIdentityAuthorization $authorization,
        string $plainToken
    ) {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $this->confirmationUrl = $frontendUrl
            .'/public-identity/confirm#token='.rawurlencode($plainToken);
        $this->privacyUrl = $frontendUrl.'/legal/privacidad';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirma la identidad pública en competición');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.guardian-public-identity-confirmation');
    }

    /** @return array<int, mixed> */
    public function attachments(): array
    {
        return [];
    }
}
