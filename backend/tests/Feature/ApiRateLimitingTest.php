<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_allows_five_attempts_and_blocks_the_sixth(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'rate-limit@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'RATE-LIMIT@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_register_blocks_the_sixth_attempt_from_the_same_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/register', $this->registrationPayload($attempt))
                ->assertCreated();
        }

        $this->postJson('/api/v1/auth/register', $this->registrationPayload(6))
            ->assertTooManyRequests();
    }

    public function test_forgot_password_blocks_the_sixth_attempt(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/forgot-password', [
                'email' => 'missing@example.com',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'MISSING@example.com',
        ])->assertTooManyRequests();
    }

    public function test_reset_password_blocks_the_sixth_attempt(): void
    {
        $user = User::factory()->create();
        $payload = [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/reset-password', $payload)
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/reset-password', $payload)
            ->assertTooManyRequests();
    }

    public function test_result_endpoints_share_a_limit_and_block_the_eleventh_attempt_for_user_and_ip(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;
        $match = GameMatch::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withToken($token)
                ->postJson("/api/v1/matches/{$match->id}/submit-result", [])
                ->assertUnprocessable();
        }

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withToken($token)
                ->postJson("/api/v1/matches/{$match->id}/confirm-result", [])
                ->assertUnprocessable();
        }

        $this->withToken($token)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [])
            ->assertTooManyRequests();
    }

    public function test_non_sensitive_public_route_is_not_affected(): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->getJson('/api/v1/seasons')
                ->assertOk();
        }
    }

    private function registrationPayload(int $attempt): array
    {
        return [
            'name' => 'Rate',
            'lastname' => 'Limit',
            'email' => "rate-limit-{$attempt}@example.com",
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }
}
