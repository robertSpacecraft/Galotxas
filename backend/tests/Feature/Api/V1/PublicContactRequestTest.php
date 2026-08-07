<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ContactNotificationStatus;
use App\Mail\ContactRequestReceived;
use App\Models\ContactRequest;
use App\Services\ContactRequestNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class PublicContactRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'contact.notification.to' => 'contact-recipient@example.test',
            'contact.notification.from' => 'no-reply@example.test',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_public_config_is_disabled_by_default_and_exposes_no_secrets(): void
    {
        config([
            'contact.form_enabled' => false,
            'contact.notification.enabled' => true,
            'contact.notification.to' => 'private@example.test',
            'contact.notification.from' => 'no-reply@example.test',
            'mail.default' => 'smtp',
        ]);

        $this->getJson('/api/v1/contact/config')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [
                    'enabled' => false,
                ],
            ])
            ->assertJsonMissing(['to' => 'private@example.test'])
            ->assertJsonMissing(['driver' => 'smtp']);
    }

    public function test_public_config_reports_enabled_without_exposing_internal_flags(): void
    {
        config([
            'contact.form_enabled' => true,
            'contact.notification.enabled' => true,
            'contact.notification.to' => 'contact-recipient@example.test',
        ]);

        $this->getJson('/api/v1/contact/config')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [
                    'enabled' => true,
                    'notice_id' => 'NOTICE-CONTACT-FORM',
                    'notice_version' => '1.0.0',
                    'privacy_url' => '/legal/privacidad',
                ],
            ]);
    }

    public function test_disabled_form_always_returns_503_without_persisting(): void
    {
        config(['contact.form_enabled' => false]);

        $this->postJson('/api/v1/contact-requests', [])
            ->assertStatus(503)
            ->assertExactJson([
                'message' => 'El formulario de contacto no está disponible.',
                'data' => null,
            ]);

        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_valid_request_is_normalized_persisted_and_returns_minimal_receipt(): void
    {
        CarbonImmutable::setTestNow('2026-08-04 12:00:00');
        config(['contact.form_enabled' => true]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->postJson('/api/v1/contact-requests', $this->validPayload([
                'name' => '  Persona Interesada  ',
                'email' => '  PERSONA@EXAMPLE.TEST  ',
                'subject' => '  Consulta general  ',
                'message' => "  Primera línea.\r\nSegunda línea.  ",
            ]))
            ->assertCreated()
            ->assertExactJson([
                'message' => 'Tu mensaje se ha recibido correctamente.',
                'data' => [
                    'received' => true,
                ],
            ]);

        $contactRequest = ContactRequest::query()->sole();

        $this->assertSame('Persona Interesada', $contactRequest->name);
        $this->assertSame('persona@example.test', $contactRequest->email);
        $this->assertSame('Consulta general', $contactRequest->subject);
        $this->assertSame("Primera línea.\nSegunda línea.", $contactRequest->message);
        $this->assertSame('2026-08-04 12:00:00', $contactRequest->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('NOTICE-CONTACT-FORM', $contactRequest->privacy_notice_id);
        $this->assertSame('1.0.0', $contactRequest->privacy_notice_version);
        $this->assertSame(ContactNotificationStatus::DISABLED, $contactRequest->notification_status);
        $this->assertSame(64, strlen($contactRequest->ip_hash));
        $this->assertNotSame('203.0.113.42', $contactRequest->ip_hash);
    }

    public function test_validation_requires_all_fields_consent_and_reasonable_limits(): void
    {
        config(['contact.form_enabled' => true]);

        $this->postJson('/api/v1/contact-requests', [
            'name' => 'A',
            'email' => 'not-an-email',
            'subject' => 'AB',
            'message' => 'Short',
            'privacy_accepted' => false,
            'website' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'subject',
                'message',
                'privacy_accepted',
            ]);

        $this->postJson('/api/v1/contact-requests', $this->validPayload([
            'name' => str_repeat('N', 121),
            'subject' => str_repeat('S', 201),
            'message' => str_repeat('M', 5001),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'subject', 'message']);

        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_payload_cannot_assign_internal_or_unknown_fields(): void
    {
        config(['contact.form_enabled' => true]);

        $this->postJson('/api/v1/contact-requests', $this->validPayload([
            'status' => 'closed',
            'ip_hash' => str_repeat('a', 64),
            'phone' => '600000000',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'ip_hash', 'payload']);

        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_filled_honeypot_returns_the_same_receipt_without_persisting_or_notifying(): void
    {
        Mail::fake();
        config([
            'contact.form_enabled' => true,
            'contact.notification.enabled' => true,
            'contact.notification.to' => 'admin@example.test',
        ]);

        $this->postJson('/api/v1/contact-requests', $this->validPayload([
            'website' => 'https://spam.example',
        ]))
            ->assertCreated()
            ->assertExactJson([
                'message' => 'Tu mensaje se ha recibido correctamente.',
                'data' => [
                    'received' => true,
                ],
            ]);

        $this->assertDatabaseCount('contact_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_rate_limit_uses_a_stable_429_after_five_requests_in_ten_minutes(): void
    {
        config(['contact.form_enabled' => true]);
        $payload = $this->validPayload([
            'email' => 'rate-limit@example.test',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.18'])
                ->postJson('/api/v1/contact-requests', $payload)
                ->assertCreated();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.18'])
            ->postJson('/api/v1/contact-requests', $payload)
            ->assertStatus(429)
            ->assertExactJson([
                'message' => 'Demasiadas solicitudes. Inténtalo de nuevo más tarde.',
                'data' => null,
            ]);

        $this->assertDatabaseCount('contact_requests', 5);
    }

    public function test_notification_is_not_sent_when_disabled(): void
    {
        Mail::fake();
        config([
            'contact.form_enabled' => true,
            'contact.notification.enabled' => false,
            'contact.notification.to' => 'admin@example.test',
        ]);

        $this->postJson('/api/v1/contact-requests', $this->validPayload([
            'email' => 'disabled-notification@example.test',
        ]))->assertCreated();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('contact_requests', 1);
        $this->assertSame(
            ContactNotificationStatus::DISABLED,
            ContactRequest::query()->sole()->notification_status
        );
    }

    public function test_missing_recipient_fails_closed_before_validation_or_persistence(): void
    {
        config([
            'contact.form_enabled' => true,
            'contact.notification.to' => '',
        ]);

        $this->postJson('/api/v1/contact-requests', $this->validPayload())
            ->assertStatus(503);
        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_missing_controlled_from_keeps_persistence_and_marks_notification_disabled(): void
    {
        Mail::fake();
        config([
            'contact.form_enabled' => true,
            'contact.notification.enabled' => true,
            'contact.notification.from' => '',
        ]);

        $this->postJson('/api/v1/contact-requests', $this->validPayload())
            ->assertCreated();

        $this->assertSame(
            ContactNotificationStatus::DISABLED,
            ContactRequest::query()->sole()->notification_status
        );
        Mail::assertNothingSent();
    }

    public function test_notification_is_sent_only_after_the_request_is_persisted(): void
    {
        Mail::fake();
        config([
            'contact.form_enabled' => true,
            'contact.notification.enabled' => true,
            'contact.notification.to' => 'admin@example.test',
            'contact.notification.from' => 'no-reply@example.test',
        ]);

        $this->postJson('/api/v1/contact-requests', $this->validPayload([
            'email' => 'notified@example.test',
        ]))->assertCreated();

        $contactRequest = ContactRequest::query()->sole();
        $this->assertSame(ContactNotificationStatus::SENT, $contactRequest->notification_status);
        $this->assertSame(1, $contactRequest->notification_attempt_count);

        Mail::assertSent(
            ContactRequestReceived::class,
            fn (ContactRequestReceived $mail): bool => $mail->hasTo('admin@example.test')
                && $mail->contactRequest->is($contactRequest)
                && $mail->contactRequest->exists
                && $mail->hasFrom('no-reply@example.test')
                && $mail->hasReplyTo('notified@example.test')
        );
    }

    public function test_notification_failure_keeps_the_request_and_returns_201_without_logging_message_body(): void
    {
        config([
            'contact.form_enabled' => true,
            'contact.notification.enabled' => true,
        ]);
        Log::spy();
        $this->mock(
            ContactRequestNotificationService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('isReady')
                    ->once()
                    ->andReturn(true);
                $mock->shouldReceive('send')
                    ->once()
                    ->andThrow(new RuntimeException('SMTP unavailable'));
            }
        );

        $body = 'Mensaje privado que no debe entrar en logs.';

        $this->postJson('/api/v1/contact-requests', $this->validPayload([
            'email' => 'mail-failure@example.test',
            'message' => $body,
        ]))->assertCreated();

        $contactRequest = ContactRequest::query()->sole();
        $this->assertSame($body, $contactRequest->message);
        $this->assertSame(ContactNotificationStatus::FAILED, $contactRequest->notification_status);
        $this->assertSame('RuntimeException', $contactRequest->notification_failure_code);

        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'No se pudo notificar una solicitud de contacto persistida.',
                Mockery::on(function (array $context) use ($contactRequest, $body): bool {
                    return $context === [
                        'contact_request_id' => $contactRequest->id,
                        'failure_code' => 'RuntimeException',
                    ] && ! in_array($body, $context, true);
                })
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
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
