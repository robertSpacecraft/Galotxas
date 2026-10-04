<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserAuthenticationRevocationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EnablesLegacyBearerCompatibility;
use Tests\Concerns\InteractsWithUserCredentials;
use Tests\TestCase;

class UserAuthenticationRevocationServiceTest extends TestCase
{
    use EnablesLegacyBearerCompatibility;
    use InteractsWithUserCredentials;
    use RefreshDatabase;

    public function test_revokes_every_token_and_durable_session_of_the_user_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->createToken('api-token');
        $user->createToken('api-token');
        $user->createToken('other-device');
        $this->createDurableSession($user);
        $this->createDurableSession($user);
        $otherToken = $other->createToken('api-token');
        $otherSessionId = $this->createDurableSession($other);
        $guestSessionId = $this->createDurableSession(null);
        $userBefore = $user->fresh()->getAttributes();

        app(UserAuthenticationRevocationService::class)->revokeAll($user);

        $this->assertCredentialCounts($user, tokens: 0, sessions: 0);
        $this->assertCredentialCounts($other, tokens: 1, sessions: 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
        $this->assertDatabaseHas('sessions', ['id' => $otherSessionId]);
        $this->assertDatabaseHas('sessions', ['id' => $guestSessionId, 'user_id' => null]);
        $this->assertSame($userBefore, $user->fresh()->getAttributes());
        $this->assertTokenStatus($otherToken->plainTextToken, 200);
    }

    public function test_revoking_a_user_without_credentials_is_harmless_and_idempotent(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $other->createToken('api-token');
        $this->createDurableSession($other);
        $service = app(UserAuthenticationRevocationService::class);

        $service->revokeAll($user);
        $service->revokeAll($user);

        $this->assertCredentialCounts($user, tokens: 0, sessions: 0);
        $this->assertCredentialCounts($other, tokens: 1, sessions: 1);
    }

    public function test_database_failures_propagate_without_partial_revocation(): void
    {
        $user = User::factory()->create();
        $user->createToken('api-token');
        $this->createDurableSession($user);
        config()->set('session.table', 'missing_sessions_table');

        try {
            app(UserAuthenticationRevocationService::class)->revokeAll($user);
            $this->fail('A database failure must not be swallowed.');
        } catch (QueryException) {
            // Expected: the failure reaches the caller.
        }

        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
    }
}
