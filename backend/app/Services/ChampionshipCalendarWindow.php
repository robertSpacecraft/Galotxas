<?php

namespace App\Services;

use App\Models\Championship;
use Carbon\Carbon;
use RuntimeException;

class ChampionshipCalendarWindow
{
    /** @return array{league: list<Carbon>, cup: list<Carbon>} */
    public function weekends(Championship $championship, int $requiredRounds): array
    {
        if (! $championship->start_date || ! $championship->end_date) {
            throw new RuntimeException('Define las fechas de inicio y fin del campeonato antes de generar el calendario.');
        }

        $friday = $championship->start_date->copy()->startOfDay();
        if (! $friday->isFriday()) {
            $friday->next(Carbon::FRIDAY);
        }
        $anchors = [];
        while ($friday->lte($championship->end_date)) {
            $anchors[] = $friday->copy();
            $friday->addWeek();
        }

        if (count($anchors) < $requiredRounds + 2) {
            throw new RuntimeException('La fecha de fin del campeonato es menor que la duración requerida. Revisa la fecha de fin del campeonato y ten en cuenta las dos semanas reservadas para la Copa.');
        }

        return ['league' => array_slice($anchors, 0, -2), 'cup' => array_slice($anchors, -2)];
    }
}
