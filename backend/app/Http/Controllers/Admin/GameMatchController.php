<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGameMatchRequest;
use App\Models\Category;
use App\Models\GameMatch;
use App\Services\MatchResultService;
use Carbon\Carbon;
use InvalidArgumentException;

class GameMatchController extends Controller
{
    public function update(
        UpdateGameMatchRequest $request,
        Category $category,
        GameMatch $match,
        MatchResultService $matchResultService
    ) {
        $match->loadMissing('round');

        if ($match->round->category_id !== $category->id) {
            abort(404);
        }

        $validated = $request->validated();

        $scheduledAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['scheduled_date'].' '.$validated['scheduled_time']
        );

        $homeScore = $validated['home_score'] !== null ? (int) $validated['home_score'] : null;
        $awayScore = $validated['away_score'] !== null ? (int) $validated['away_score'] : null;
        $status = $validated['status'];

        try {
            $matchResultService->updateFromAdmin(
                $match,
                $category->id,
                $scheduledAt,
                (int) $validated['venue_id'],
                $status,
                $homeScore,
                $awayScore,
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Partido actualizado correctamente.');
    }
}
