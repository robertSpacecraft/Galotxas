<?php

namespace App\Services\Ranking;

use App\Models\CategoryEntry;
use Illuminate\Support\Collection;

class ResolveEntryPlayerContributionsService
{
    public function resolve(CategoryEntry $entry): Collection
    {
        if ($entry->entry_type === 'player' && $entry->player) {
            return collect([
                [
                    'player' => $entry->player,
                    'role' => 'single',
                    'weight' => 1.0,
                ],
            ]);
        }

        if ($entry->entry_type === 'team' && $entry->team) {
            return $entry->team->players->map(function ($player) {
                $role = $player->pivot?->role_in_team;

                return [
                    'player' => $player,
                    'role' => $role,
                    'weight' => $this->resolveRoleWeight($role),
                ];
            });
        }

        return collect();
    }

    private function resolveRoleWeight(?string $role): float
    {
        return match ($role) {
            'back' => 0.75,
            'front' => 0.25,
            default => 0.50,
        };
    }
}
