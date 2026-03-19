<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryGender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Championship;
use App\Models\Player;
use App\Services\GenerateLeagueScheduleService;
use Illuminate\Support\Str;
use Throwable;

class CategoryController extends Controller
{
    public function index(Championship $championship)
    {
        $categories = $championship->categories()->orderBy('level')->get();

        return view('admin.categories.index', compact('championship', 'categories'));
    }

    public function create(Championship $championship)
    {
        $genderOptions = CategoryGender::options();
        $levelOptions = range(1, 10);

        return view('admin.categories.create', compact('championship', 'genderOptions', 'levelOptions'));
    }

    public function store(StoreCategoryRequest $request, Championship $championship)
    {
        $validated = $request->validated();

        Category::create([
            'championship_id' => $championship->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'level' => $validated['level'],
            'gender' => $validated['gender'],
        ]);

        return redirect()
            ->route('admin.championships.categories', $championship)
            ->with('success', 'Categoría creada');
    }

    public function show(Category $category)
    {
        $category->load([
            'championship.season',
            'registrations.player.user',
            'teams.players.user',
            'rounds.matches.homeEntry.player.user',
            'rounds.matches.homeEntry.team.players.user',
            'rounds.matches.awayEntry.player.user',
            'rounds.matches.awayEntry.team.players.user',
            'rounds.matches.venue',
        ]);

        $registrations = $category->registrations;

        $registeredIds = $registrations->pluck('player_id');

        $availablePlayers = Player::query()
            ->with('user')
            ->whereNotIn('id', $registeredIds)
            ->orderBy('id')
            ->get();

        $teams = $category->teams;

        $assignedPlayerIds = $teams
            ->flatMap(function ($team) {
                return $team->players->pluck('id');
            })
            ->unique()
            ->values();

        $teamSelectablePlayers = $registrations
            ->filter(fn ($registration) => $registration->status === 'approved')
            ->map(fn ($registration) => $registration->player)
            ->filter(fn ($player) => !$assignedPlayerIds->contains($player->id))
            ->values();

        $leagueRounds = $category->rounds
            ->where('type', 'league')
            ->sortBy('order')
            ->values();

        $venues = \App\Models\Venue::orderBy('id')->get();

        return view('admin.categories.show', [
            'category' => $category,
            'registrations' => $registrations,
            'availablePlayers' => $availablePlayers,
            'teams' => $teams,
            'teamSelectablePlayers' => $teamSelectablePlayers,
            'leagueRounds' => $leagueRounds,
            'venues' => $venues,
        ]);
    }

    public function generateLeague(Category $category, GenerateLeagueScheduleService $service)
    {
        try {
            $service->generate($category);

            return back()->with('success', 'Liga generada correctamente.');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function edit(Category $category)
    {
        $genderOptions = CategoryGender::options();
        $levelOptions = range(1, 10);

        return view('admin.categories.edit', compact('category', 'genderOptions', 'levelOptions'));
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $validated = $request->validated();

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'level' => $validated['level'],
            'gender' => $validated['gender'],
        ]);

        return redirect()
            ->route('admin.championships.categories', $category->championship)
            ->with('success', 'Categoría actualizada');
    }

    public function destroy(Category $category)
    {
        $championship = $category->championship;

        $category->delete();

        return redirect()
            ->route('admin.championships.categories', $championship)
            ->with('success', 'Categoría eliminada');
    }
}
