<?php

namespace Database\Factories;

use App\Enums\ContactNotificationStatus;
use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Services\ContactNoticeService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactRequest>
 */
class ContactRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'subject' => fake()->sentence(5),
            'message' => fake()->paragraphs(2, true),
            'status' => ContactRequestStatus::NEW->value,
            'consent_at' => now(),
            'privacy_notice_id' => ContactNoticeService::ID,
            'privacy_notice_version' => '1.0.0',
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'ip_hash_expires_at' => now()->addDays(30),
            'notification_status' => ContactNotificationStatus::NOT_REQUESTED->value,
        ];
    }

    public function newRequest(): static
    {
        return $this->state(fn () => [
            'status' => ContactRequestStatus::NEW->value,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn () => [
            'status' => ContactRequestStatus::READ->value,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => ContactRequestStatus::CLOSED->value,
            'closed_at' => now(),
            'retention_until' => now()->addMonthsNoOverflow(12),
        ]);
    }

    public function legacy(): static
    {
        return $this->state(fn () => [
            'privacy_notice_id' => null,
            'privacy_notice_version' => null,
        ]);
    }

    public function notificationFailed(): static
    {
        return $this->state(fn () => [
            'notification_status' => ContactNotificationStatus::FAILED->value,
            'notification_attempt_count' => 1,
            'notification_attempted_at' => now(),
            'notification_failed_at' => now(),
            'notification_failure_code' => 'RuntimeException',
        ]);
    }
}
