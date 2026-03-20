<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGameMatchRequest;
use App\Models\Category;
use App\Models\GameMatch;
use App\Services\MatchResultService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class GameMatchController extends Controller
{
    public function update(
        UpdateGameMatchRequest $request,
        Category $category,
        GameMatch $match,
        MatchResultService $matchResultService
    ) {
        $match->loadMissing('round.category.championship');

        if ($match->round->category_id !== $category->id) {
            abort(404);
        }

        $validated = $request->validated();

        $scheduledAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['scheduled_date'] . ' ' . $validated['scheduled_time']
        );

        $homeScore = $validated['home_score'] !== null ? (int) $validated['home_score'] : null;
        $awayScore = $validated['away_score'] !== null ? (int) $validated['away_score'] : null;
        $status = $validated['status'];

        try {
            $matchResultService->validateScores($match, $homeScore, $awayScore, $status);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $updateData = [
            'scheduled_date' => $scheduledAt,
            'venue_id' => $validated['venue_id'],
            'status' => $status,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ];

        if ($status === 'submitted') {
            $updateData['submitted_by'] = Auth::id();
        }

        if ($status === 'validated') {
            $updateData['submitted_by'] = $match->submitted_by ?? Auth::id();
            $updateData['validated_by'] = Auth::id();
        }

        if (in_array($status, ['scheduled', 'postponed', 'cancelled'], true)) {
            $updateData['validated_by'] = null;
        }

        $match->update($updateData);

        return back()->with('success', 'Partido actualizado correctamente.');
    }
}
