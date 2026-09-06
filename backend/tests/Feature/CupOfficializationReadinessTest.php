<?php

namespace Tests\Feature;

use App\Models\CategoryEntry;
use App\Models\CategoryOfficialResult;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Services\EvaluateCupOfficializationReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOfficialCupFixture;
use Tests\TestCase;

class CupOfficializationReadinessTest extends TestCase
{
    use CreatesOfficialCupFixture;
    use RefreshDatabase;

    public function test_accepts_exactly_four_more_than_four_and_an_absent_third_place(): void
    {
        $exact = $this->createReadySinglesCup(4, false);
        $exactReadiness = $this->readiness($exact['category']->id);
        $this->assertTrue($exactReadiness->isReady(), implode(', ', $exactReadiness->reasonCodes()));
        $this->assertCount(4, $exactReadiness->source->seed);
        $this->assertCount(3, $exactReadiness->source->matches);

        $more = $this->createReadySinglesCup(5);
        $moreReadiness = $this->readiness($more['category']->id);
        $this->assertTrue($moreReadiness->isReady(), implode(', ', $moreReadiness->reasonCodes()));
        $this->assertCount(4, $moreReadiness->source->seed);
    }

    public function test_rejects_insufficient_incoherent_or_missing_participant_sources(): void
    {
        $insufficient = $this->createReadySinglesCup();
        $insufficient['entries']->last()->update(['status' => 'rejected']);
        $this->assertNotReadyWith($insufficient['category']->id, 'insufficient_entries');

        $badType = $this->createReadySinglesCup();
        $badType['entries']->first()->update([
            'entry_type' => 'team',
            'player_id' => null,
        ]);
        $this->assertNotReadyWith($badType['category']->id, 'incoherent_entry_type');

        $missing = $this->createReadySinglesCup();
        $missing['entries']->first()->update(['player_id' => null]);
        $this->assertNotReadyWith($missing['category']->id, 'missing_entry_source');

        $doubles = $this->createReadyDoublesCup();
        DB::table('team_members')
            ->where('team_id', $doubles['teams']->first()->id)
            ->limit(1)
            ->delete();
        $this->assertNotReadyWith($doubles['category']->id, 'invalid_team_composition');

        $missingTeam = $this->createReadyDoublesCup();
        $missingTeam['entries']->first()->update(['team_id' => null]);
        $this->assertNotReadyWith($missingTeam['category']->id, 'missing_entry_source');
    }

    public function test_rejects_missing_duplicate_or_ambiguous_cup_structure(): void
    {
        $missingSemi = $this->createReadySinglesCup();
        $missingSemi['semifinalRound']->delete();
        $this->assertNotReadyWith($missingSemi['category']->id, 'missing_semifinal_round');

        $duplicateSemi = $this->createReadySinglesCup();
        Round::factory()->create([
            'category_id' => $duplicateSemi['category']->id,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'semifinal',
        ]);
        $this->assertNotReadyWith($duplicateSemi['category']->id, 'duplicate_semifinal_round');

        $missingFinal = $this->createReadySinglesCup();
        $missingFinal['finalRound']->delete();
        $this->assertNotReadyWith($missingFinal['category']->id, 'missing_final_round');

        $duplicateFinal = $this->createReadySinglesCup();
        Round::factory()->create([
            'category_id' => $duplicateFinal['category']->id,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'final',
        ]);
        $this->assertNotReadyWith($duplicateFinal['category']->id, 'duplicate_final_round');

        $duplicateThird = $this->createReadySinglesCup();
        Round::factory()->create([
            'category_id' => $duplicateThird['category']->id,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'third_place',
        ]);
        $this->assertNotReadyWith($duplicateThird['category']->id, 'duplicate_third_place_round');

        $ambiguous = $this->createReadySinglesCup();
        Round::factory()->create([
            'category_id' => $ambiguous['category']->id,
            'type' => 'cup',
            'phase' => null,
            'stage' => 'semifinal',
        ]);
        $this->assertNotReadyWith($ambiguous['category']->id, 'ambiguous_cup_round');
    }

