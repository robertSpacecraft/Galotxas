<?php

namespace App\Services;

use App\Enums\OfficialResultMutationImpact;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Round;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerateLeagueScheduleService
{
    public function __construct(
        private readonly OfficialResultMutationGuard $mutationGuard,
        private readonly OfficialResultLockService $locks,
        private readonly MatchScheduleTimePolicy $timePolicy,
        private readonly VenueOccupancyService $occupancy,
        private readonly ChampionshipCalendarWindow $window,
        private readonly LeagueCourtPolicy $courts,
        private readonly LeagueWeekendAllocator $allocator,
    ) {}

    public function generate(Championship $championship): void
    {
        // Production entry owns its transaction: post-Venue-lock occupancy reads
        // must see commits made while waiting, without reverse GameMatch locks.
        if (DB::transactionLevel() === 0) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }

        DB::transaction(function () use ($championship): void {
            $championship = Championship::query()->lockForUpdate()->findOrFail($championship->id);
            $categories = Category::query()
                ->where('championship_id', $championship->id)
                ->orderBy('id')->lockForUpdate()->get();
            if ($categories->isEmpty()) {
                throw new RuntimeException('El campeonato no tiene categorías para generar la liga.');
            }
            $ids = $categories->modelKeys();
            $this->mutationGuard->lockAndGuardCategories($ids, OfficialResultMutationImpact::LEAGUE_STRUCTURE);
            if (CategoryOfficialResult::query()->whereIn('category_id', $ids)
                ->orderBy('id')->lockForUpdate()->get(['id'])->isNotEmpty()) {
                throw new RuntimeException('No se puede regenerar la liga: existe historial de resultados oficiales.');
            }
            $structure = $this->locks->lockRoundsAndMatches($ids);
            $participants = $this->locks->lockEntriesAndTeams($ids);
            $this->assertReplaceable($structure);
            $rounds = [];
            $maxRounds = 0;
            foreach ($categories as $category) {
                $entries = $participants['entries']->where('category_id', $category->id)
                    ->where('status', 'approved')->sortBy('id')->values();
                if ($entries->count() < 2 || $entries->count() > 10) {
                    throw new RuntimeException("La categoría {$category->id} debe tener entre 2 y 10 participantes aprobados para generar la liga.");
                }
                if ($category->age_group === null) {
                    throw new RuntimeException("Clasifica el grupo de edad de la categoría {$category->id} antes de generar la liga.");
                }
                $rounds[$category->id] = $this->buildRoundRobinPairings($entries);
                $maxRounds = max($maxRounds, count($rounds[$category->id]));
            }
            $weekends = $this->window->weekends($championship, $maxRounds)['league'];

            // One ordered batch includes old origin venues, even legacy/extra
            // courts, and all candidate destinations. No competition lock follows.
            $venues = $this->occupancy->lockAllVenues()
                ->filter(fn ($venue) => in_array($venue->court_number, range(1, 6), true))
                ->sortBy('court_number')->values();
            foreach ($categories as $category) {
                $normal = $this->courts->normalCourts($category, $championship->type);
                if ($this->courts->isFirstMale($category) && $venues->whereIn('court_number', $normal)->isEmpty()) {
                    throw new RuntimeException("Faltan pistas requeridas para la categoría {$category->id}: ".implode(', ', $normal).'. Configura sus números de pista.');
                }
            }

            // Only history-free league structure is removed, within this same
            // transaction. A planning or persistence failure restores it in full.
            $leagueIds = $structure['rounds']->where('type', 'league')->modelKeys();
            GameMatch::query()->whereIn('round_id', $leagueIds)->delete();
            Round::query()->whereKey($leagueIds)->delete();

            $plan = [];
            for ($week = 0; $week < $maxRounds; $week++) {
                $matches = [];
                foreach ($categories as $category) {
                    foreach ($rounds[$category->id][$week] ?? [] as $pairing) {
                        $matches[] = $pairing + ['category' => $category];
                    }
                }
                $slots = $this->occupancy->availableSlots($this->buildWeekendSlots($weekends[$week], $venues));
                try {
                    $plan[$week] = $this->allocator->allocate($matches, $slots, $championship->type);
                } catch (RuntimeException $exception) {
                    throw new RuntimeException('Jornada '.($week + 1).': '.$exception->getMessage(), previous: $exception);
                }
            }

            $this->occupancy->withConflictTranslation(function () use ($plan): void {
                foreach ($plan as $week => $matches) {
                    $persistedRounds = [];
                    foreach ($matches as $match) {
                        $categoryId = $match['category']->id;
                        $round = $persistedRounds[$categoryId] ??= Round::create([
                            'category_id' => $categoryId,
                            'name' => 'Jornada '.($week + 1),
                            'order' => $week + 1,
                            'type' => 'league',
                        ]);
                        $slot = $match['slot'];
                        $this->timePolicy->assertCanonicalStart($slot['scheduled_at']);
                        GameMatch::create([
                            'round_id' => $round->id,
                            'venue_id' => $slot['venue_id'],
                            'home_entry_id' => $match['home']->id,
                            'away_entry_id' => $match['away']->id,
                            'scheduled_date' => $slot['scheduled_at'],
                            'status' => 'scheduled',
                        ]);
                    }
                }
            });
        });
    }

    private function assertReplaceable(array $structure): void
    {
        if ($structure['rounds']->contains(fn ($round) => $round->type !== 'league'
            || ! in_array($round->phase, [null, 'league'], true)
            || ! in_array($round->stage, [null, 'matchday'], true))) {
            throw new RuntimeException('No se puede regenerar la liga: existe Copa u otra estructura dependiente. Revisa el campeonato.');
        }
        foreach ($structure['matches'] as $match) {
            if ($match->status->value !== 'scheduled'
                || $match->home_score !== null || $match->away_score !== null
                || $match->winner_entry_id !== null || $match->submitted_by !== null || $match->validated_by !== null) {
                throw new RuntimeException('No se puede regenerar la liga: hay resultados o cambios de estado con historia que debe conservarse.');
            }
        }
        $matchIds = $structure['matches']->modelKeys();
        foreach (['match_result_reports', 'match_reschedule_requests'] as $table) {
            if (DB::table($table)->whereIn('game_match_id', $matchIds)
                ->orderBy('id')->lockForUpdate()->get(['id'])->isNotEmpty()) {
                throw new RuntimeException('No se puede regenerar la liga: hay informes de resultado o solicitudes de reprogramación.');
            }
        }
    }

    private function buildWeekendSlots(Carbon $friday, Collection $venues): array
    {
        $slots = [];
        foreach ([0, 1] as $day) {
            foreach ([17, 18, 19, 20] as $hour) {
                foreach ($venues as $venue) {
                    $slots[] = [
                        'venue_id' => $venue->id,
                        'court_number' => $venue->court_number,
                        'scheduled_at' => $friday->copy()->addDays($day)->setTime($hour, 0, 0),
                    ];
                }
            }
        }

        return $slots;
    }

    /**
     * Algoritmo round robin a una vuelta.
     *
     * Devuelve array de rondas, cada ronda con emparejamientos:
     * [
     *   [
     *     ['home' => CategoryEntry, 'away' => CategoryEntry],
     *   ],
     * ]
     */
    private function buildRoundRobinPairings(Collection $entries): array
    {
        $participants = $entries->values()->all();

        if (count($participants) % 2 !== 0) {
            $participants[] = null; // bye
        }

        $numParticipants = count($participants);
        $numRounds = $numParticipants - 1;
        $matchesPerRound = $numParticipants / 2;

        $rounds = [];

        for ($round = 0; $round < $numRounds; $round++) {
            $pairings = [];

            for ($i = 0; $i < $matchesPerRound; $i++) {
                $home = $participants[$i];
                $away = $participants[$numParticipants - 1 - $i];

                if ($home !== null && $away !== null) {
                    // Alternancia simple para evitar sesgo fijo home/away
                    if ($round % 2 === 0) {
                        $pairings[] = ['home' => $home, 'away' => $away];
                    } else {
                        $pairings[] = ['home' => $away, 'away' => $home];
                    }
                }
            }

            $rounds[] = $pairings;

            // Rotación manteniendo fijo el primero
            $fixed = array_shift($participants);
            $last = array_pop($participants);
            array_unshift($participants, $last);
            array_unshift($participants, $fixed);
        }

        return $rounds;
    }
}
