<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserAuthenticationRevocationService
{
    public function revokeAll(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();

            DB::connection(config('session.connection'))
                ->table(config('session.table'))
                ->where('user_id', $user->getKey())
                ->delete();
        });
    }
}
