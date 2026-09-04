<?php

namespace App\Services;

use App\Enums\GameMatchStatus;
use App\Enums\OfficialResultMutationImpact;
use App\Models\Category;
use App\Models\GameMatch;
use App\Models\Round;
use App\Services\Ranking\BuildCategoryRankingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerateCupService
{
    public function __construct(
        private readonly BuildCategoryRankingService $rankingService,
        private readonly OfficialResultMutationGuard $mutationGuard,
        private readonly OfficialResultLockService $locks,
    ) {}

    public function generateSemifinals(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $categoryLock = $this->mutationGuard->lockAndGuard(
                $category,
                OfficialResultMutationImpact::CUP_DECISIVE
            );
            $structure = $this->locks->lockRoundsAndMatches([$categoryLock->category->id]);
            $this->locks->lockEntriesAndTeams([$categoryLock->category->id]);

            $ranking = $this->rankingService->build($categoryLock->category);

            if ($ranking->count() < 4) {
                throw new RuntimeException('No hay suficientes participantes para generar la copa. Se necesitan al menos 4.');
            }

            $top4 = $ranking->take(4)->values();

            $this->deleteCupRounds($structure['rounds']);

            $semiRound = Round::create([
                'category_id' => $categoryLock->category->id,
                'name' => 'Semifinales',
                'order' => 100,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'semifinal',
            ]);

            // 1º vs 4º
            GameMatch::create([
                'round_id' => $semiRound->id,
                'venue_id' => null,
                'home_entry_id' => $top4[0]['entry_id'],
                'away_entry_id' => $top4[3]['entry_id'],
                'scheduled_date' => null,
                'status' => 'scheduled',
            ]);

            // 2º vs 3º
            GameMatch::create([
                'round_id' => $semiRound->id,
                'venue_id' => null,
                'home_entry_id' => $top4[1]['entry_id'],
                'away_entry_id' => $top4[2]['entry_id'],
                'scheduled_date' => null,
                'status' => 'scheduled',
            ]);
        });
    }

    public function deleteCup(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $categoryLock = $this->mutationGuard->lockAndGuard(
                $category,
                OfficialResultMutationImpact::CUP_DECISIVE
            );
            $structure = $this->locks->lockRoundsAndMatches([$categoryLock->category->id]);

            $this->deleteCupRounds($structure['rounds']);
        });
    }

    public function generateFinals(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $categoryLock = $this->mutationGuard->lockAndGuard(
                $category,
                OfficialResultMutationImpact::CUP_DECISIVE
            );
            $structure = $this->locks->lockRoundsAndMatches([$categoryLock->category->id]);

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

            foreach ($existingFinals as $round) {
                $round->matches()->delete();
                $round->delete();
            }

            // FINAL
            $finalRound = Round::create([
                'category_id' => $categoryLock->category->id,
                'name' => 'Final',
                'order' => 200,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'final',
            ]);

            GameMatch::create([
                'round_id' => $finalRound->id,
                'venue_id' => null,
                'home_entry_id' => $winners[0],
                'away_entry_id' => $winners[1],
                'scheduled_date' => null,
                'status' => 'scheduled',
            ]);

            // 3º y 4º
            $thirdRound = Round::create([
                'category_id' => $categoryLock->category->id,
                'name' => '3º y 4º',
                'order' => 201,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'third_place',
            ]);

            GameMatch::create([
                'round_id' => $thirdRound->id,
                'venue_id' => null,
                'home_entry_id' => $losers[0],
                'away_entry_id' => $losers[1],
                'scheduled_date' => null,
                'status' => 'scheduled',
            ]);
        });
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
