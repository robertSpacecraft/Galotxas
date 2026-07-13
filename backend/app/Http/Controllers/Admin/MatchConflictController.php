<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameMatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveMatchConflictRequest;
use App\Models\GameMatch;
use App\Services\MatchResultService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class MatchConflictController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const RELATIONS = [
        'homeEntry.player.user',
        'homeEntry.team',
        'awayEntry.player.user',
        'awayEntry.team',
        'venue',
        'round.category.championship',
        'homeResultReport.player.user',
        'awayResultReport.player.user',
    ];

    public function index(): View
    {
        $matches = GameMatch::query()
            ->where('status', GameMatchStatus::UNDER_REVIEW->value)
            ->with(self::RELATIONS)
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->get();

        return view('admin.match-conflicts.index', compact('matches'));
    }

    public function show(GameMatch $gameMatch, MatchResultService $matchResultService): View
    {
        abort_unless($gameMatch->status === GameMatchStatus::UNDER_REVIEW, 404);

        $gameMatch->load(self::RELATIONS);
        $targetScore = $matchResultService->getTargetScore($gameMatch);

        return view('admin.match-conflicts.show', compact('gameMatch', 'targetScore'));
    }

    public function resolve(
        ResolveMatchConflictRequest $request,
        GameMatch $gameMatch,
        MatchResultService $matchResultService
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $matchResultService->resolveConflict(
                $gameMatch,
                (int) $validated['home_score'],
                (int) $validated['away_score'],
                $request->user()
            );
        } catch (InvalidArgumentException $exception) {
            if ($gameMatch->fresh()->status !== GameMatchStatus::UNDER_REVIEW) {
                return redirect()
                    ->route('admin.match-conflicts.index')
                    ->with('error', $exception->getMessage());
            }

            return back()
                ->withErrors(['scores' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.match-conflicts.index')
            ->with('success', 'Conflicto resuelto y resultado validado correctamente.');
    }
}