    public function test_rejects_invalid_match_cardinality_and_semifinal_participants(): void
    {
        $zeroSemis = $this->createReadySinglesCup();
        $zeroSemis['semifinalMatches']->each->delete();
        $this->assertNotReadyWith($zeroSemis['category']->id, 'invalid_semifinal_match_count');

        $oneSemi = $this->createReadySinglesCup();
        $oneSemi['semifinalMatches']->last()->delete();
        $this->assertNotReadyWith($oneSemi['category']->id, 'invalid_semifinal_match_count');

        $threeSemis = $this->createReadySinglesCup();
        GameMatch::factory()->create([
            'round_id' => $threeSemis['semifinalRound']->id,
            'home_entry_id' => $threeSemis['seed'][0]->id,
            'away_entry_id' => $threeSemis['seed'][1]->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 1,
            'winner_entry_id' => $threeSemis['seed'][0]->id,
        ]);
        $this->assertNotReadyWith($threeSemis['category']->id, 'invalid_semifinal_match_count');

        $zeroFinals = $this->createReadySinglesCup();
        $zeroFinals['finalMatch']->delete();
        $this->assertNotReadyWith($zeroFinals['category']->id, 'invalid_final_match_count');

        $twoFinals = $this->createReadySinglesCup();
        GameMatch::factory()->create([
            'round_id' => $twoFinals['finalRound']->id,
            'home_entry_id' => $twoFinals['seed'][0]->id,
            'away_entry_id' => $twoFinals['seed'][1]->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 1,
            'winner_entry_id' => $twoFinals['seed'][0]->id,
        ]);
        $this->assertNotReadyWith($twoFinals['category']->id, 'invalid_final_match_count');

        $duplicate = $this->createReadySinglesCup();
        $second = $duplicate['semifinalMatches']->last();
        $second->update([
            'home_entry_id' => $duplicate['semifinalMatches']->first()->home_entry_id,
            'winner_entry_id' => $duplicate['semifinalMatches']->first()->home_entry_id,
        ]);
        $this->assertNotReadyWith($duplicate['category']->id, 'duplicate_semifinal_participant');

        $self = $this->createReadySinglesCup();
        $match = $self['semifinalMatches']->first();
        $match->update([
            'away_entry_id' => $match->home_entry_id,
            'winner_entry_id' => $match->home_entry_id,
        ]);
        $this->assertNotReadyWith($self['category']->id, 'self_pairing');

        $foreign = $this->createReadySinglesCup();
        $foreignEntry = CategoryEntry::factory()->playerEntry()->create(['status' => 'approved']);
        $foreign['semifinalMatches']->first()->update([
            'home_entry_id' => $foreignEntry->id,
            'winner_entry_id' => $foreignEntry->id,
        ]);
        $this->assertNotReadyWith($foreign['category']->id, 'foreign_entry');
    }

    public function test_revalidates_every_decisive_match_status_score_and_winner(): void
    {
        foreach (['semifinal', 'final'] as $stage) {
            foreach (['scheduled', 'submitted', 'under_review', 'postponed', 'cancelled'] as $status) {
                $fixture = $this->createReadySinglesCup();
                $this->decisiveMatchForStage($fixture, $stage)->update(['status' => $status]);
                $this->assertNotReadyWith($fixture['category']->id, 'match_not_validated');
            }
        }

        $cases = [
            [['home_score' => null], 'missing_score'],
            [['home_score' => 9, 'away_score' => 2], 'invalid_score'],
            [['home_score' => 10, 'away_score' => 10], 'tied_match'],
            [['winner_entry_id' => null], 'missing_winner'],
        ];
        foreach (['semifinal', 'final'] as $stage) {
            foreach ($cases as [$attributes, $code]) {
                $fixture = $this->createReadySinglesCup();
                $this->decisiveMatchForStage($fixture, $stage)->update($attributes);
                $this->assertNotReadyWith($fixture['category']->id, $code);
            }
        }

        foreach (['semifinal', 'final'] as $stage) {
            $wrongWinner = $this->createReadySinglesCup();
            $match = $this->decisiveMatchForStage($wrongWinner, $stage);
            $match->update(['winner_entry_id' => $match->away_entry_id]);
            $this->assertNotReadyWith($wrongWinner['category']->id, 'inconsistent_winner');

            $winnerOutsideSides = $this->createReadySinglesCup();
            $outside = $winnerOutsideSides['entries']->first(
                fn (CategoryEntry $entry): bool => ! in_array($entry->id, [
                    $this->decisiveMatchForStage($winnerOutsideSides, $stage)->home_entry_id,
                    $this->decisiveMatchForStage($winnerOutsideSides, $stage)->away_entry_id,
                ], true)
            );
            $this->decisiveMatchForStage($winnerOutsideSides, $stage)->update([
                'winner_entry_id' => $outside->id,
            ]);
            $this->assertNotReadyWith($winnerOutsideSides['category']->id, 'inconsistent_winner');
        }

        $wrongFinalist = $this->createReadySinglesCup();
        $wrongFinalist['finalMatch']->update([
            'away_entry_id' => $wrongFinalist['seed'][2]->id,
        ]);
        $this->assertNotReadyWith($wrongFinalist['category']->id, 'inconsistent_finalists');

        $duplicateFinalist = $this->createReadySinglesCup();
        $duplicateFinalist['finalMatch']->update([
            'away_entry_id' => $duplicateFinalist['finalMatch']->home_entry_id,
            'winner_entry_id' => $duplicateFinalist['finalMatch']->home_entry_id,
        ]);
        $this->assertNotReadyWith($duplicateFinalist['category']->id, 'inconsistent_finalists');
    }

