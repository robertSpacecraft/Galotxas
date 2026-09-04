<?php

namespace Tests\Feature;

use App\Enums\OfficialIdentityProjection;
use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use App\Exceptions\InvalidOfficialResultActorException;
use App\Exceptions\InvalidReopenReasonException;
use App\Exceptions\LeagueAlreadyOfficialException;
use App\Exceptions\LeagueOfficializationNotReadyException;
use App\Exceptions\NoCurrentLeagueOfficialResultException;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Models\CategoryOfficialLeagueRow;
use App\Models\CategoryOfficialResult;
use App\Models\User;
use App\Services\MatchResultService;
use App\Services\OfficializeLeagueResultService;
use App\Services\ReopenLeagueResultService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesOfficialLeagueFixture;
use Tests\TestCase;

class LeagueOfficialResultLifecycleTest extends TestCase
{
    use CreatesOfficialLeagueFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_officializes_v1_as_a_complete_exact_and_current_aggregate(): void
    {
        CarbonImmutable::setTestNow('2026-09-04 10:20:30');
        $fixture = $this->createReadySinglesLeague();
        $fixture['players']->first()->update(['nickname' => '  Àlies   Uno  ']);
        $actor = $this->createActiveAdmin();

        $result = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $actor);

        $this->assertSame(1, $result->version);
        $this->assertSame($fixture['category']->id, $result->category_id);
        $this->assertSame(OfficialResultCompetitionPart::LEAGUE, $result->competition_part);
        $this->assertSame(OfficialResultStatus::OFFICIAL, $result->status);
        $this->assertSame(1, $result->current_slot);
        $this->assertSame('2026-09-04 10:20:30', $result->officialized_at->format('Y-m-d H:i:s'));
        $this->assertSame($actor->id, $result->officialized_by_user_id);
        $this->assertSame('Ada Administradora', $result->officialized_by_name_snapshot);
        $this->assertNull($result->reopened_at);
        $this->assertNull($result->reopened_by_user_id);
        $this->assertNull($result->reopened_by_name_snapshot);
        $this->assertNull($result->reopen_reason);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $result->source_digest);

        $this->assertCount(3, $result->leagueRows);
        $this->assertCount(3, $result->matchSnapshots);
        $this->assertSame([1, 2, 3], $result->leagueRows->pluck('position')->all());
        $this->assertSame(
            $fixture['entries']->pluck('id')->sort()->values()->all(),
            $result->leagueRows->pluck('source_entry_id')->sort()->values()->all(),
        );
        $this->assertSame(
            $fixture['matches']->pluck('id')->sort()->values()->all(),
            $result->matchSnapshots->pluck('source_game_match_id')->sort()->values()->all(),
        );

        $firstRow = $result->leagueRows->firstWhere(
            'source_entry_id',
            $fixture['entries']->first()->id,
        );
        $this->assertSame('player', $firstRow->entry_type);
        $this->assertSame($fixture['players']->first()->id, $firstRow->source_player_id);
        $this->assertNull($firstRow->source_team_id);
        $this->assertSame(OfficialIdentityProjection::ALIAS, $firstRow->identity_projection);
        $this->assertSame('Àlies Uno', $firstRow->display_name_snapshot);
        $this->assertSame('Àlies Uno', $firstRow->public_display_name);
        $this->assertNull($firstRow->public_anonymized_at);
        $this->assertSame(2, $firstRow->played);
        $this->assertSame(2, $firstRow->wins);
        $this->assertSame(0, $firstRow->losses);
        $this->assertSame(6, $firstRow->points);
        $this->assertSame(
            $firstRow->games_for - $firstRow->games_against,
            $firstRow->games_diff,
        );

        foreach ($result->matchSnapshots as $snapshot) {
            $source = $fixture['matches']->firstWhere('id', $snapshot->source_game_match_id);
            $this->assertSame($source->round_id, $snapshot->source_round_id);
            $this->assertSame('matchday', $snapshot->stage);
            $this->assertSame($source->home_entry_id, $snapshot->home_entry_id);
            $this->assertSame($source->away_entry_id, $snapshot->away_entry_id);
            $this->assertSame($source->home_score, $snapshot->home_score);
            $this->assertSame($source->away_score, $snapshot->away_score);
            $this->assertSame($source->winner_entry_id, $snapshot->winner_entry_id);
        }
    }

    public function test_officializes_doubles_with_team_sources_and_team_name_projection(): void
    {
        $fixture = $this->createReadyDoublesLeague();
        $result = app(OfficializeLeagueResultService::class)->officialize(
            $fixture['category'],
            $this->createActiveAdmin(),
        );

        $this->assertCount(3, $result->leagueRows);
        $this->assertCount(3, $result->matchSnapshots);

        foreach ($result->leagueRows as $row) {
            $team = $fixture['teams']->firstWhere('id', $row->source_team_id);
            $this->assertNotNull($team);
            $this->assertSame('team', $row->entry_type);
            $this->assertNull($row->source_player_id);
            $this->assertSame(OfficialIdentityProjection::TEAM_NAME, $row->identity_projection);
            $this->assertSame($team->name, $row->display_name_snapshot);
            $this->assertSame($team->name, $row->public_display_name);
            $this->assertSame(2, $row->played);
        }
    }

    public function test_officialize_allows_a_current_cup_but_rejects_a_current_league(): void
    {
        $withCup = $this->createReadySinglesLeague();
        CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $withCup['category']->id,
        ]);
        $league = app(OfficializeLeagueResultService::class)->officialize(
            $withCup['category'],
            $this->createActiveAdmin(),
        );
        $this->assertSame(OfficialResultCompetitionPart::LEAGUE, $league->competition_part);
        $this->assertSame(2, CategoryOfficialResult::query()->official()->count());

        $this->expectException(LeagueAlreadyOfficialException::class);
        app(OfficializeLeagueResultService::class)->officialize(
            $withCup['category'],
            $this->createActiveAdmin(),
        );
    }

    public function test_officialize_exposes_safe_readiness_codes_and_rolls_back_when_not_ready(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $fixture['matches']->first()->delete();

        try {
            app(OfficializeLeagueResultService::class)->officialize(
                $fixture['category'],
                $this->createActiveAdmin(),
            );
            $this->fail('Una Liga incompleta no puede oficializarse.');
        } catch (LeagueOfficializationNotReadyException $exception) {
            $this->assertContains('empty_league_round', $exception->reasonCodes());
            $this->assertContains('incomplete_round_robin', $exception->reasonCodes());
            $this->assertNotEmpty($exception->safeIssues());
            $this->assertStringNotContainsString('email', json_encode(
                $exception->safeIssues(),
                JSON_THROW_ON_ERROR,
            ));
        }

        $this->assertDatabaseCount('category_official_results', 0);
        $this->assertDatabaseCount('category_official_league_rows', 0);
        $this->assertDatabaseCount('category_official_result_match_snapshots', 0);
    }

    public function test_officialize_rolls_back_parent_when_a_child_insert_fails(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $listener = static function (): void {
            throw new \RuntimeException('Fallo de hijo controlado por la prueba.');
        };
        Event::listen('eloquent.creating: '.CategoryOfficialLeagueRow::class, $listener);

        try {
            app(OfficializeLeagueResultService::class)->officialize(
                $fixture['category'],
                $this->createActiveAdmin(),
            );
            $this->fail('La creación de la fila hija debía fallar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo de hijo controlado por la prueba.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.CategoryOfficialLeagueRow::class);
        }

        $this->assertSame(0, CategoryOfficialResult::query()->count());
        $this->assertSame(0, CategoryOfficialLeagueRow::query()->count());
    }

    public function test_version_history_must_be_contiguous_and_reopened(): void
    {
        $fixture = $this->createReadySinglesLeague();
        CategoryOfficialResult::factory()->league()->reopened()->create([
            'category_id' => $fixture['category']->id,
            'version' => 2,
        ]);

        $this->expectException(OfficialResultSourceIntegrityException::class);
        app(OfficializeLeagueResultService::class)->officialize(
            $fixture['category'],
            $this->createActiveAdmin(),
        );
    }

    public function test_reopen_then_reofficialize_creates_v2_and_allows_the_same_digest(): void
    {
        CarbonImmutable::setTestNow('2026-09-04 08:00:00');
        $fixture = $this->createReadySinglesLeague();
        $actor = $this->createActiveAdmin();
        $officialize = app(OfficializeLeagueResultService::class);
        $first = $officialize->officialize($fixture['category'], $actor);

        app(ReopenLeagueResultService::class)->reopen(
            $fixture['category'],
            $actor,
            'Revisión administrativa',
        );
        CarbonImmutable::setTestNow('2026-09-05 14:30:00');
        $secondActor = $this->createActiveAdmin([
            'name' => 'Otra',
            'lastname' => 'Administradora',
        ]);
        $second = $officialize->officialize($fixture['category'], $secondActor);

        $this->assertSame(2, $second->version);
        $this->assertSame($first->source_digest, $second->source_digest);
        $this->assertNotSame($first->officialized_by_user_id, $second->officialized_by_user_id);
        $this->assertNotSame(
            $first->officialized_at->toISOString(),
            $second->officialized_at->toISOString(),
        );
        $this->assertSame(
            [OfficialResultStatus::REOPENED, OfficialResultStatus::OFFICIAL],
            CategoryOfficialResult::query()->league()->orderBy('version')->get()->pluck('status')->all(),
        );
        $this->assertSame(1, CategoryOfficialResult::query()->league()->official()->count());
    }

    public function test_reopen_changes_only_reopen_metadata_and_preserves_all_evidence(): void
    {
        CarbonImmutable::setTestNow('2026-09-04 09:00:00');
        $fixture = $this->createReadySinglesLeague();
        $officializeActor = $this->createActiveAdmin([
            'name' => 'Oficial',
            'lastname' => 'Inicial',
        ]);
        $result = app(OfficializeLeagueResultService::class)->officialize(
            $fixture['category'],
            $officializeActor,
        );
        $result->load(['leagueRows', 'matchSnapshots']);
        $original = [
            'officialized_at' => $result->officialized_at->toISOString(),
            'officialized_by_user_id' => $result->officialized_by_user_id,
            'officialized_by_name_snapshot' => $result->officialized_by_name_snapshot,
            'source_digest' => $result->source_digest,
            'rows' => $result->leagueRows->map->getAttributes()->all(),
            'matches' => $result->matchSnapshots->map->getAttributes()->all(),
        ];

        CarbonImmutable::setTestNow('2026-09-04 11:22:33');
        $reopenActor = $this->createActiveAdmin([
            'name' => '  Reabre ',
            'lastname' => ' Resultado  ',
        ]);
        $reopened = app(ReopenLeagueResultService::class)->reopen(
            $fixture['category'],
            $reopenActor,
            "  Línea uno\r\nLínea dos  ",
        );

        $this->assertSame(OfficialResultStatus::REOPENED, $reopened->status);
        $this->assertNull($reopened->current_slot);
        $this->assertSame('2026-09-04 11:22:33', $reopened->reopened_at->format('Y-m-d H:i:s'));
        $this->assertSame($reopenActor->id, $reopened->reopened_by_user_id);
        $this->assertSame('Reabre Resultado', $reopened->reopened_by_name_snapshot);
        $this->assertSame("Línea uno\nLínea dos", $reopened->reopen_reason);
        $this->assertSame($original['officialized_at'], $reopened->officialized_at->toISOString());
        $this->assertSame($original['officialized_by_user_id'], $reopened->officialized_by_user_id);
        $this->assertSame($original['officialized_by_name_snapshot'], $reopened->officialized_by_name_snapshot);
        $this->assertSame($original['source_digest'], $reopened->source_digest);
        $this->assertSame($original['rows'], $reopened->leagueRows->map->getAttributes()->all());
        $this->assertSame($original['matches'], $reopened->matchSnapshots->map->getAttributes()->all());
    }

    public function test_reopen_rejects_blank_or_oversized_reasons_without_mutating_the_result(): void
    {
        foreach ([" \r\n ", str_repeat('á', 2001)] as $reason) {
            $fixture = $this->createReadySinglesLeague();
            $actor = $this->createActiveAdmin();
            $result = app(OfficializeLeagueResultService::class)->officialize(
                $fixture['category'],
                $actor,
            );

            try {
                app(ReopenLeagueResultService::class)->reopen(
                    $fixture['category'],
                    $actor,
                    $reason,
                );
                $this->fail('El motivo inválido debería rechazarse.');
            } catch (InvalidReopenReasonException) {
                $this->assertSame(OfficialResultStatus::OFFICIAL, $result->fresh()->status);
                $this->assertSame(1, $result->fresh()->current_slot);
            }
        }
    }

    public function test_invalid_actor_is_rejected_for_officialize_and_reopen(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $inactive = User::factory()->admin()->create(['active' => false]);

        try {
            app(OfficializeLeagueResultService::class)->officialize(
                $fixture['category'],
                $inactive,
            );
            $this->fail('Un actor inactivo no puede oficializar.');
        } catch (InvalidOfficialResultActorException) {
            $this->assertDatabaseCount('category_official_results', 0);
        }

        $actor = $this->createActiveAdmin();
        app(OfficializeLeagueResultService::class)->officialize($fixture['category'], $actor);
        $nonAdmin = User::factory()->create(['active' => true]);

        $this->expectException(InvalidOfficialResultActorException::class);
        app(ReopenLeagueResultService::class)->reopen(
            $fixture['category'],
            $nonAdmin,
            'Motivo válido',
        );
    }

    public function test_second_reopen_and_reopen_without_current_league_fail(): void
    {
        $withoutCurrent = $this->createReadySinglesLeague();
        try {
            app(ReopenLeagueResultService::class)->reopen(
                $withoutCurrent['category'],
                $this->createActiveAdmin(),
                'No existe',
            );
            $this->fail('No debería reabrirse una Liga inexistente.');
        } catch (NoCurrentLeagueOfficialResultException) {
            $this->assertDatabaseCount('category_official_results', 0);
        }

        $fixture = $this->createReadySinglesLeague();
        $actor = $this->createActiveAdmin();
        app(OfficializeLeagueResultService::class)->officialize($fixture['category'], $actor);
        app(ReopenLeagueResultService::class)->reopen(
            $fixture['category'],
            $actor,
            'Primera reapertura',
        );

        $this->expectException(NoCurrentLeagueOfficialResultException::class);
        app(ReopenLeagueResultService::class)->reopen(
            $fixture['category'],
            $actor,
            'Segunda reapertura',
        );
    }

    public function test_cup_does_not_block_reopen_but_keeps_league_writers_blocked_afterward(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $actor = $this->createActiveAdmin();
        app(OfficializeLeagueResultService::class)->officialize($fixture['category'], $actor);
        CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $fixture['category']->id,
        ]);

        $reopened = app(ReopenLeagueResultService::class)->reopen(
            $fixture['category'],
            $actor,
            'Liga reabierta con Copa vigente',
        );
        $this->assertSame(OfficialResultStatus::REOPENED, $reopened->status);

        $match = $fixture['matches']->first();
        $this->expectException(OfficialResultMutationBlockedException::class);
        app(MatchResultService::class)->updateFromAdmin(
            $match,
            $fixture['category']->id,
            CarbonImmutable::now()->addDay(),
            $match->venue_id,
            'validated',
            10,
            8,
            $actor,
        );
    }
}
