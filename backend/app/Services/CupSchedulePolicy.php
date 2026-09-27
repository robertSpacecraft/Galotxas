<?php

namespace App\Services;

use App\Enums\CategoryAgeGroup;
use App\Enums\CategoryGender;
use App\Enums\ChampionshipType;
use App\Models\Category;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RuntimeException;

class CupSchedulePolicy
{
    public function finalHour(Category $category): int
    {
        if ($category->age_group === null) {
            throw new RuntimeException("Clasifica el grupo de edad de la categoría {$category->id} antes de generar la Copa.");
        }

        if ($category->age_group === CategoryAgeGroup::YOUTH) {
            return 18;
        }

        return match ($category->gender) {
            CategoryGender::MIXED => 18,
            CategoryGender::FEMALE => 19,
            CategoryGender::MALE => 20,
            default => throw new RuntimeException('Define el género de la categoría antes de generar la Copa.'),
        };
    }

    /** @return list<int> */
    public function finalCourts(Category $category, ChampionshipType $type): array
    {
        if ($category->age_group === CategoryAgeGroup::OPEN
            && $category->gender === CategoryGender::MALE
            && (int) $category->level === 1) {
            return $type === ChampionshipType::SINGLES ? [4] : [1];
        }

        return [2, 3, 4, 5, 6];
    }

    /**
     * Normal capacity at every permitted hour precedes overflow capacity.
     * Within each tier: earlier hour, then lower structural court number.
     *
     * @param  list<int>  $hours
     * @param  list<int>  $courtNumbers
     * @return list<array{venue_id: int, scheduled_at: CarbonInterface}>
     */
    public function slots(CarbonInterface $friday, array $hours, Collection $venues, array $courtNumbers = [2, 3, 4, 5, 6]): array
    {
        $slots = [];
        foreach ([false, true] as $overflow) {
            foreach ($hours as $hour) {
                foreach ($venues->whereIn('court_number', $courtNumbers)->sortBy('court_number') as $venue) {
                    if (($venue->court_number === 6) !== $overflow) {
                        continue;
                    }
                    $slots[] = [
                        'venue_id' => $venue->id,
                        'scheduled_at' => $friday->copy()->setTime($hour, 0, 0),
                    ];
                }
            }
        }

        return $slots;
    }
}
