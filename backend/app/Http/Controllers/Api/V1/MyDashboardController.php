<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\GameMatch;
use App\Models\Player;
use App\Services\Ranking\BuildCategoryRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MyDashboardController extends Controller
{
    use ApiResponse;

    //Calendar
    public function calendar(Request $request): JsonResponse
    {
        $player = $request->user()->player;

        if (!$player) {
            return $this->successResponse([]);
        }

        $matches = $this->resolvePlayerMatches($player);

        $calendar = $matches
            ->filter(fn ($match) => $match->scheduled_date !== null)
            ->groupBy(fn ($match) => $match->scheduled_date->format('Y-m-d'))
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'matches' => $items->values(),
                ];
            })
            ->values();

        return $this->successResponse($calendar);
    }

    //Rankings
    public function rankings(Request $request): JsonResponse
    {
        $player = $request->user()->player;

        if (!$player) {
            return $this->successResponse([]);
        }

        $categories = Category::query()
            ->whereHas('entries', function ($query) use ($player) {
                $query->where(function ($subQuery) use ($player) {
                    $subQuery->where('player_id', $player->id)
                        ->orWhereHas('team.players', function ($teamQuery) use ($player) {
                            $teamQuery->where('players.id', $player->id);
                        });
                });
            })
            ->with('championship')
            ->get();

        $data = [];

        foreach ($categories as $category) {
            $ranking = app(BuildCategoryRankingService::class)->build($category);

            $myRow = collect($ranking)->first(function ($row) use ($player) {
                if (!is_array($row) || !isset($row['entry']) || !$row['entry'] instanceof CategoryEntry) {
                    return false;
                }

                $entry = $row['entry'];

                if ($entry->entry_type === 'player') {
                    return (int) $entry->player_id === (int) $player->id;
                }

                if ($entry->entry_type === 'team' && $entry->team) {
                    return $entry->team->players->contains('id', $player->id);
                }

                return false;
            });

            if (!is_array($myRow)) {
                continue;
            }

            $data[] = [
                'championship' => [
                    'id' => $category->championship->id,
                    'name' => $category->championship->name,
                ],
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
                'entry_type' => $myRow['entry']->entry_type,
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
        }

        return $this->successResponse($data);
    }

    //Helpers
    private function resolvePlayerMatches(Player $player): Collection
    {
        return GameMatch::query()
            ->with([
                'round.category.championship',
                'venue',
                'homeEntry.player.user',
                'awayEntry.player.user',
                'homeEntry.team.players.user',
                'awayEntry.team.players.user',
            ])
            ->where(function ($query) use ($player) {
                //Singles
                $query->whereHas('homeEntry.player', function ($subQuery) use ($player) {
                    $subQuery->where('players.id', $player->id);
                });

                $query->orWhereHas('awayEntry.player', function ($subQuery) use ($player) {
                    $subQuery->where('players.id', $player->id);
                });

                //Doubles
                $query->orWhereHas('homeEntry.team.players', function ($subQuery) use ($player) {
                    $subQuery->where('players.id', $player->id);
                });

                $query->orWhereHas('awayEntry.team.players', function ($subQuery) use ($player) {
                    $subQuery->where('players.id', $player->id);
                });
            })
            ->orderBy('scheduled_date')
            ->get()
            ->unique('id')
            ->values();
    }
}
