<?php

namespace App\Services\Ranking;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Player;
use Illuminate\Support\Collection;

class BuildMyRankingsService
{
    public function __construct(
        private readonly BuildCategoryRankingService $buildCategoryRankingService
    ) {
    }

    public function build(Player $player): Collection
    {
        return Category::query()
            ->whereHas('entries', function ($query) use ($player) {
                $query->where(function ($subQuery) use ($player) {
                    $subQuery->where('player_id', $player->id)
                        ->orWhereHas('team.players', function ($teamQuery) use ($player) {
                            $teamQuery->where('players.id', $player->id);
                        });
                });
            })
            ->with('championship')
            ->get()
            ->map(function (Category $category) use ($player): ?array {
                $myRow = $this->buildCategoryRankingService
                    ->build($category)
                    ->first(fn ($row) => $this->rowBelongsToPlayer($row, $player));

                if (!is_array($myRow)) {
                    return null;
                }

                /** @var CategoryEntry $entry */
                $entry = $myRow['entry'];

                return [
                    'championship' => [
                        'id' => $category->championship->id,
                        'name' => $category->championship->name,
                    ],
                    'category' => [
                        'id' => $category->id,
                        'name' => $category->name,
                    ],
                    'entry_type' => $entry->entry_type,
                    'entry_name' => $myRow['name'],
                    'position' => $myRow['position'],
                    'played' => $myRow['played'],
                    'wins' => $myRow['wins'],
                    'losses' => $myRow['losses'],
                    'points' => $myRow['points'],
                    'games_for' => $myRow['games_for'],
                    'games_against' => $myRow['games_against'],
                    'games_diff' => $myRow['games_diff'],
                ];
            })
            ->filter()
            ->values();
    }

    private function rowBelongsToPlayer(mixed $row, Player $player): bool
    {
        if (!is_array($row) || !isset($row['entry']) || !$row['entry'] instanceof CategoryEntry) {
            return false;
        }

        $entry = $row['entry'];

        if ($entry->entry_type === 'player') {
            return (int) $entry->player_id === (int) $player->id;
        }

        return $entry->entry_type === 'team'
            && $entry->team
            && $entry->team->players->contains('id', $player->id);
    }
}
