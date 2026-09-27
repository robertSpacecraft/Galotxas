<?php

namespace App\Services;

use App\Enums\CategoryAgeGroup;
use App\Enums\CategoryGender;
use App\Enums\ChampionshipType;
use App\Models\Category;

class LeagueCourtPolicy
{
    /** @return list<int> */
    public function normalCourts(Category $category, ChampionshipType $type): array
    {
        if ($this->isFirstMale($category)) {
            return $type === ChampionshipType::SINGLES ? [4, 5] : [1];
        }

        return [2, 3, 4, 5];
    }

    public function overflowPriority(Category $category): ?int
    {
        if ($category->age_group === CategoryAgeGroup::YOUTH) {
            return 0;
        }
        if ($category->age_group !== CategoryAgeGroup::OPEN || $this->isFirstMale($category)) {
            return null;
        }

        return match ((int) $category->level) {
            6 => 1,
            5 => 2,
            4 => 3,
            3 => 4,
            2 => match ($category->gender) {
                CategoryGender::FEMALE => 5,
                CategoryGender::MALE => 6,
                default => null,
            },
            1 => $category->gender === CategoryGender::FEMALE ? 7 : null,
            default => null,
        };
    }

    public function isFirstMale(Category $category): bool
    {
        return $category->age_group === CategoryAgeGroup::OPEN
            && $category->gender === CategoryGender::MALE
            && (int) $category->level === 1;
    }
}
