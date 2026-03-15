<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function show(GameMatch $gameMatch)
    {
        return response()->json($gameMatch->load([
            'homeEntry.player', 'homeEntry.team',
            'awayEntry.player', 'awayEntry.team',
            'venue', 'round.category.championship.season'
        ]));
    }

    public function submitResult(Request $request, GameMatch $gameMatch)
    {
        $validated = $request->validate([
            'home_score' => 'required|integer|min:0',
            'away_score' => 'required|integer|min:0',
        ]);

        if ($gameMatch->status === 'validated') {
            return response()->json(['message' => 'Match is already validated and cannot be changed.'], 403);
        }

        $gameMatch->update([
            'home_score' => $validated['home_score'],
            'away_score' => $validated['away_score'],
            'status' => 'submitted',
            'submitted_by' => $request->user()->id,
        ]);

        return response()->json($gameMatch);
    }
}
