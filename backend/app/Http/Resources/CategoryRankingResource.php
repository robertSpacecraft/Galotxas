<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryRankingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entry = $this['entry'] ?? null;

        return [
            'position' => $this['position'],
            'entry_id' => $this['entry_id'],
            'name' => $this['name'],
            'played' => $this['played'],
            'wins' => $this['wins'],
            'losses' => $this['losses'],
            'points' => $this['points'],
            'games_for' => $this['games_for'],
            'games_against' => $this['games_against'],
            'games_diff' => $this['games_diff'],

            'entry' => $entry ? [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type,
                'player' => $entry->player ? [
                    'id' => $entry->player->id,
                    'nickname' => $entry->player->nickname,
                    'user' => $entry->player->user ? [
                        'id' => $entry->player->user->id,
                        'name' => $entry->player->user->name,
                        'lastname' => $entry->player->user->lastname,
                    ] : null,
                ] : null,
                'team' => $entry->team ? [
                    'id' => $entry->team->id,
                    'name' => $entry->team->name,
                    'players' => $entry->team->players->map(function ($player) {
                        return [
                            'id' => $player->id,
                            'nickname' => $player->nickname,
                            'user' => $player->user ? [
                                'id' => $player->user->id,
                                'name' => $player->user->name,
                                'lastname' => $player->user->lastname,
                            ] : null,
                        ];
                    })->values(),
                ] : null,
            ] : null,
        ];
    }
}
