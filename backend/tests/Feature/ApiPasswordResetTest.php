<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'new-password-123',
        ])->assertOk();
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
