<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EnablesLegacyBearerCompatibility;
use Tests\Concerns\InteractsWithUserCredentials;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    use EnablesLegacyBearerCompatibility;
    use InteractsWithUserCredentials;
    use RefreshDatabase;

    public function test_active_authenticated_user_can_access_private_api(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_inactive_user_receives_forbidden_and_every_credential_is_revoked(): void
    {
        [$user, $token, $tokenId] = $this->createUserWithToken(active: false);
        $secondToken = $user->createToken('api-token');
        $this->createDurableSession($user);
        $this->createDurableSession($user);
        [$other, $otherToken] = $this->createUserWithToken();
        $this->createDurableSession($other);

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'El usuario está inactivo.',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $secondToken->accessToken->id,
        ]);
        $this->assertCredentialCounts($user, tokens: 0, sessions: 0);
        $this->assertCredentialCounts($other, tokens: 1, sessions: 1);

        $this->assertTokenStatus($token, 401);
        $this->assertTokenStatus($secondToken->plainTextToken, 401);
        $this->assertTokenStatus($otherToken, 200);
    }

    public function test_inactive_user_cannot_access_another_private_endpoint(): void
    {
        [$user, $token] = $this->createUserWithToken(active: false);

        $this->withToken($token)
            ->getJson('/api/v1/me/championship-registrations')
            ->assertForbidden();
    }

    public function test_unauthenticated_user_still_receives_unauthorized(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_inactive_admin_cannot_access_admin_api(): void
    {
        [$admin, $token] = $this->createUserWithToken(active: false, admin: true);

        $this->withToken($token)
            ->getJson('/api/v1/admin/seasons')
            ->assertForbidden();
    }

    public function test_inactive_user_can_still_logout(): void
    {
        [$user, $token, $tokenId] = $this->createUserWithToken(active: false);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    private function createUserWithToken(
        bool $active = true,
        bool $admin = false
    ): array {
        $factory = User::factory()->state(['active' => $active]);

        if ($admin) {
            $factory = $factory->admin();
        }

        $user = $factory->create();
        $newToken = $user->createToken('api-token');

        return [$user, $newToken->plainTextToken, $newToken->accessToken->id];
    }
}
