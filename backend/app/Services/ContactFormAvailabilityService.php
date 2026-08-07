<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use Throwable;

class ContactFormAvailabilityService
{
    public function __construct(
        private readonly ContactNoticeService $notices
    ) {}

    public function isEnabled(): bool
    {
        if (! config('contact.form_enabled')) {
            return false;
        }

        $recipient = trim((string) config('contact.notification.to'));
        if (! $this->isSafeEmail($recipient)) {
            return false;
        }

        try {
            $notice = $this->notices->current();

            return ($notice['privacyUrl'] ?? null) === ContactNoticeService::PRIVACY_URL
                && Schema::hasColumns('contact_requests', [
                    'privacy_notice_id',
                    'privacy_notice_version',
                    'consent_at',
                    'notification_status',
                    'retention_until',
                ]);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{enabled: false}|array{enabled: true, notice_id: string, notice_version: string, privacy_url: string} */
    public function publicConfig(): array
    {
        if (! $this->isEnabled()) {
            return ['enabled' => false];
        }

        $notice = $this->notices->current();

        return [
            'enabled' => true,
            'notice_id' => (string) $notice['id'],
            'notice_version' => (string) $notice['version'],
            'privacy_url' => (string) $notice['privacyUrl'],
        ];
    }

    private function isSafeEmail(string $email): bool
    {
        return $email !== ''
            && ! str_contains($email, "\r")
            && ! str_contains($email, "\n")
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
