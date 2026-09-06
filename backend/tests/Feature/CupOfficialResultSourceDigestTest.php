<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\CupOfficializationSource;
use App\Services\EvaluateCupOfficializationReadinessService;
use App\Services\MatchScoreRulesService;
use App\Services\OfficialResultSourceDigestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOfficialCupFixture;
use Tests\TestCase;

class CupOfficialResultSourceDigestTest extends TestCase
{
    use CreatesOfficialCupFixture;
    use RefreshDatabase;

    public function test_match_score_rules_expose_the_minimal_canonical_cup_ruleset(): void
    {
        $rules = app(MatchScoreRulesService::class);

        $this->assertSame(['match_target_score' => 10], $rules->canonicalRuleset('singles'));
        $this->assertSame(['match_target_score' => 12], $rules->canonicalRuleset('doubles'));
    }

    public function test_cup_digest_is_deterministic_canonical_and_input_order_independent(): void
    {
        $fixture = $this->createReadyDoublesCup();
        $source = $this->source($fixture['category']->id);
        $reorderedSeed = array_reverse($source->seed);
        foreach ($reorderedSeed as &$entry) {
            $entry['team_members'] = array_reverse($entry['team_members']);
        }
        unset($entry);
        $reordered = $this->copySource($source, [
            'seed' => $reorderedSeed,
            'matches' => array_reverse($source->matches),
        ]);
        $service = app(OfficialResultSourceDigestService::class);
        $first = $service->cupDigest($source);

        $this->assertSame($first, $service->cupDigest($source));
        $this->assertSame($first, $service->cupDigest($reordered));
        $this->assertSame(64, strlen($first));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $first);
        $this->assertSame(
            $first,
            hash('sha256', $service->canonicalJson($service->cupPayload($source))),
        );
    }

    public function test_digest_changes_for_seed_decisive_matches_champion_and_team_composition(): void
    {
        $fixture = $this->createReadySinglesCup();
        $source = $this->source($fixture['category']->id);
        $baseline = $this->digest($source);

        $changedSeed = $source->seed;
        [$changedSeed[0], $changedSeed[1]] = [$changedSeed[1], $changedSeed[0]];
        $changedSeed[0]['position'] = 1;
        $changedSeed[1]['position'] = 2;
        $this->assertNotSame(
            $baseline,
            $this->digest($this->copySource($source, ['seed' => $changedSeed])),
        );

        $changedScore = $source->matches;
        $changedScore[0]['away_score'] = 8;
        $this->assertNotSame(
            $baseline,
            $this->digest($this->copySource($source, ['matches' => $changedScore])),
        );

        $changedChampionMatches = $source->matches;
        $finalIndex = collect($changedChampionMatches)->search(
            fn (array $match): bool => $match['stage'] === 'final'
        );
        $final = $changedChampionMatches[$finalIndex];
        $changedChampionMatches[$finalIndex]['home_score'] = 6;
        $changedChampionMatches[$finalIndex]['away_score'] = 10;
        $changedChampionMatches[$finalIndex]['winner_entry_id'] = $final['away_entry_id'];
        $changedChampion = [
            'source_entry_id' => $final['away_entry_id'],
            'source_final_match_id' => $final['source_game_match_id'],
        ];
        $this->assertNotSame(
            $baseline,
            $this->digest($this->copySource($source, [
                'matches' => $changedChampionMatches,
                'champion' => $changedChampion,
            ])),
        );

        $doubles = $this->createReadyDoublesCup();
        $doublesSource = $this->source($doubles['category']->id);
        $changedTeamSeed = $doublesSource->seed;
        $changedTeamSeed[0]['team_members'][0]['source_player_id'] = Player::factory()->create()->id;
        $this->assertNotSame(
            $this->digest($doublesSource),
            $this->digest($this->copySource($doublesSource, ['seed' => $changedTeamSeed])),
        );
    }

    public function test_digest_excludes_identity_actor_schedule_editorial_and_third_place(): void
    {
        $fixture = $this->createReadySinglesCup();
        $baseline = $this->digest($this->source($fixture['category']->id));
        $player = $fixture['players']->first();
        $player->update([
            'nickname' => 'Identidad cambiada',
            'dni' => '12345678Z',
            'notes' => 'Privado',
        ]);
        $player->user()->update([
            'name' => 'Nombre nuevo',
            'lastname' => 'Apellido nuevo',
            'email' => 'changed@example.test',
        ]);
        $fixture['category']->update([
            'name' => 'Categoría editorial',
            'description' => 'Descripción nueva',
            'status' => 'finished',
        ]);
        $fixture['category']->forceFill(['is_public' => true])->save();
        $fixture['semifinalRound']->update(['name' => 'Nombre libre', 'order' => 999]);
        $fixture['finalMatch']->update([
            'scheduled_date' => now()->addYear(),
            'venue_id' => null,
            'submitted_by' => $this->createActiveAdmin()->id,
            'validated_by' => $this->createActiveAdmin()->id,
        ]);
        $fixture['thirdPlaceMatch']->update([
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 9,
            'winner_entry_id' => $fixture['thirdPlaceMatch']->home_entry_id,
        ]);

        $after = $this->source($fixture['category']->id);
        $this->assertSame($baseline, $this->digest($after));

        $payload = json_encode(
            app(OfficialResultSourceDigestService::class)->cupPayload($after),
            JSON_THROW_ON_ERROR,
        );
        foreach ([
            'dni',
            'email',
            'identity_projection',
            'public_display_name',
            'venue',
            'scheduled_date',
            'actor',
            'officialized_at',
            'third_place',
            'points',
            'games_diff',
        ] as $excluded) {
            $this->assertStringNotContainsString($excluded, $payload);
        }
    }

    public function test_digest_changes_when_a_live_team_composition_changes(): void
    {
        $fixture = $this->createReadyDoublesCup();
        $before = $this->digest($this->source($fixture['category']->id));
        $member = DB::table('team_members')
            ->where('team_id', $fixture['teams']->first()->id)
            ->where('role_in_team', 'back')
            ->first();
        DB::table('team_members')->where('id', $member->id)->update([
            'player_id' => Player::factory()->create()->id,
        ]);

        $this->assertNotSame(
            $before,
            $this->digest($this->source($fixture['category']->id)),
        );
    }

    private function source(int $categoryId): CupOfficializationSource
    {
        $readiness = app(EvaluateCupOfficializationReadinessService::class)->evaluate($categoryId);
        $this->assertTrue($readiness->isReady(), implode(', ', $readiness->reasonCodes()));

        return $readiness->source;
    }

    private function digest(CupOfficializationSource $source): string
    {
        return app(OfficialResultSourceDigestService::class)->cupDigest($source);
    }

    /** @param array<string, mixed> $changes */
    private function copySource(
        CupOfficializationSource $source,
        array $changes,
    ): CupOfficializationSource {
        return new CupOfficializationSource(
            $changes['category'] ?? $source->category,
            $changes['championshipType'] ?? $source->championshipType,
            $changes['targetScore'] ?? $source->targetScore,
            $changes['seedEntryModels'] ?? $source->seedEntryModels,
            $changes['seed'] ?? $source->seed,
            $changes['matches'] ?? $source->matches,
            $changes['champion'] ?? $source->champion,
        );
    }
}
