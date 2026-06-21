<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CalendarDayResource;
use App\Http\Resources\MyRankingResource;
use App\Models\GameMatch;
use App\Models\Player;
use App\Services\Ranking\BuildMyRankingsService;
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

        return $this->successResponse(
            CalendarDayResource::collection($calendar)
        );
    }

    //Rankings
    public function rankings(
        Request $request,
        BuildMyRankingsService $buildMyRankingsService
    ): JsonResponse
    {
        $player = $request->user()->player;

        if (!$player) {
            return $this->successResponse([]);
        }

        return $this->successResponse(
            MyRankingResource::collection($buildMyRankingsService->build($player))
        );
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
