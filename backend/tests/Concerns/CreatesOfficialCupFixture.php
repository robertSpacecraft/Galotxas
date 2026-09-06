<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\GameMatch;
use App\Models\Round;
use App\Services\Ranking\BuildCategoryRankingService;

trait CreatesOfficialCupFixture
{
    use CreatesOfficialLeagueFixture;

    /** @return array<string, mixed> */
    protected function createReadySinglesCup(
        int $entryCount = 4,
        bool $withThirdPlace = true,
    ): array {
        $fixture = $this->createReadySinglesLeague($entryCount);

        return $this->addReadyCup($fixture, 10, $withThirdPlace);
    }

    /** @return array<string, mixed> */
    protected function createReadyDoublesCup(
        int $entryCount = 4,
        bool $withThirdPlace = true,
    ): array {
        $fixture = $this->createReadyDoublesLeague($entryCount);

        return $this->addReadyCup($fixture, 12, $withThirdPlace);
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function addReadyCup(
        array $fixture,
        int $targetScore,
        bool $withThirdPlace,
    ): array {
        /** @var Category $category */
        $category = $fixture['category'];
        foreach ($fixture['matches']->values() as $index => $match) {
            $loserScore = ($index % 7) + 1;
            $match->update([
                'home_score' => $match->winner_entry_id === $match->home_entry_id
                    ? $targetScore
                    : $loserScore,
                'away_score' => $match->winner_entry_id === $match->away_entry_id
                    ? $targetScore
                    : $loserScore,
            ]);
        }
        $seed = app(BuildCategoryRankingService::class)
            ->build($category)
            ->take(4)
            ->pluck('entry')
            ->values();
        $semifinalRound = Round::factory()->create([
            'category_id' => $category->id,
            'name' => 'Semifinales',
            'order' => 100,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'semifinal',
        ]);
        $semifinalMatches = collect([
            $this->decisiveMatch(
                $semifinalRound,
                $seed[0],
                $seed[3],
                $targetScore,
                4,
            ),
            $this->decisiveMatch(
                $semifinalRound,
                $seed[1],
                $seed[2],
                $targetScore,
                5,
            ),
        ]);
        $finalRound = Round::factory()->create([
            'category_id' => $category->id,
            'name' => 'Final',
            'order' => 200,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'final',
        ]);
        $finalMatch = $this->decisiveMatch(
            $finalRound,
            $seed[0],
            $seed[1],
            $targetScore,
            6,
        );
        $thirdPlaceRound = null;
        $thirdPlaceMatch = null;

        if ($withThirdPlace) {
            $thirdPlaceRound = Round::factory()->create([
                'category_id' => $category->id,
                'name' => '3º y 4º',
                'order' => 201,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'third_place',
            ]);
            $thirdPlaceMatch = GameMatch::factory()->create([
                'round_id' => $thirdPlaceRound->id,
                'home_entry_id' => $seed[3]->id,
                'away_entry_id' => $seed[2]->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
                'winner_entry_id' => null,
            ]);
        }

        return array_merge($fixture, compact(
            'seed',
            'semifinalRound',
            'semifinalMatches',
            'finalRound',
            'finalMatch',
            'thirdPlaceRound',
            'thirdPlaceMatch',
        ));
    }

    private function decisiveMatch(
        Round $round,
        CategoryEntry $home,
        CategoryEntry $away,
        int $targetScore,
        int $awayScore,
    ): GameMatch {
        return GameMatch::factory()->create([
            'round_id' => $round->id,
            'home_entry_id' => $home->id,
            'away_entry_id' => $away->id,
            'status' => 'validated',
            'home_score' => $targetScore,
            'away_score' => $awayScore,
            'winner_entry_id' => $home->id,
        ]);
    }
}