    public function test_enforces_exact_seed_orientation_but_not_semifinal_creation_order(): void
    {
        $inverted = $this->createReadySinglesCup();
        $match = $inverted['semifinalMatches']->first();
        $match->update([
            'home_entry_id' => $match->away_entry_id,
            'away_entry_id' => $match->home_entry_id,
            'winner_entry_id' => $match->home_entry_id,
        ]);
        $this->assertNotReadyWith($inverted['category']->id, 'invalid_cup_seed');

        $reordered = $this->createReadySinglesCup();
        $first = $reordered['semifinalMatches']->first();
        $second = $reordered['semifinalMatches']->last();
        $payloads = [$second->getAttributes(), $first->getAttributes()];
        $first->delete();
        $second->delete();
        foreach ($payloads as $payload) {
            unset($payload['id'], $payload['created_at'], $payload['updated_at']);
            GameMatch::query()->create($payload);
        }
        $readiness = $this->readiness($reordered['category']->id);
        $this->assertTrue($readiness->isReady(), implode(', ', $readiness->reasonCodes()));

        $reversedFinal = $this->createReadySinglesCup();
        $final = $reversedFinal['finalMatch'];
        $final->update([
            'home_entry_id' => $final->away_entry_id,
            'away_entry_id' => $final->home_entry_id,
            'home_score' => $final->away_score,
            'away_score' => $final->home_score,
            'winner_entry_id' => $final->home_entry_id,
        ]);
        $readiness = $this->readiness($reversedFinal['category']->id);
        $this->assertTrue($readiness->isReady(), implode(', ', $readiness->reasonCodes()));
    }

    public function test_rejects_a_stale_bracket_after_the_locked_ranking_changes(): void
    {
        $fixture = $this->createReadySinglesCup();
        $third = $fixture['seed'][2]->id;
        $fourth = $fixture['seed'][3]->id;
        $match = $fixture['matches']->first(function (GameMatch $match) use ($third, $fourth): bool {
            return collect([$match->home_entry_id, $match->away_entry_id])->sort()->values()->all()
                === collect([$third, $fourth])->sort()->values()->all();
        });
        $match->update([
            'home_score' => $match->home_entry_id === $fourth ? 10 : 2,
            'away_score' => $match->away_entry_id === $fourth ? 10 : 2,
            'winner_entry_id' => $fourth,
        ]);

        $this->assertNotReadyWith($fixture['category']->id, 'invalid_cup_seed');
    }

    public function test_technical_ties_block_only_when_they_touch_the_top_four(): void
    {
        $topFour = $this->createReadySinglesCup();
        foreach ($topFour['matches'] as $match) {
            $match->update(['status' => 'scheduled']);
        }
        $this->assertNotReadyWith($topFour['category']->id, 'unsupported_cup_seed_tie');

        $crossing = $this->createReadySinglesCup();
        $this->addApprovedPlayerEntry($crossing['category']->id);
        $this->assertNotReadyWith($crossing['category']->id, 'unsupported_cup_seed_tie');

        $outside = $this->createReadySinglesCup();
        $fourthId = $outside['seed'][3]->id;
        $loss = $outside['matches']->first(
            fn (GameMatch $match): bool => in_array($fourthId, [
                $match->home_entry_id,
                $match->away_entry_id,
            ], true)
        );
        $loss->update([
            'home_score' => $loss->winner_entry_id === $loss->home_entry_id ? 10 : 8,
            'away_score' => $loss->winner_entry_id === $loss->away_entry_id ? 10 : 8,
        ]);
        $this->addApprovedPlayerEntry($outside['category']->id);
        $this->addApprovedPlayerEntry($outside['category']->id);
        $readiness = $this->readiness($outside['category']->id);
        $this->assertTrue($readiness->isReady(), implode(', ', $readiness->reasonCodes()));
    }

