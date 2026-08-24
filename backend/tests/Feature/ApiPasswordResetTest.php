<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class ApiPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_MESSAGE = 'Si el correo existe, recibirás instrucciones para restablecer la contraseña.';

    public function test_forgot_password_returns_the_same_generic_response_for_existing_and_missing_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $existingResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $missingResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing-account@example.com',
        ]);

        $expectedResponse = [
            'message' => self::GENERIC_MESSAGE,
            'data' => null,
        ];

        $existingResponse->assertOk()->assertExactJson($expectedResponse);
        $missingResponse->assertOk()->assertExactJson($expectedResponse);
        $this->assertSame($existingResponse->getStatusCode(), $missingResponse->getStatusCode());
        $this->assertSame($existingResponse->json(), $missingResponse->json());

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'missing-account@example.com',
        ]);
    }

    public function test_forgot_password_builds_the_exact_frontend_url_with_a_valid_token(): void
    {
        Notification::fake();
        config()->set('app.frontend_url', 'https://staging.galotxesmonover.es');
        $user = User::factory()->create(['email' => 'player+reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $expectedUrl = 'https://staging.galotxesmonover.es/reset-password?token='.
                    $notification->token.'&email='.urlencode($user->email);

                return $notification->toMail($user)->actionUrl === $expectedUrl
                    && Password::broker()->tokenExists($user, $notification->token);
            }
        );
    }

    public function test_valid_reset_token_changes_password_and_allows_login(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk()->assertExactJson([
            'message' => 'Contraseña restablecida correctamente.',
            'data' => null,
        ]);

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'new-password-123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'another-password-123',
            'password_confirmation' => 'another-password-123',
        ])->assertUnprocessable();
    }

    public function test_expired_reset_token_returns_a_controlled_error(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->travel(61)->minutes();

        try {
            $this->postJson('/api/v1/auth/reset-password', [
                'email' => $user->email,
                'token' => $token,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertUnprocessable()
                ->assertJsonPath('data', null);
        } finally {
            $this->travelBack();
        }
    }

    public function test_forgot_password_preserves_broker_token_throttling(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson('/api/v1/auth/forgot-password', [
                'email' => $user->email,
            ])->assertOk()->assertJsonPath('message', self::GENERIC_MESSAGE);
        }

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_mail_transport_failure_is_non_enumerable_and_only_logs_sanitized_evidence(): void
    {
        config()->set('mail.default', 'password-reset-test-failure');
        config()->set('mail.mailers.password-reset-test-failure', [
            'transport' => 'password-reset-test-failure',
        ]);
        config()->set('services.resend.key', 're_secret_that_must_not_leak');
        Mail::extend('password-reset-test-failure', function (): AbstractTransport {
            return new class extends AbstractTransport
            {
                protected function doSend(SentMessage $message): void
                {
                    throw new TransportException(
                        'Provider rejected token full-reset-token-and-re_secret_that_must_not_leak.'
                    );
                }

                public function __toString(): string
                {
                    return 'password-reset-test-failure';
                }
            };
        });
        Mail::purge();
        Log::spy();
        $user = User::factory()->create();

        $existingResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);
        $missingResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing-after-failure@example.com',
        ]);

        $expectedResponse = [
            'message' => self::GENERIC_MESSAGE,
            'data' => null,
        ];

        $existingResponse->assertOk()->assertExactJson($expectedResponse);
        $missingResponse->assertOk()->assertExactJson($expectedResponse);
        $this->assertSame($existingResponse->json(), $missingResponse->json());
        $this->assertStringNotContainsString(
            're_secret_that_must_not_leak',
            $existingResponse->getContent()
        );
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'No se pudo entregar un enlace de recuperación de contraseña.',
                Mockery::on(fn (array $context): bool => $context === [
                    'failure_code' => 'TransportException',
                ])
            );
    }

    public function test_invalid_reset_token_returns_a_controlled_error(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable()
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_reset_password_requires_password_confirmation(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'any-token',
            'password' => 'new-password-123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_rejects_mismatched_confirmation(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'any-token',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
