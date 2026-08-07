<?php

namespace Tests\Feature;

use App\Enums\ContactNotificationStatus;
use App\Enums\ContactRequestEventType;
use App\Enums\ContactRequestStatus;
use App\Mail\ContactRequestReceived;
use App\Models\ContactRequest;
use App\Models\ContactRequestEvent;
use App\Models\User;
use App\Services\ContactNoticeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ContactRequestOperationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_config_fails_closed_when_the_compiled_notice_is_unavailable(): void
    {
        config([
            'contact.form_enabled' => true,
            'contact.notification.to' => 'recipient@example.test',
        ]);
        $this->mock(ContactNoticeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('current')->once()->andThrow(new RuntimeException('stale'));
        });

        $this->getJson('/api/v1/contact/config')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => ['enabled' => false],
            ]);
    }

    public function test_stale_contact_notice_is_rejected_without_persistence(): void
    {
        $this->enableForm();

        $this->postJson('/api/v1/contact-requests', $this->payload([
            'privacy_notice_version' => '0.9.0',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('privacy_notice_version');

        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_crlf_in_mail_headers_fails_safely_and_in_requester_email_is_rejected(): void
    {
        config([
            'contact.form_enabled' => true,
            'contact.notification.to' => "recipient@example.test\r\nBcc:other@example.test",
        ]);
        $this->getJson('/api/v1/contact/config')->assertJsonPath('data.enabled', false);

        $this->enableForm();
        $this->postJson('/api/v1/contact-requests', $this->payload([
            'email' => "person@example.test\r\nBcc:other@example.test",
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        config([
            'contact.notification.enabled' => true,
            'contact.notification.to' => 'recipient@example.test',
            'contact.notification.from' => "no-reply@example.test\r\nBcc:other@example.test",
        ]);
        $this->postJson('/api/v1/contact-requests', $this->payload())
            ->assertCreated();
        $this->assertDatabaseHas('contact_requests', [
            'email' => 'persona@example.test',
            'notification_status' => ContactNotificationStatus::DISABLED->value,
        ]);
    }

    public function test_admin_closure_starts_retention_and_records_the_actor(): void
    {
        CarbonImmutable::setTestNow('2026-08-07 10:00:00');
        $admin = User::factory()->admin()->create();
        $contactRequest = ContactRequest::factory()->read()->create();

        $this->actingAs($admin)
            ->post(route('admin.contact-requests.close', $contactRequest))
            ->assertRedirect(route('admin.contact-requests.show', $contactRequest));

        $contactRequest->refresh();
        $this->assertSame(ContactRequestStatus::CLOSED, $contactRequest->status);
        $this->assertSame('2026-08-07 10:00:00', $contactRequest->closed_at->format('Y-m-d H:i:s'));
        $this->assertSame('2027-08-07 10:00:00', $contactRequest->retention_until->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('contact_request_events', [
            'contact_request_id' => $contactRequest->id,
            'type' => ContactRequestEventType::CLOSED->value,
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_manual_retry_is_bounded_audited_and_uses_controlled_headers(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $contactRequest = ContactRequest::factory()->notificationFailed()->create([
            'email' => 'requester@example.test',
        ]);
        config([
            'contact.notification.enabled' => true,
            'contact.notification.to' => 'recipient@example.test',
            'contact.notification.from' => 'no-reply@example.test',
            'contact.notification.reply_to_mode' => 'requester',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contact-requests.retry-notification', $contactRequest))
            ->assertRedirect(route('admin.contact-requests.show', $contactRequest));

        $contactRequest->refresh();
        $this->assertSame(ContactNotificationStatus::SENT, $contactRequest->notification_status);
        $this->assertSame(2, $contactRequest->notification_attempt_count);
        $this->assertDatabaseHas('contact_request_events', [
            'contact_request_id' => $contactRequest->id,
            'type' => ContactRequestEventType::NOTIFICATION_RETRIED->value,
            'actor_user_id' => $admin->id,
        ]);
        Mail::assertSent(ContactRequestReceived::class, fn ($mail): bool => $mail->hasTo('recipient@example.test')
            && $mail->hasFrom('no-reply@example.test')
            && $mail->hasReplyTo('requester@example.test')
        );

        $contactRequest->forceFill([
            'notification_status' => ContactNotificationStatus::FAILED,
            'notification_attempt_count' => 3,
        ])->save();
        $this->actingAs($admin)
            ->from(route('admin.contact-requests.show', $contactRequest))
            ->post(route('admin.contact-requests.retry-notification', $contactRequest))
            ->assertSessionHasErrors('notification');
        $this->assertSame(1, ContactRequestEvent::query()
            ->whereBelongsTo($contactRequest)
            ->where('type', ContactRequestEventType::NOTIFICATION_RETRIED->value)
            ->count());
    }

    public function test_hold_blocks_anonymization_until_release_and_purge_is_idempotent(): void
    {
        CarbonImmutable::setTestNow('2027-09-01 12:00:00');
        $admin = User::factory()->admin()->create();
        $eligible = ContactRequest::factory()->closed()->create([
            'retention_until' => now()->subDay(),
            'message' => 'Contenido personal eliminable.',
        ]);
        $held = ContactRequest::factory()->closed()->create([
            'retention_until' => now()->subDay(),
        ]);
        $open = ContactRequest::factory()->newRequest()->create([
            'retention_until' => now()->subDay(),
        ]);

        $this->actingAs($admin)->post(
            route('admin.contact-requests.retention-hold', $held),
            ['retention_hold_reason' => 'Reclamación en revisión']
        )->assertRedirect();

        $this->artisan('contact:purge-expired', ['--dry-run' => true])
            ->expectsOutput('Solicitudes elegibles: 1. Sin cambios.')
            ->assertSuccessful();
        $this->assertNotNull($eligible->fresh()->email);

        $this->artisan('contact:purge-expired')
            ->expectsOutput('Solicitudes anonimizadas: 1.')
            ->assertSuccessful();
        $eligible->refresh();
        $this->assertNull($eligible->name);
        $this->assertNull($eligible->email);
        $this->assertNull($eligible->subject);
        $this->assertNull($eligible->message);
        $this->assertNull($eligible->ip_hash);
        $this->assertNotNull($eligible->privacy_notice_version);
        $this->assertNotNull($held->fresh()->email);
        $this->assertNotNull($open->fresh()->email);

        $this->artisan('contact:purge-expired')
            ->expectsOutput('Solicitudes anonimizadas: 0.')
            ->assertSuccessful();

        $this->actingAs($admin)
            ->post(route('admin.contact-requests.release-retention-hold', $held))
            ->assertRedirect();
        $this->artisan('contact:purge-expired')
            ->expectsOutput('Solicitudes anonimizadas: 1.')
            ->assertSuccessful();
        $this->assertNull($held->fresh()->email);
    }

    public function test_abuse_hash_command_has_dry_run_and_a_thirty_day_boundary(): void
    {
        CarbonImmutable::setTestNow('2026-09-10 12:00:00');
        $expired = ContactRequest::factory()->create([
            'created_at' => now()->subDays(31),
            'ip_hash_expires_at' => now()->subDay(),
        ]);
        $current = ContactRequest::factory()->create([
            'ip_hash_expires_at' => now()->addDay(),
        ]);
        $held = ContactRequest::factory()->closed()->create([
            'ip_hash_expires_at' => now()->subDay(),
            'retention_hold' => true,
            'retention_hold_reason' => 'Incidente de seguridad',
        ]);

        $this->artisan('contact:purge-abuse-hashes', ['--dry-run' => true])
            ->expectsOutput('Hashes elegibles: 1. Sin cambios.')
            ->assertSuccessful();
        $this->assertNotNull($expired->fresh()->ip_hash);

        $this->artisan('contact:purge-abuse-hashes')
            ->expectsOutput('Hashes eliminados: 1.')
            ->assertSuccessful();
        $this->assertNull($expired->fresh()->ip_hash);
        $this->assertNotNull($current->fresh()->ip_hash);
        $this->assertNotNull($held->fresh()->ip_hash);

        $this->artisan('contact:purge-abuse-hashes')
            ->expectsOutput('Hashes eliminados: 0.')
            ->assertSuccessful();
    }

    public function test_manual_anonymization_requires_explicit_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $contactRequest = ContactRequest::factory()->closed()->create([
            'retention_until' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contact-requests.anonymize', $contactRequest))
            ->assertSessionHasErrors('confirm_anonymization');
        $this->assertNotNull($contactRequest->fresh()->email);

        $this->actingAs($admin)
            ->post(route('admin.contact-requests.anonymize', $contactRequest), [
                'confirm_anonymization' => true,
            ])
            ->assertRedirect(route('admin.contact-requests.show', $contactRequest));
        $this->assertNull($contactRequest->fresh()->email);
    }

    public function test_non_admin_cannot_use_operational_contact_actions(): void
    {
        $user = User::factory()->create();
        $contactRequest = ContactRequest::factory()->closed()->create([
            'retention_until' => now()->subDay(),
        ]);

        foreach ([
            route('admin.contact-requests.retry-notification', $contactRequest),
            route('admin.contact-requests.retention-hold', $contactRequest),
            route('admin.contact-requests.release-retention-hold', $contactRequest),
            route('admin.contact-requests.anonymize', $contactRequest),
        ] as $route) {
            $this->actingAs($user)->post($route)->assertForbidden();
        }
    }

    private function enableForm(): void
    {
        config([
            'contact.form_enabled' => true,
            'contact.notification.enabled' => false,
            'contact.notification.to' => 'recipient@example.test',
            'contact.notification.from' => 'no-reply@example.test',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Persona interesada',
            'email' => 'persona@example.test',
            'subject' => 'Consulta general',
            'message' => 'Este es un mensaje de contacto válido.',
            'privacy_accepted' => true,
            'privacy_notice_id' => 'NOTICE-CONTACT-FORM',
            'privacy_notice_version' => '1.0.0',
            'website' => '',
        ], $overrides);
    }
}
