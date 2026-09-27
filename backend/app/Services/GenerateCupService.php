<?php

namespace App\Services;

use App\Enums\GameMatchStatus;
use App\Enums\OfficialResultMutationImpact;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Round;
use App\Services\Ranking\BuildCategoryRankingService;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerateCupService
{
    public function __construct(
        private readonly BuildCategoryRankingService $rankingService,
        private readonly OfficialResultMutationGuard $mutationGuard,
        private readonly OfficialResultLockService $locks,
        private readonly ChampionshipCalendarWindow $window,
        private readonly CupSchedulePolicy $schedule,
        private readonly VenueOccupancyService $occupancy,
        private readonly MatchScheduleTimePolicy $timePolicy,
    ) {}

    public function generateSemifinals(Category $category): void
    {
        $this->mutate($category, function (Category $category, Championship $championship, array $structure): void {
            $this->locks->lockEntriesAndTeams([$category->id]);

            $ranking = $this->rankingService->build($category);

            if ($ranking->count() < 4) {
                throw new RuntimeException('No hay suficientes participantes para generar la copa. Se necesitan al menos 4.');
            }

            $top4 = $ranking->take(4)->values();

            $this->schedule->finalHour($category);
            $friday = $this->window->weekends($championship, 0)['cup'][0];
            $this->assertReplaceable($structure['rounds']->where('type', 'cup'), $structure['matches']);
            $venues = $this->occupancy->lockAllVenues();
            $this->deleteCupRounds($structure['rounds']);
            $slots = $this->availableSlots($this->schedule->slots($friday, [17, 18, 19, 20], $venues), 2, 'semifinales');

            $semiRound = Round::create([
                'category_id' => $category->id,
                'name' => 'Semifinales',
                'order' => 100,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'semifinal',
            ]);

            // 1º vs 4º
            GameMatch::create([
                'round_id' => $semiRound->id,
                'venue_id' => $slots[0]['venue_id'],
                'home_entry_id' => $top4[0]['entry_id'],
                'away_entry_id' => $top4[3]['entry_id'],
                'scheduled_date' => $slots[0]['scheduled_at'],
                'status' => 'scheduled',
            ]);

            // 2º vs 3º
            GameMatch::create([
                'round_id' => $semiRound->id,
                'venue_id' => $slots[1]['venue_id'],
                'home_entry_id' => $top4[1]['entry_id'],
                'away_entry_id' => $top4[2]['entry_id'],
                'scheduled_date' => $slots[1]['scheduled_at'],
                'status' => 'scheduled',
            ]);
        });
    }

    public function deleteCup(Category $category): void
    {
        $this->mutate($category, function (Category $category, Championship $championship, array $structure): void {
            $this->assertReplaceable($structure['rounds']->where('type', 'cup'), $structure['matches']);
            $this->occupancy->lockAllVenues();
            $this->deleteCupRounds($structure['rounds']);
        });
    }

    public function generateFinals(Category $category): void
    {
        $this->mutate($category, function (Category $category, Championship $championship, array $structure): void {

            $semiRound = $structure['rounds']
                ->where('type', 'cup')
                ->where('phase', 'cup')
                ->where('stage', 'semifinal')
                ->first();

            if (! $semiRound) {
                throw new RuntimeException('No existen semifinales.');
            }

            $matches = $structure['matches']
                ->where('round_id', $semiRound->id)
                ->sortBy('id')
                ->values();

            if ($matches->count() !== 2) {
                throw new RuntimeException('Las semifinales no están correctamente definidas.');
            }

            $validated = $matches->filter(function ($match) {
                return $match->status === GameMatchStatus::VALIDATED
                    && ! is_null($match->home_score)
                    && ! is_null($match->away_score);
            });

            if ($validated->count() !== 2) {
                throw new RuntimeException('Las semifinales deben estar validadas antes de generar la final.');
            }

            $winners = [];
            $losers = [];

            foreach ($validated as $match) {
                if ($match->home_score === $match->away_score) {
                    throw new RuntimeException('Las semifinales no pueden terminar en empate.');
                }

                if ($match->home_score > $match->away_score) {
                    $winners[] = $match->home_entry_id;
                    $losers[] = $match->away_entry_id;
                } else {
                    $winners[] = $match->away_entry_id;
                    $losers[] = $match->home_entry_id;
                }
            }

            // eliminar finales existentes si las hubiera
            $existingFinals = $structure['rounds']
                ->where('type', 'cup')
                ->where('phase', 'cup')
                ->whereIn('stage', ['final', 'third_place'])
                ->values();

            $this->locks->lockEntriesAndTeams([$category->id]);
            $hour = $this->schedule->finalHour($category);
            $friday = $this->window->weekends($championship, 0)['cup'][1];
            $this->assertReplaceable($existingFinals, $structure['matches']);
            $venues = $this->occupancy->lockAllVenues();
            $courts = $this->schedule->finalCourts($category, $championship->type);
            if (count($courts) === 1 && $venues->where('court_number', $courts[0])->isEmpty()) {
                throw new RuntimeException("Falta la pista {$courts[0]} obligatoria para la Final. Configura su número de pista.");
            }
            // Replacement is transactional: old fixtures do not block their own
            // allocation, and any failure restores both old rounds and matches.
            $this->deleteCupRounds($existingFinals);
            $finalSlot = $this->availableSlots($this->schedule->slots($friday, [$hour], $venues, $courts), 1, 'Final (pistas '.implode(', ', $courts).')')[0];
            $thirdSlot = $this->availableSlots($this->schedule->slots($friday, [17], $venues), 1, '3º y 4º puesto')[0];

            // FINAL
            $finalRound = Round::create([
                'category_id' => $category->id,
                'name' => 'Final',
                'order' => 200,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'final',
            ]);

            GameMatch::create([
                'round_id' => $finalRound->id,
                'venue_id' => $finalSlot['venue_id'],
                'home_entry_id' => $winners[0],
                'away_entry_id' => $winners[1],
                'scheduled_date' => $finalSlot['scheduled_at'],
                'status' => 'scheduled',
            ]);

            // 3º y 4º
            $thirdRound = Round::create([
                'category_id' => $category->id,
                'name' => '3º y 4º',
                'order' => 201,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'third_place',
            ]);

            GameMatch::create([
                'round_id' => $thirdRound->id,
                'venue_id' => $thirdSlot['venue_id'],
                'home_entry_id' => $losers[0],
                'away_entry_id' => $losers[1],
                'scheduled_date' => $thirdSlot['scheduled_at'],
                'status' => 'scheduled',
            ]);
        });
    }

    private function mutate(Category $category, Closure $mutation): void
    {
        // As in League generation, read committed makes occupancy authoritative
        // after waiting on Venues, without acquiring reverse GameMatch locks.
        if (DB::transactionLevel() === 0) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }
        $this->occupancy->withConflictTranslation(function () use ($category, $mutation): void {
            DB::transaction(function () use ($category, $mutation): void {
                $championship = Championship::query()->lockForUpdate()->findOrFail($category->championship_id);
                $locked = $this->mutationGuard->lockAndGuard($category, OfficialResultMutationImpact::CUP_DECISIVE)->category;
                if (CategoryOfficialResult::query()->where('category_id', $locked->id)->cup()
                    ->orderBy('id')->lockForUpdate()->get(['id'])->isNotEmpty()) {
                    throw new RuntimeException('No se puede regenerar o eliminar la Copa: existe historial de resultados oficiales de Copa.');
                }
                $structure = $this->locks->lockRoundsAndMatches([$locked->id]);
                $mutation($locked, $championship, $structure);
            });
        });
    }

    private function assertReplaceable(Collection $rounds, Collection $matches): void
    {
        $matches = $matches->whereIn('round_id', $rounds->modelKeys());
        foreach ($matches as $match) {
            if ($match->status !== GameMatchStatus::SCHEDULED
                || $match->home_score !== null || $match->away_score !== null
                || $match->winner_entry_id !== null || $match->submitted_by !== null || $match->validated_by !== null) {
                throw new RuntimeException('No se puede reemplazar o eliminar la Copa: hay resultados o cambios de estado con historia que debe conservarse.');
            }
        }
        foreach (['match_result_reports', 'match_reschedule_requests'] as $table) {
            if (DB::table($table)->whereIn('game_match_id', $matches->modelKeys())
                ->orderBy('id')->lockForUpdate()->get(['id'])->isNotEmpty()) {
                throw new RuntimeException('No se puede reemplazar o eliminar la Copa: hay informes de resultado o solicitudes de reprogramación.');
            }
        }
    }

    private function availableSlots(array $candidates, int $required, string $stage): array
    {
        $slots = array_slice($this->occupancy->availableSlots($candidates), 0, $required);
        if (count($slots) < $required) {
            throw new RuntimeException("No hay pistas permitidas libres para {$stage} en el viernes reservado de Copa. Revisa la ocupación y los números de pista.");
        }
        foreach ($slots as $slot) {
            $this->timePolicy->assertCanonicalStart($slot['scheduled_at']);
        }

        return $slots;
    }

    /**
     * @param  Collection<int, Round>  $rounds
     */
    private function deleteCupRounds(Collection $rounds): void
    {
        foreach ($rounds->where('type', 'cup')->sortBy('id') as $round) {
            $round->matches()->delete();
            $round->delete();
        }
    }
}
