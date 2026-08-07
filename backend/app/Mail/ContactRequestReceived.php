<?php

namespace App\Mail;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ContactRequest $contactRequest
    ) {}

    public function envelope(): Envelope
    {
        $from = (string) config('contact.notification.from');
        $replyTo = config('contact.notification.reply_to_mode') === 'requester'
            ? [new Address((string) $this->contactRequest->email)]
            : [];

        return new Envelope(
            from: new Address($from, (string) config('app.name')),
            replyTo: $replyTo,
            subject: 'Nueva solicitud de contacto'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact-request-received'
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
