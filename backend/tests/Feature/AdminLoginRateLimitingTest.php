<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_attempts_are_allowed_then_the_same_email_is_temporarily_blocked(): void
    {
        $payload = ['email' => 'admin-rate-limit@example.com', 'password' => 'wrong-password'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('admin.login.submit'), $payload)
                ->assertRedirect()
                ->assertSessionHasErrors('email');
        }

        $this->post(route('admin.login.submit'), [
            'email' => 'ADMIN-RATE-LIMIT@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests()->assertHeader('Retry-After');

        $this->travel(61)->seconds();

        $this->post(route('admin.login.submit'), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    public function test_another_admin_sharing_the_ip_can_still_log_in(): void
    {
        $admin = User::factory()->admin()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('admin.login.submit'), [
                'email' => 'other-admin@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('admin.login.submit'), [
            'email' => 'other-admin@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }
}
