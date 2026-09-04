<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\EvaluateLeagueOfficializationReadinessService;
use App\Services\LeagueOfficializationSource;
use App\Services\OfficialResultSourceDigestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOfficialLeagueFixture;
use Tests\TestCase;

class OfficialResultSourceDigestTest extends TestCase
{
    use CreatesOfficialLeagueFixture;
    use RefreshDatabase;

    public function test_digest_is_deterministic_canonical_and_query_order_independent(): void
    {
        $fixture = $this->createReadyDoublesLeague();
        $source = $this->source($fixture['category']->id);
        $service = app(OfficialResultSourceDigestService::class);
        $reorderedEntries = array_reverse($source->entries);
        foreach ($reorderedEntries as &$entry) {
            $entry['team_members'] = array_reverse($entry['team_members']);
        }
        unset($entry);
        $reordered = $this->copySource($source, [
            'entries' => $reorderedEntries,
            'matches' => array_reverse($source->matches),
            'ranking' => array_reverse($source->ranking),
        ]);

        $first = $service->leagueDigest($source);
        $second = $service->leagueDigest($source);
        $reorderedDigest = $service->leagueDigest($reordered);

        $this->assertSame($first, $second);
        $this->assertSame($first, $reorderedDigest);
        $this->assertSame(64, strlen($first));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $first);
        $this->assertSame(
            $first,
            hash('sha256', $service->canonicalJson($service->leaguePayload($source))),
        );
    }

    public function test_digest_changes_for_every_included_sporting_source(): void
    {
        $scoreFixture = $this->createReadySinglesLeague();
        $beforeScore = $this->digest($this->source($scoreFixture['category']->id));
        $match = $scoreFixture['matches']->first();
        $match->update([
            'away_score' => 8,
            'winner_entry_id' => $match->home_entry_id,
        ]);
        $this->assertNotSame($beforeScore, $this->digest($this->source($scoreFixture['category']->id)));

        $participantFixture = $this->createReadySinglesLeague();
        $beforeParticipant = $this->digest($this->source($participantFixture['category']->id));
        $replacement = Player::factory()->create([
            'nickname' => 'Relevo',
            'birth_date' => '1990-01-01',
        ]);
        $participantFixture['entries']->first()->update(['player_id' => $replacement->id]);
        $this->assertNotSame(
            $beforeParticipant,
            $this->digest($this->source($participantFixture['category']->id)),
        );

        $teamFixture = $this->createReadyDoublesLeague();
        $beforeTeam = $this->digest($this->source($teamFixture['category']->id));
        $team = $teamFixture['teams']->first();
        $back = DB::table('team_members')
            ->where('team_id', $team->id)
            ->where('role_in_team', 'back')
            ->first();
        DB::table('team_members')->where('id', $back->id)->update([
            'player_id' => Player::factory()->create()->id,
        ]);
        $this->assertNotSame($beforeTeam, $this->digest($this->source($teamFixture['category']->id)));

        $source = $this->source($scoreFixture['category']->id);
        $winnerChanged = $source->matches;
        $winnerChanged[0]['winner_entry_id'] = $winnerChanged[0]['away_entry_id'];
        $this->assertNotSame(
            $this->digest($source),
            $this->digest($this->copySource($source, ['matches' => $winnerChanged])),
        );

        $rankingChanged = $source->ranking;
        $rankingChanged[0]['points']++;
        $this->assertNotSame(
            $this->digest($source),
            $this->digest($this->copySource($source, ['ranking' => $rankingChanged])),
        );

        $doublesRules = $this->copySource($source, [
            'championshipType' => 'doubles',
            'targetScore' => 12,
        ]);
        $this->assertNotSame($this->digest($source), $this->digest($doublesRules));
    }

    public function test_digest_excludes_identity_editorial_schedule_actor_and_time_data(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $initial = $this->digest($this->source($fixture['category']->id));
        $player = $fixture['players']->first();
        $player->update([
            'nickname' => 'Identidad pública cambiada',
            'dni' => '12345678Z',
            'notes' => 'No debe entrar en el digest',
        ]);
        $player->user()->update([
            'name' => 'Nombre cambiado',
            'lastname' => 'Apellido cambiado',
            'email' => 'changed@example.test',
        ]);
        $fixture['category']->update([
            'name' => 'Nombre editorial nuevo',
            'description' => 'Descripción nueva',
            'status' => 'finished',
        ]);
        $fixture['category']->forceFill(['is_public' => true])->save();
        $championship = $fixture['category']->championship;
        $championship->update([
            'name' => 'Campeonato renombrado',
            'status' => 'finished',
        ]);
        $championship->forceFill(['is_public' => true])->save();
        $fixture['rounds']->first()->update([
            'name' => 'Nombre de jornada cambiado',
            'order' => 99,
        ]);
        $fixture['matches']->first()->update([
            'scheduled_date' => now()->addYear(),
            'venue_id' => null,
            'submitted_by' => $this->createActiveAdmin()->id,
            'validated_by' => $this->createActiveAdmin()->id,
        ]);

        $after = $this->digest($this->source($fixture['category']->id));

        $this->assertSame($initial, $after);
        $payload = json_encode(
            app(OfficialResultSourceDigestService::class)->leaguePayload(
                $this->source($fixture['category']->id),
            ),
            JSON_THROW_ON_ERROR,
        );
        foreach (['dni', 'email', 'birth_date', 'notes', 'identity_projection', 'public_display_name', 'venue', 'scheduled_date', 'actor', 'officialized_at'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $payload);
        }
    }

    private function source(int $categoryId): LeagueOfficializationSource
    {
        $readiness = app(EvaluateLeagueOfficializationReadinessService::class)->evaluate($categoryId);
        $this->assertTrue($readiness->isReady(), implode(', ', $readiness->reasonCodes()));

        return $readiness->source;
    }

    private function digest(LeagueOfficializationSource $source): string
    {
        return app(OfficialResultSourceDigestService::class)->leagueDigest($source);
    }

    /** @param array<string, mixed> $changes */
    private function copySource(LeagueOfficializationSource $source, array $changes): LeagueOfficializationSource
    {
        return new LeagueOfficializationSource(
            $changes['category'] ?? $source->category,
            $changes['championshipType'] ?? $source->championshipType,
            $changes['targetScore'] ?? $source->targetScore,
            $changes['entryModels'] ?? $source->entryModels,
            $changes['entries'] ?? $source->entries,
            $changes['matches'] ?? $source->matches,
            $changes['ranking'] ?? $source->ranking,
        );
    }
}
