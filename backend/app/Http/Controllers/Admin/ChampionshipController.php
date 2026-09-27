<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChampionshipRegistrationStatus;
use App\Enums\ChampionshipStatus;
use App\Enums\ChampionshipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChampionshipRequest;
use App\Http\Requests\Admin\UpdateChampionshipRequest;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\Season;
use App\Services\ChampionshipMutationService;
use App\Services\GenerateLeagueScheduleService;
use App\Services\OfficialResultProtectedDeletionService;
use App\Services\Ranking\BuildChampionshipRankingService;
use DomainException;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;

class ChampionshipController extends Controller
{
    public function index()
    {
        $championships = Championship::with('season')
            ->orderByDesc('id')
            ->get();

        return view('admin.championships.index', compact('championships'));
    }

    public function create(Season $season)
    {
        return view('admin.championships.create', [
            'season' => $season,
            'championship' => new Championship(['season_id' => $season->id]),
            'seasons' => Season::orderByDesc('id')->get(),
            'typeOptions' => ChampionshipType::cases(),
            'statusOptions' => ChampionshipStatus::cases(),
            'registrationStatusOptions' => ChampionshipRegistrationStatus::cases(),
            'defaultStatus' => ChampionshipStatus::PENDING->value,
            'defaultRegistrationStatus' => ChampionshipRegistrationStatus::CLOSED->value,
        ]);
    }

    public function store(StoreChampionshipRequest $request, Season $season, ChampionshipMutationService $mutations)
    {
        $validated = $request->validated();

        $mutations->create($validated, $request->file('image'));

        return redirect()
            ->route('admin.seasons.championships', $validated['season_id'])
            ->with('success', 'Campeonato creado correctamente.');
    }

    public function show(Championship $championship, BuildChampionshipRankingService $rankingService)
    {
        $championship->load([
            'season',
            'categories',
        ]);

        $championshipRanking = $rankingService->build($championship);

        $registrationRequests = ChampionshipRegistrationRequest::query()
            ->with([
                'user',
                'player.user',
                'suggestedCategory',
            ])
            ->where('championship_id', $championship->id)
            ->latest()
            ->get();

        return view('admin.championships.show', [
            'championship' => $championship,
            'championshipRanking' => $championshipRanking,
            'registrationRequests' => $registrationRequests,
            'hasLeagueCalendar' => $championship->categories()->whereHas('rounds', fn ($query) => $query->where('type', 'league'))->exists(),
        ]);
    }

    public function generateLeague(Championship $championship, GenerateLeagueScheduleService $service)
    {
        try {
            $service->generate($championship);
        } catch (QueryException $exception) {
            report($exception);

            return back()->with('error', 'No se pudo guardar el calendario completo. Reintenta la operación; si persiste, revisa la integridad de los datos.');
        } catch (DomainException|InvalidArgumentException|RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Calendario de liga del campeonato generado correctamente.');
    }

    public function edit(Championship $championship)
    {
        $championship->load('season');

        return view('admin.championships.edit', [
            'championship' => $championship,
            'seasons' => Season::orderByDesc('id')->get(),
            'typeOptions' => ChampionshipType::cases(),
            'statusOptions' => ChampionshipStatus::cases(),
            'registrationStatusOptions' => ChampionshipRegistrationStatus::cases(),
            'defaultStatus' => ChampionshipStatus::PENDING->value,
            'defaultRegistrationStatus' => ChampionshipRegistrationStatus::CLOSED->value,
        ]);
    }

    public function update(
        UpdateChampionshipRequest $request,
        Championship $championship,
        ChampionshipMutationService $mutations,
    ) {
        $validated = $request->validated();
        $mutations->update($championship, $validated, $request->file('image'), $request->boolean('remove_image'));

        return redirect()
            ->route('admin.seasons.championships', $validated['season_id'])
            ->with('success', 'Campeonato actualizado correctamente.');
    }

    public function destroy(
        Championship $championship,
        OfficialResultProtectedDeletionService $deletions,
    ) {
        $season = $championship->season;

        $deletions->deleteChampionship($championship);

        return redirect()
            ->route('admin.seasons.championships', $season)
            ->with('success', 'Campeonato eliminado correctamente.');
    }
}
