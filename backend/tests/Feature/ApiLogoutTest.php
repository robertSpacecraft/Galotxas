<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout_and_only_current_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current-token');
        $otherToken = $user->createToken('other-token');

        $response = $this
            ->withToken($currentToken->plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response
            ->assertOk()
            ->assertExactJson([
                'message' => 'Logout correcto.',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
        $this->app['auth']->forgetGuards();

        $this
            ->withToken($currentToken->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
        $this->app['auth']->forgetGuards();

        $this
            ->withToken($otherToken->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_user_can_login_again_after_logout(): void
    {
        $user = User::factory()->create();

        $firstLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $firstToken = $firstLogin->json('data.token');

        $this->withToken($firstToken)
            ->getJson('/api/v1/me')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($firstToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($firstToken)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $secondLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $secondToken = $secondLogin->json('data.token');

        $this->assertNotSame($firstToken, $secondToken);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }
}
