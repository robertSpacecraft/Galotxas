<?php

namespace App\Services\Ranking;

use App\Models\Category;
use App\Models\GameMatch;
use App\Services\PublicPlayerIdentityService;
use Illuminate\Support\Collection;

class BuildCategoryRankingService
{
    public function __construct(
        private readonly BuildCategoryLeagueTableService $tableService,
        private readonly PublicPlayerIdentityService $publicIdentityService
    ) {}

    public function build(Category $category, bool $publicOnly = false): Collection
    {
        $category->loadMissing([
            'championship',
            'entries.player.user',
            'entries.player.publicIdentityAuthorizations',
            'entries.team.players.user',
            'entries.team.players.publicIdentityAuthorizations',
        ]);

        $entries = $category->entries()
            ->where('status', 'approved')
            ->with([
                'player.user',
                'player.publicIdentityAuthorizations',
                'team.players.user',
                'team.players.publicIdentityAuthorizations',
            ])
            ->get()
            ->keyBy('id');

        $matches = GameMatch::query()
            ->whereHas('round', function ($query) use ($category) {
                $query->where('category_id', $category->id)
                    ->where('type', 'league');
            })
            ->where('status', 'validated')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->with([
                'homeEntry.player.user',
                'homeEntry.player.publicIdentityAuthorizations',
                'homeEntry.team.players.user',
                'homeEntry.team.players.publicIdentityAuthorizations',
                'awayEntry.player.user',
                'awayEntry.player.publicIdentityAuthorizations',
                'awayEntry.team.players.user',
                'awayEntry.team.players.publicIdentityAuthorizations',
            ])
            ->get();

        $names = [];

        foreach ($entries as $entry) {
            $names[$entry->id] = $publicOnly
                ? $this->publicIdentityService->entryDisplayName($entry)
                : $this->resolveEntryName($entry);
        }

        return $this->tableService
            ->build($entries->values(), $matches, $names)
            ->rows
            ->map(function (array $row) use ($entries, $names, $publicOnly): array {
                $name = $names[$row['entry_id']];
                $row['entry'] = $entries->get($row['entry_id']);
                $row['name'] = $name;
                $row['public_display_name'] = $publicOnly ? $name : null;

                return $row;
            });
    }

    private function resolveEntryName($entry): string
    {
        if ($entry->entry_type === 'player' && $entry->player) {
            return $entry->player->nickname
                ?: trim($entry->player->user->name.' '.$entry->player->user->lastname);
        }

        if ($entry->entry_type === 'team' && $entry->team) {
            return $entry->team->name;
        }

        return 'Participante #'.$entry->id;
    }
}
