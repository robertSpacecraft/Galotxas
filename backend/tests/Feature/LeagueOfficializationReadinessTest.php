<?php

namespace Tests\Feature;

use App\Models\CategoryEntry;
use App\Models\CategoryOfficialResult;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Models\Team;
use App\Services\EvaluateLeagueOfficializationReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOfficialLeagueFixture;
use Tests\TestCase;

class LeagueOfficializationReadinessTest extends TestCase
{
    use CreatesOfficialLeagueFixture;
    use RefreshDatabase;

    public function test_accepts_exact_three_entry_round_robin_with_bye_in_normalized_and_legacy_forms(): void
    {
        foreach ([false, true] as $legacy) {
            $fixture = $this->createReadySinglesLeague(3, $legacy);
            $readiness = $this->readiness($fixture['category']->id);

            $this->assertTrue($readiness->isReady());
            $this->assertSame([], $readiness->reasonCodes());
            $this->assertCount(3, $readiness->source->entries);
            $this->assertCount(3, $readiness->source->matches);
            $this->assertSame(['matchday'], array_values(array_unique(array_column(
                $readiness->source->matches,
                'stage',
            ))));
        }
    }

    public function test_rejects_insufficient_or_incoherent_entries_and_invalid_team_composition(): void
    {
        $insufficient = $this->createReadySinglesLeague(3);
        $insufficient['entries']->last()->update(['status' => 'rejected']);
        $this->assertNotReadyWith($insufficient['category']->id, 'insufficient_entries');

        $singles = $this->createReadySinglesLeague();
        $singles['entries']->first()->update([
            'entry_type' => 'team',
            'player_id' => null,
            'team_id' => Team::factory()->create([
                'category_id' => $singles['category']->id,
            ])->id,
        ]);
        $this->assertNotReadyWith($singles['category']->id, 'incoherent_entry_type');

        $doubles = $this->createReadyDoublesLeague();
        $doubles['entries']->first()->update([
            'entry_type' => 'player',
            'player_id' => Player::factory()->create()->id,
            'team_id' => null,
        ]);
        $this->assertNotReadyWith($doubles['category']->id, 'incoherent_entry_type');

        $wrongCategory = $this->createReadyDoublesLeague();
        $foreignTeam = Team::factory()->create();
        $wrongCategory['entries']->first()->update(['team_id' => $foreignTeam->id]);
        $this->assertNotReadyWith($wrongCategory['category']->id, 'missing_entry_source');

        $oneMember = $this->createReadyDoublesLeague();
        $team = $oneMember['teams']->first();
        DB::table('team_members')->where('team_id', $team->id)->limit(1)->delete();
        $this->assertNotReadyWith($oneMember['category']->id, 'invalid_team_composition');

        $duplicateMember = $this->createReadyDoublesLeague();
        $team = $duplicateMember['teams']->first();
        $member = DB::table('team_members')->where('team_id', $team->id)->first();
        DB::table('team_members')->where('team_id', $team->id)->delete();
        DB::table('team_members')->insert([
            [
                'team_id' => $team->id,
                'player_id' => $member->player_id,
                'role_in_team' => 'front',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'team_id' => $team->id,
                'player_id' => $member->player_id,
                'role_in_team' => 'back',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->assertNotReadyWith($duplicateMember['category']->id, 'invalid_team_composition');

        $wrongRoles = $this->createReadyDoublesLeague();
        DB::table('team_members')
            ->where('team_id', $wrongRoles['teams']->first()->id)
            ->update(['role_in_team' => 'front']);
        $this->assertNotReadyWith($wrongRoles['category']->id, 'invalid_team_composition');
    }

    public function test_rejects_ambiguous_missing_empty_or_incorrect_rounds(): void
    {
        $ambiguous = $this->createReadySinglesLeague();
        $ambiguous['rounds']->first()->update(['phase' => null, 'stage' => 'matchday']);
        $this->assertNotReadyWith($ambiguous['category']->id, 'ambiguous_round');

        $missing = $this->createReadySinglesLeague();
        foreach ($missing['rounds'] as $round) {
            $round->update(['type' => 'cup', 'phase' => 'cup', 'stage' => 'semifinal']);
        }
        $this->assertNotReadyWith($missing['category']->id, 'missing_league_round');

        $empty = $this->createReadySinglesLeague();
        $empty['matches']->first()->delete();
        $this->assertNotReadyWith($empty['category']->id, 'empty_league_round');

        $wrongCount = $this->createReadySinglesLeague();
        Round::factory()->create([
            'category_id' => $wrongCount['category']->id,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);
        $this->assertNotReadyWith($wrongCount['category']->id, 'invalid_round_count');
    }

    public function test_rejects_invalid_round_robin_pairings_and_foreign_participants(): void
    {
        $repeated = $this->createReadySinglesLeague(4);
        $roundMatches = $repeated['matches']->where('round_id', $repeated['rounds']->first()->id)->values();
        $roundMatches[1]->update(['home_entry_id' => $roundMatches[0]->home_entry_id]);
        $this->assertNotReadyWith($repeated['category']->id, 'entry_repeated_in_round');

        $missing = $this->createReadySinglesLeague();
        $missing['matches']->first()->delete();
        $this->assertNotReadyWith($missing['category']->id, 'incomplete_round_robin');

        $duplicate = $this->createReadySinglesLeague();
        $first = $duplicate['matches']->first();
        $duplicate['matches']->last()->update([
            'home_entry_id' => $first->home_entry_id,
            'away_entry_id' => $first->away_entry_id,
            'winner_entry_id' => $first->home_entry_id,
        ]);
        $this->assertNotReadyWith($duplicate['category']->id, 'duplicate_pairing');

        $extra = $this->createReadySinglesLeague();
        $first = $extra['matches']->first();
        GameMatch::factory()->create([
            'round_id' => $first->round_id,
            'home_entry_id' => $first->home_entry_id,
            'away_entry_id' => $first->away_entry_id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 1,
            'winner_entry_id' => $first->home_entry_id,
        ]);
        $this->assertNotReadyWith($extra['category']->id, 'extra_league_match');

        $foreign = $this->createReadySinglesLeague();
        $foreignEntry = CategoryEntry::factory()->playerEntry()->create(['status' => 'approved']);
        $foreign['matches']->first()->update([
            'home_entry_id' => $foreignEntry->id,
            'winner_entry_id' => $foreignEntry->id,
        ]);
        $this->assertNotReadyWith($foreign['category']->id, 'foreign_entry');

        $self = $this->createReadySinglesLeague();
        $match = $self['matches']->first();
        $match->update([
            'away_entry_id' => $match->home_entry_id,
            'winner_entry_id' => $match->home_entry_id,
        ]);
        $this->assertNotReadyWith($self['category']->id, 'self_pairing');
    }

    public function test_rejects_every_non_validated_match_lifecycle_state(): void
    {
        foreach (['scheduled', 'submitted', 'under_review', 'postponed', 'cancelled'] as $status) {
            $fixture = $this->createReadySinglesLeague();
            $fixture['matches']->first()->update(['status' => $status]);
            $this->assertNotReadyWith(
                $fixture['category']->id,
                'match_not_validated',
                "El estado {$status} debería impedir oficializar.",
            );
        }
    }

    public function test_rejects_invalid_scores_and_winners_with_specific_safe_codes(): void
    {
        $cases = [
            [['home_score' => null], 'missing_score'],
            [['home_score' => -1, 'away_score' => 10], 'invalid_score'],
            [['home_score' => 10, 'away_score' => 10], 'tied_match'],
            [['home_score' => 9, 'away_score' => 2], 'invalid_score'],
            [['home_score' => 11, 'away_score' => 2], 'invalid_score'],
            [['winner_entry_id' => null], 'missing_winner'],
        ];

        foreach ($cases as [$attributes, $code]) {
            $fixture = $this->createReadySinglesLeague();
            $fixture['matches']->first()->update($attributes);
            $this->assertNotReadyWith($fixture['category']->id, $code);
        }

        $outside = $this->createReadySinglesLeague();
        $foreignEntry = CategoryEntry::factory()->playerEntry()->create(['status' => 'approved']);
        $outside['matches']->first()->update(['winner_entry_id' => $foreignEntry->id]);
        $this->assertNotReadyWith($outside['category']->id, 'inconsistent_winner');

        $contradiction = $this->createReadySinglesLeague();
        $match = $contradiction['matches']->first();
        $match->update(['winner_entry_id' => $match->away_entry_id]);
        $this->assertNotReadyWith($contradiction['category']->id, 'inconsistent_winner');
    }

    public function test_sporting_ties_are_resolved_only_by_supported_rules(): void
    {
        $twoWay = $this->createReadySinglesLeague(4);
        $entries = $twoWay['entries']->values();
        $winners = [
            $this->pairKey($entries[0]->id, $entries[1]->id) => $entries[0]->id,
            $this->pairKey($entries[0]->id, $entries[2]->id) => $entries[0]->id,
            $this->pairKey($entries[0]->id, $entries[3]->id) => $entries[3]->id,
            $this->pairKey($entries[1]->id, $entries[2]->id) => $entries[1]->id,
            $this->pairKey($entries[1]->id, $entries[3]->id) => $entries[1]->id,
            $this->pairKey($entries[2]->id, $entries[3]->id) => $entries[2]->id,
        ];
        $this->applyWinners($twoWay['matches'], $winners);
        $twoWayReadiness = $this->readiness($twoWay['category']->id);
        $this->assertTrue($twoWayReadiness->isReady());
        $this->assertSame(
            $entries->pluck('id')->all(),
            array_column($twoWayReadiness->source->ranking, 'source_entry_id'),
        );

        $multi = $this->createReadySinglesLeague();
        $entries = $multi['entries']->values();
        $this->applyWinners($multi['matches'], [
            $this->pairKey($entries[0]->id, $entries[1]->id) => $entries[0]->id,
            $this->pairKey($entries[1]->id, $entries[2]->id) => $entries[1]->id,
            $this->pairKey($entries[0]->id, $entries[2]->id) => $entries[2]->id,
        ], [0, 5, 7]);
        $this->assertTrue($this->readiness($multi['category']->id)->isReady());

        $residual = $this->createReadySinglesLeague();
        $entries = $residual['entries']->values();
        $this->applyWinners($residual['matches'], [
            $this->pairKey($entries[0]->id, $entries[1]->id) => $entries[0]->id,
            $this->pairKey($entries[1]->id, $entries[2]->id) => $entries[1]->id,
            $this->pairKey($entries[0]->id, $entries[2]->id) => $entries[2]->id,
        ], [0, 0, 0]);
        $this->assertNotReadyWith($residual['category']->id, 'unsupported_ranking_tie');
    }

    public function test_current_league_blocks_readiness_but_current_cup_does_not(): void
    {
        $fixture = $this->createReadySinglesLeague();
        CategoryOfficialResult::factory()->league()->create([
            'category_id' => $fixture['category']->id,
        ]);
        $this->assertNotReadyWith($fixture['category']->id, 'league_already_official');

        $withCup = $this->createReadySinglesLeague();
        CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $withCup['category']->id,
        ]);
        $this->assertTrue($this->readiness($withCup['category']->id)->isReady());
    }

    private function readiness(int $categoryId)
    {
        return app(EvaluateLeagueOfficializationReadinessService::class)->evaluate($categoryId);
    }

    private function assertNotReadyWith(int $categoryId, string $code, string $message = ''): void
    {
        $readiness = $this->readiness($categoryId);

        $this->assertFalse($readiness->isReady(), $message);
        $this->assertContains($code, $readiness->reasonCodes(), $message);
    }

    private function pairKey(int $first, int $second): string
    {
        return min($first, $second).'|'.max($first, $second);
    }

    /**
     * @param  iterable<int, GameMatch>  $matches
     * @param  array<string, int>  $winners
     * @param  list<int>  $loserScores
     */
    private function applyWinners(iterable $matches, array $winners, array $loserScores = []): void
    {
        foreach ($matches as $index => $match) {
            $winnerId = $winners[$this->pairKey($match->home_entry_id, $match->away_entry_id)];
            $loserScore = $loserScores[$index] ?? 4;
            $match->update([
                'home_score' => $winnerId === $match->home_entry_id ? 10 : $loserScore,
                'away_score' => $winnerId === $match->away_entry_id ? 10 : $loserScore,
                'winner_entry_id' => $winnerId,
            ]);
        }
    }
}
