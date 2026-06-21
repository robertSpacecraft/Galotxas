<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActiveSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_inactive_admin_is_logged_out_and_redirected_to_login(): void
    {
        $admin = User::factory()->admin()->create(['active' => false]);

        $response = $this->actingAs($admin)
            ->withSession(['session_sentinel' => 'must-be-removed'])
            ->get(route('admin.dashboard'));

        $response
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors([
                'email' => 'Tu usuario está inactivo.',
            ])
            ->assertSessionMissing('session_sentinel');

        $this->assertGuest('web');
    }

    public function test_non_admin_user_still_receives_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_still_redirected_to_admin_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }
}
