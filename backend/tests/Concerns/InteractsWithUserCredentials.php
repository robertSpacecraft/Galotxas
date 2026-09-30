<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait InteractsWithUserCredentials
{
    protected function createDurableSession(?User $user): string
    {
        $sessionId = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user?->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);

        return $sessionId;
    }

    protected function assertCredentialCounts(User $user, int $tokens, int $sessions): void
    {
        $this->assertSame($tokens, $user->tokens()->count());
        $this->assertSame(
            $sessions,
            DB::table('sessions')->where('user_id', $user->getKey())->count()
        );
    }

    protected function assertTokenStatus(string $plainTextToken, int $status): void
    {
        $this->app['auth']->forgetGuards();

        $this->withToken($plainTextToken)
            ->getJson('/api/v1/me')
            ->assertStatus($status);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
    }
}
