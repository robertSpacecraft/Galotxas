<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGameMatchRequest;
use App\Models\Category;
use App\Models\GameMatch;
use Carbon\Carbon;

class GameMatchController extends Controller
{
    public function update(UpdateGameMatchRequest $request, Category $category, GameMatch $match)
    {
        $match->loadMissing('round');

        if ($match->round->category_id !== $category->id) {
            abort(404);
        }

        $validated = $request->validated();

        $scheduledAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['scheduled_date'] . ' ' . $validated['scheduled_time']
        );

        $match->update([
            'scheduled_date' => $scheduledAt,
            'venue_id' => $validated['venue_id'],
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Partido actualizado correctamente.');
    }
}
