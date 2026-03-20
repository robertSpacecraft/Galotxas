<?php

namespace App\Services;

use App\Models\Category;
use App\Models\GameMatch;
use App\Models\Round;
use App\Services\Ranking\BuildCategoryRankingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerateCupService
{
    public function __construct(
        private readonly BuildCategoryRankingService $rankingService
    ) {
    }

    public function generateSemifinals(Category $category): void
    {
        $ranking = $this->rankingService->build($category);

        if ($ranking->count() < 4) {
            throw new RuntimeException('No hay suficientes participantes para generar la copa. Se necesitan al menos 4.');
        }

        $top4 = $ranking->take(4)->values();

        DB::transaction(function () use ($category, $top4) {
            $this->deleteCup($category);

            $semiRound = Round::create([
                'category_id' => $category->id,
                'name' => 'Semifinales',
                'order' => 100,
                'type' => 'cup',
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
        DB::transaction(function () use ($category) {
            $cupRounds = Round::query()
                ->where('category_id', $category->id)
                ->where('type', 'cup')
                ->get();

            foreach ($cupRounds as $round) {
                $round->matches()->delete();
                $round->delete();
            }
        });
    }

    public function generateFinals(Category $category): void
    {
        DB::transaction(function () use ($category) {

            $semiRound = Round::query()
                ->where('category_id', $category->id)
                ->where('type', 'cup')
                ->where('name', 'Semifinales')
                ->first();

            if (!$semiRound) {
                throw new RuntimeException('No existen semifinales.');
            }

            $matches = $semiRound->matches;

            if ($matches->count() !== 2) {
                throw new RuntimeException('Las semifinales no están correctamente definidas.');
            }

            $validated = $matches->filter(function ($match) {
                return $match->status === 'validated'
                    && !is_null($match->home_score)
                    && !is_null($match->away_score);
            });

            if ($validated->count() !== 2) {
                throw new RuntimeException('Las semifinales deben estar validadas antes de generar la final.');
            }

            $winners = [];
            $losers = [];

            foreach ($validated as $match) {
                if ($match->home_score > $match->away_score) {
                    $winners[] = $match->home_entry_id;
                    $losers[] = $match->away_entry_id;
                } else {
                    $winners[] = $match->away_entry_id;
                    $losers[] = $match->home_entry_id;
                }
            }

            // eliminar finales existentes si las hubiera
            $existingFinals = Round::query()
                ->where('category_id', $category->id)
                ->where('type', 'cup')
                ->whereIn('name', ['Final', '3º y 4º'])
                ->get();

            foreach ($existingFinals as $round) {
                $round->matches()->delete();
                $round->delete();
            }

            // FINAL
            $finalRound = Round::create([
                'category_id' => $category->id,
                'name' => 'Final',
                'order' => 200,
                'type' => 'cup',
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
                'category_id' => $category->id,
                'name' => '3º y 4º',
                'order' => 201,
                'type' => 'cup',
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
}
