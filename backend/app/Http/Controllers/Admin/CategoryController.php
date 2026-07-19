<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryGender;
use App\Enums\CategoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\CategoryRegistration;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\Venue;
use App\Services\GenerateCupService;
use App\Services\GenerateLeagueScheduleService;
use App\Services\Ranking\BuildCategoryRankingService;
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
        return view('admin.categories.create', [
            'championship' => $championship,
            'category' => new Category([
                'championship_id' => $championship->id,
                'status' => CategoryStatus::PENDING->value,
            ]),
            'genderOptions' => CategoryGender::cases(),
            'levelOptions' => range(1, 10),
            'statusOptions' => CategoryStatus::cases(),
            'defaultStatus' => CategoryStatus::PENDING->value,
        ]);
    }

    public function store(StoreCategoryRequest $request, Championship $championship)
    {
        $validated = $request->validated();

        Category::create([
            'championship_id' => $championship->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'level' => $validated['level'] ?? null,
            'gender' => $validated['gender'],
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('admin.championships.categories', $championship)
            ->with('success', 'Categoría creada');
    }

    public function show(Category $category, BuildCategoryRankingService $rankingService)
    {
        $category->load([
            'championship.season',
            'registrations.player.user',
            'teams.players.user',
            'entries.player.user',
            'entries.team.players.user',
            'rounds.matches.homeEntry.player.user',
            'rounds.matches.homeEntry.team.players.user',
            'rounds.matches.awayEntry.player.user',
            'rounds.matches.awayEntry.team.players.user',
            'rounds.matches.venue',
        ]);

        $registrations = $category->registrations;

        $registeredIds = $registrations->pluck('player_id');

        $championshipId = $category->championship_id;

        $assignedInChampionshipIds = CategoryRegistration::query()
            ->whereHas('category', function ($query) use ($championshipId) {
                $query->where('championship_id', $championshipId);
            })
            ->pluck('player_id');

        $availablePlayers = ChampionshipRegistrationRequest::query()
            ->with(['player.user'])
            ->where('championship_id', $championshipId)
            ->where('status', 'approved')
            ->whereNotNull('player_id')
            ->whereNotIn('player_id', $assignedInChampionshipIds)
            ->get()
            ->pluck('player')
            ->filter()
            ->unique('id')
            ->sortBy(function ($player) {
                return $player->nickname ?: trim(($player->user->name ?? '').' '.($player->user->lastname ?? ''));
            })
            ->values();

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
            ->filter(fn ($player) => ! $assignedPlayerIds->contains($player->id))
            ->values();

        $leagueRounds = $category->rounds
            ->where('type', 'league')
            ->sortBy('order')
            ->values();

        $cupRounds = $category->rounds
            ->where('type', 'cup')
            ->sortBy('order')
            ->values();

        $venues = Venue::orderBy('id')->get();

        $categoryRanking = $rankingService->build($category);

        return view('admin.categories.show', [
            'category' => $category,
            'registrations' => $registrations,
            'availablePlayers' => $availablePlayers,
            'teams' => $teams,
            'teamSelectablePlayers' => $teamSelectablePlayers,
            'leagueRounds' => $leagueRounds,
            'venues' => $venues,
            'categoryRanking' => $categoryRanking,
            'cupRounds' => $cupRounds,
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
        $category->loadMissing('championship');

        return view('admin.categories.edit', [
            'championship' => $category->championship,
            'category' => $category,
            'genderOptions' => CategoryGender::cases(),
            'levelOptions' => range(1, 10),
            'statusOptions' => CategoryStatus::cases(),
            'defaultStatus' => CategoryStatus::PENDING->value,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $validated = $request->validated();

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'level' => $validated['level'] ?? null,
            'gender' => $validated['gender'],
            'status' => $validated['status'],
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

    // Para generar la copa:
    public function generateCup(Category $category, GenerateCupService $cupService)
    {
        try {
            $cupService->generateSemifinals($category);

            return back()->with('success', 'Semifinales de copa generadas correctamente.');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function deleteCup(Category $category, GenerateCupService $cupService)
    {
        try {
            $cupService->deleteCup($category);

            return back()->with('success', 'Copa eliminada correctamente.');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function generateFinals(Category $category, GenerateCupService $cupService)
    {
        try {
            $cupService->generateFinals($category);

            return back()->with('success', 'Final y 3º/4º generados correctamente.');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
