<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithUserCredentials;
use Tests\TestCase;

class AdminUserCredentialRevocationTest extends TestCase
{
    use InteractsWithUserCredentials;
    use RefreshDatabase;

    public function test_password_change_revokes_every_target_credential_and_keeps_the_acting_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('api-token')->plainTextToken;
        $adminSessionId = $this->createDurableSession($admin);
        $target = User::factory()->create();
        $firstToken = $target->createToken('api-token')->plainTextToken;
        $secondToken = $target->createToken('api-token')->plainTextToken;
        $this->createDurableSession($target);
        $this->createDurableSession($target);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), $this->updatePayload($target, [
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'Usuario actualizado correctamente.');

        $this->assertAuthenticatedAs($admin, 'web');
        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertTrue(Hash::check('new-password-123', $target->fresh()->password));
        $this->assertCredentialCounts($target, tokens: 0, sessions: 0);
        $this->assertCredentialCounts($admin, tokens: 1, sessions: 1);
        $this->assertDatabaseHas('sessions', ['id' => $adminSessionId, 'user_id' => $admin->id]);
        $this->assertTokenStatus($firstToken, 401);
        $this->assertTokenStatus($secondToken, 401);
        $this->assertTokenStatus($adminToken, 200);
    }

    public function test_ordinary_edit_without_password_keeps_target_credentials(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $token = $target->createToken('api-token')->plainTextToken;
        $this->createDurableSession($target);
        $originalPasswordHash = $target->password;

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), $this->updatePayload($target, [
                'name' => 'Nombre editado',
                'lastname' => 'Apellido editado',
                'email' => 'editado@example.test',
                'role' => 'admin',
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertSame('Nombre editado', $target->name);
        $this->assertSame('editado@example.test', $target->email);
        $this->assertSame('admin', $target->role);
        $this->assertTrue($target->active);
        $this->assertSame($originalPasswordHash, $target->password);
        $this->assertCredentialCounts($target, tokens: 1, sessions: 1);
        $this->assertTokenStatus($token, 200);
    }

    public function test_deactivation_revokes_every_target_credential_and_reactivation_restores_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $adminSessionId = $this->createDurableSession($admin);
        $target = User::factory()->create();
        $firstToken = $target->createToken('api-token')->plainTextToken;
        $secondToken = $target->createToken('other-device')->plainTextToken;
        $this->createDurableSession($target);
        $this->createDurableSession($target);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), $this->updatePayload($target, ['active' => '0']))
            ->assertRedirect(route('admin.users.index'));

        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertFalse($target->fresh()->active);
        $this->assertCredentialCounts($target, tokens: 0, sessions: 0);
        $this->assertDatabaseHas('sessions', ['id' => $adminSessionId, 'user_id' => $admin->id]);
        $this->assertTokenStatus($firstToken, 401);
        $this->assertTokenStatus($secondToken, 401);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), $this->updatePayload($target->fresh(), ['active' => '1']))
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($target->fresh()->active);
        $this->assertCredentialCounts($target, tokens: 0, sessions: 0);
        $this->assertTokenStatus($firstToken, 401);
        $this->assertTokenStatus($secondToken, 401);
    }

    public function test_editing_an_already_inactive_user_without_password_does_not_revoke(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['active' => false]);
        $token = $target->createToken('api-token');
        $sessionId = $this->createDurableSession($target);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), $this->updatePayload($target, [
                'name' => 'Sigue inactivo',
                'active' => '0',
            ]))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('Sigue inactivo', $target->fresh()->name);
        $this->assertFalse($target->fresh()->active);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $target->id]);
    }

    public function test_admin_changing_own_password_is_logged_out_and_loses_every_credential(): void
    {
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('api-token')->plainTextToken;
        $this->createDurableSession($admin);
        $this->createDurableSession($admin);
        $other = User::factory()->create();
        $otherToken = $other->createToken('api-token')->plainTextToken;
        $this->createDurableSession($other);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), $this->updatePayload($admin, [
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors([
                'email' => 'Tu contraseña se ha actualizado. Vuelve a iniciar sesión.',
            ]);

        $this->assertGuest('web');
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertTrue(Hash::check('new-password-123', $admin->fresh()->password));
        $this->assertTrue($admin->fresh()->active);
        $this->assertCredentialCounts($admin, tokens: 0, sessions: 0);
        $this->assertCredentialCounts($other, tokens: 1, sessions: 1);
        $this->assertTokenStatus($adminToken, 401);
        $this->assertTokenStatus($otherToken, 200);
    }

    public function test_admin_deactivating_self_is_logged_out_and_loses_every_credential(): void
    {
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('api-token')->plainTextToken;
        $this->createDurableSession($admin);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), $this->updatePayload($admin, ['active' => '0']))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Tu usuario está inactivo.']);

        $this->assertGuest('web');
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertFalse($admin->fresh()->active);
        $this->assertCredentialCounts($admin, tokens: 0, sessions: 0);
        $this->assertTokenStatus($adminToken, 401);
    }

    public function test_self_password_change_does_not_revive_the_current_database_session(): void
    {
        $this->useDatabaseSessions();
        $cookieName = config('session.cookie');
        $admin = User::factory()->admin()->create();

        $login = $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $sessionId = $login->getCookie($cookieName)->getValue();
        $otherDeviceSessionId = $this->createDurableSession($admin);
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $admin->id]);

        $this->refreshAuthenticationState();
        $this->withCookie($cookieName, $sessionId)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->refreshAuthenticationState();
        $response = $this->withCookie($cookieName, $sessionId)
            ->put(route('admin.users.update', $admin), $this->updatePayload($admin, [
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]))
            ->assertRedirect(route('admin.login'));

        $replacementSessionId = $response->getCookie($cookieName)->getValue();
        $this->assertNotSame($sessionId, $replacementSessionId);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertDatabaseMissing('sessions', ['id' => $otherDeviceSessionId]);
        $this->assertDatabaseHas('sessions', ['id' => $replacementSessionId, 'user_id' => null]);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $admin->id)->count());

        $this->refreshAuthenticationState();
        $this->withCookie($cookieName, $sessionId)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('web');
        $this->assertSame(0, DB::table('sessions')->where('user_id', $admin->id)->count());
    }

    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'role' => $user->role,
            'active' => $user->active ? '1' : '0',
        ], $overrides);
    }

    private function useDatabaseSessions(): void
    {
        config()->set('session.driver', 'database');
        $this->app->forgetInstance('session.store');
        $this->refreshAuthenticationState();
    }

    private function refreshAuthenticationState(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
    }
}
