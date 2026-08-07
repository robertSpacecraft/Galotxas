<?php

namespace App\Services;

use App\Mail\ContactRequestReceived;
use App\Models\ContactRequest;
use Illuminate\Support\Facades\Mail;

class ContactRequestNotificationService
{
    public function isReady(): bool
    {
        if (! config('contact.notification.enabled')) {
            return false;
        }

        $recipient = trim((string) config('contact.notification.to'));
        $from = trim((string) config('contact.notification.from'));
        $replyToMode = (string) config('contact.notification.reply_to_mode');

        return $this->isSafeEmail($recipient)
            && $this->isSafeEmail($from)
            && in_array($replyToMode, ['requester', 'none'], true);
    }

    public function send(ContactRequest $contactRequest): void
    {
        $mailer = trim((string) config('contact.notification.mailer'));
        $factory = $mailer === '' ? Mail::mailer() : Mail::mailer($mailer);

        $factory->to((string) config('contact.notification.to'))->send(
            new ContactRequestReceived($contactRequest)
        );
    }

    private function isSafeEmail(string $email): bool
    {
        return $email !== ''
            && ! str_contains($email, "\r")
            && ! str_contains($email, "\n")
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
