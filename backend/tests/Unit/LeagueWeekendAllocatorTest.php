<?php

namespace Tests\Unit;

use App\Enums\ChampionshipType;
use App\Models\Category;
use App\Services\LeagueCourtPolicy;
use App\Services\LeagueWeekendAllocator;
use PHPUnit\Framework\TestCase;

class LeagueWeekendAllocatorTest extends TestCase
{
    public function test_matching_reassigns_a_flexible_match_to_preserve_a_constrained_slot(): void
    {
        $flexible = new Category(['age_group' => 'youth', 'gender' => 'male', 'level' => 1]);
        $firstMale = new Category(['age_group' => 'open', 'gender' => 'male', 'level' => 1]);
        $slots = [['court_number' => 4], ['court_number' => 2], ['court_number' => 6]];
        $allocator = new LeagueWeekendAllocator(new LeagueCourtPolicy);
        $matches = [['category' => $flexible], ['category' => $firstMale]];
        $plan = $allocator->allocate($matches, $slots, ChampionshipType::SINGLES);
        $this->assertSame([2, 4], array_column(array_column($plan, 'slot'), 'court_number'));
        $this->assertSame($plan, $allocator->allocate($matches, $slots, ChampionshipType::SINGLES));
    }

    public function test_each_eligible_overflow_rank_beats_the_next_regardless_of_input_order(): void
    {
        $categories = [
            new Category(['age_group' => 'youth', 'gender' => 'mixed', 'level' => null]),
            new Category(['age_group' => 'open', 'gender' => 'mixed', 'level' => 6]),
            new Category(['age_group' => 'open', 'gender' => 'male', 'level' => 5]),
            new Category(['age_group' => 'open', 'gender' => 'female', 'level' => 4]),
            new Category(['age_group' => 'open', 'gender' => 'mixed', 'level' => 3]),
            new Category(['age_group' => 'open', 'gender' => 'female', 'level' => 2]),
            new Category(['age_group' => 'open', 'gender' => 'male', 'level' => 2]),
            new Category(['age_group' => 'open', 'gender' => 'female', 'level' => 1]),
        ];
        $allocator = new LeagueWeekendAllocator(new LeagueCourtPolicy);
        for ($i = 0; $i < count($categories) - 1; $i++) {
            foreach ([false, true] as $reverse) {
                $matches = [['category' => $categories[$i]], ['category' => $categories[$i + 1]]];
                if ($reverse) {
                    $matches = array_reverse($matches);
                }
                $plan = $allocator->allocate($matches, [['court_number' => 2], ['court_number' => 6]], ChampionshipType::SINGLES);
                $overflow = array_values(array_filter($plan, fn ($m) => $m['slot']['court_number'] === 6));
                $this->assertSame($categories[$i], $overflow[0]['category']);
            }
        }
    }
}