    public function test_rejects_corrupt_validated_league_contributors(): void
    {
        $cases = [
            ['home_score' => 11, 'away_score' => 2],
            ['home_score' => 10, 'away_score' => 10],
            ['winner_entry_id' => null],
        ];

        foreach ($cases as $attributes) {
            $fixture = $this->createReadySinglesCup();
            $fixture['matches']->first()->update($attributes);
            $this->assertNotReadyWith($fixture['category']->id, 'source_integrity_error');
        }

        $wrongWinner = $this->createReadySinglesCup();
        $match = $wrongWinner['matches']->first();
        $match->update([
            'winner_entry_id' => $match->winner_entry_id === $match->home_entry_id
                ? $match->away_entry_id
                : $match->home_entry_id,
        ]);
        $this->assertNotReadyWith($wrongWinner['category']->id, 'source_integrity_error');
    }

    public function test_league_lifecycle_does_not_replace_the_live_seed_and_current_cup_blocks(): void
    {
        $withLeague = $this->createReadySinglesCup();
        CategoryOfficialResult::factory()->league()->create([
            'category_id' => $withLeague['category']->id,
        ]);
        $this->assertTrue($this->readiness($withLeague['category']->id)->isReady());

        $withReopenedLeague = $this->createReadySinglesCup();
        CategoryOfficialResult::factory()->league()->reopened()->create([
            'category_id' => $withReopenedLeague['category']->id,
        ]);
        $this->assertTrue($this->readiness($withReopenedLeague['category']->id)->isReady());

        $withCup = $this->createReadySinglesCup();
        CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $withCup['category']->id,
        ]);
        $this->assertNotReadyWith($withCup['category']->id, 'cup_already_official');
    }

    public function test_third_place_content_never_blocks_or_enters_the_source(): void
    {
        foreach (['scheduled', 'submitted', 'validated', 'postponed', 'cancelled'] as $status) {
            $fixture = $this->createReadySinglesCup();
            $attributes = ['status' => $status];
            if (in_array($status, ['submitted', 'validated'], true)) {
                $attributes += [
                    'home_score' => 10,
                    'away_score' => 7,
                    'winner_entry_id' => $fixture['thirdPlaceMatch']->home_entry_id,
                ];
            }
            $fixture['thirdPlaceMatch']->update($attributes);
            $readiness = $this->readiness($fixture['category']->id);
            $this->assertTrue($readiness->isReady(), implode(', ', $readiness->reasonCodes()));
            $this->assertNotContains(
                $fixture['thirdPlaceMatch']->id,
                collect($readiness->source->matches)->pluck('source_game_match_id')->all(),
            );
        }
    }

    private function readiness(int $categoryId)
    {
        return app(EvaluateCupOfficializationReadinessService::class)->evaluate($categoryId);
    }

    /** @param array<string, mixed> $fixture */
    private function decisiveMatchForStage(array $fixture, string $stage): GameMatch
    {
        return $stage === 'semifinal'
            ? $fixture['semifinalMatches']->first()
            : $fixture['finalMatch'];
    }

    private function assertNotReadyWith(int $categoryId, string $code): void
    {
        $readiness = $this->readiness($categoryId);

        $this->assertFalse($readiness->isReady());
        $this->assertContains($code, $readiness->reasonCodes());
        $this->assertStringNotContainsString(
            'email',
            json_encode($readiness->safeIssues(), JSON_THROW_ON_ERROR),
        );
    }

    private function addApprovedPlayerEntry(int $categoryId): CategoryEntry
    {
        return CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $categoryId,
            'player_id' => Player::factory()->create()->id,
            'status' => 'approved',
        ]);
    }
}
