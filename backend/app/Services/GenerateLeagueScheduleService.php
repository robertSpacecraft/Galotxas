<?php

namespace App\Services;

use App\Enums\ChampionshipType;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\GameMatch;
use App\Models\Round;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerateLeagueScheduleService
{
    /**
     * Genera la fase de liga para una categoría:
     * - rounds tipo league
     * - game_matches scheduled
     * - asignación automática de fecha, hora y pista
     */
    public function generate(Category $category): void
    {
        $category->loadMissing('championship');

        $entries = CategoryEntry::query()
            ->where('category_id', $category->id)
            ->where('status', 'approved')
            ->orderBy('id')
            ->get();

        if ($entries->count() < 2) {
            throw new RuntimeException('No hay suficientes participantes aprobados para generar la liga.');
        }

        $existingLeagueRounds = Round::query()
            ->where('category_id', $category->id)
            ->where('type', 'league')
            ->exists();

        if ($existingLeagueRounds) {
            throw new RuntimeException('La categoría ya tiene jornadas de liga generadas.');
        }

        $existingMatches = GameMatch::query()
            ->whereHas('round', function ($query) use ($category) {
                $query->where('category_id', $category->id)
                    ->where('type', 'league');
            })
            ->exists();

        if ($existingMatches) {
            throw new RuntimeException('La categoría ya tiene partidos de liga generados.');
        }

        $venueIds = $this->resolveAutomaticVenueIds($category);

        if (empty($venueIds)) {
            throw new RuntimeException('No hay pistas válidas disponibles para generar el calendario.');
        }

        $pairingsByRound = $this->buildRoundRobinPairings($entries);

        DB::transaction(function () use ($category, $pairingsByRound, $venueIds) {
            $startDate = $this->resolveGenerationStartDate($category);

            foreach ($pairingsByRound as $roundIndex => $roundPairings) {
                $roundNumber = $roundIndex + 1;

                $round = Round::create([
                    'category_id' => $category->id,
                    'name' => 'Jornada ' . $roundNumber,
                    'order' => $roundNumber,
                    'type' => 'league',
                ]);

                $slots = $this->buildWeekendSlots($startDate->copy()->addWeeks($roundIndex), $venueIds);

                if (count($roundPairings) > count($slots)) {
                    throw new RuntimeException('No hay suficientes huecos automáticos para programar la jornada ' . $roundNumber . '.');
                }

                foreach ($roundPairings as $matchIndex => $pairing) {
                    $slot = $slots[$matchIndex];

                    GameMatch::create([
                        'round_id' => $round->id,
                        'venue_id' => $slot['venue_id'],
                        'home_entry_id' => $pairing['home']->id,
                        'away_entry_id' => $pairing['away']->id,
                        'scheduled_date' => $slot['scheduled_at'],
                        'status' => 'scheduled',
                    ]);
                }
            }
        });
    }

    /**
     * Devuelve IDs de Venue permitidos para la generación automática.
     */
    private function resolveAutomaticVenueIds(Category $category): array
    {
        $category->loadMissing('championship');

        $isTopDoublesCategory =
            $category->championship->type === ChampionshipType::DOUBLES
            && (int) $category->level === 1;

        if ($isTopDoublesCategory) {
            return Venue::query()
                ->where('name', '1')
                ->orWhere('name', 'Galotxa 1')
                ->orWhere('name', 'Pista 1')
                ->orWhere('id', 1)
                ->pluck('id')
                ->unique()
                ->values()
                ->all();
        }

        return Venue::query()
            ->whereIn('id', [2, 3, 4, 5])
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * Decide desde qué viernes empezar a generar.
     * Si el campeonato tiene start_date, usa el primer viernes en o después de esa fecha.
     * Si no, usa el próximo viernes desde hoy.
     */
    private function resolveGenerationStartDate(Category $category): Carbon
    {
        $referenceDate = $category->championship->start_date
            ? Carbon::parse($category->championship->start_date)
            : Carbon::now();

        $date = $referenceDate->copy()->startOfDay();

        if ((int) $date->dayOfWeek !== Carbon::FRIDAY) {
            $date = $date->next(Carbon::FRIDAY);
        }

        return $date;
    }

    /**
     * Genera huecos automáticos para una jornada:
     * viernes primero, luego sábado.
     */
    private function buildWeekendSlots(Carbon $fridayDate, array $venueIds): array
    {
        $slots = [];

        $fridayHours = ['17:00', '18:00', '19:00', '20:00'];
        $saturdayHours = ['17:30', '18:00', '19:00'];

        foreach ($fridayHours as $hour) {
            foreach ($venueIds as $venueId) {
                $slots[] = [
                    'venue_id' => $venueId,
                    'scheduled_at' => Carbon::parse($fridayDate->format('Y-m-d') . ' ' . $hour . ':00'),
                ];
            }
        }

        $saturdayDate = $fridayDate->copy()->addDay();

        foreach ($saturdayHours as $hour) {
            foreach ($venueIds as $venueId) {
                $slots[] = [
                    'venue_id' => $venueId,
                    'scheduled_at' => Carbon::parse($saturdayDate->format('Y-m-d') . ' ' . $hour . ':00'),
                ];
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
