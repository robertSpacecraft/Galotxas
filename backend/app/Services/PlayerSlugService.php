<?php

namespace App\Services;

use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Str;

class PlayerSlugService
{
    public function generate(?string $nickname, User $user): string
    {
        $fullName = trim(($user->name ?? '').' '.($user->lastname ?? ''));
        $base = $nickname ?: ($fullName !== '' ? $fullName : ($user->name ?: 'player'));
        $slug = Str::slug($base) ?: 'player';
        $candidate = $slug;
        $counter = 1;

        while (Player::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
