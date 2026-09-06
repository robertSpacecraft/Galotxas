<?php

namespace Tests\Feature;

use App\Enums\GameMatchStatus;
use App\Enums\OfficialIdentityProjection;
use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultMutationImpact;
use App\Enums\OfficialResultStatus;
use App\Exceptions\CupAlreadyOfficialException;
use App\Exceptions\CupOfficializationNotReadyException;
use App\Exceptions\InvalidOfficialResultActorException;
use App\Exceptions\InvalidReopenReasonException;
use App\Exceptions\NoCurrentCupOfficialResultException;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Models\CategoryOfficialCupWinner;
use App\Models\CategoryOfficialResult;
use App\Models\GameMatch;
use App\Models\PublicIdentityAuthorization;
use App\Models\User;
use App\Models\Venue;
use App\Services\MatchResultService;
use App\Services\OfficializeCupResultService;
use App\Services\OfficializeLeagueResultService;
use App\Services\OfficialResultMutationGuard;
use App\Services\ReopenCupResultService;
use App\Services\ReopenLeagueResultService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesOfficialCupFixture;
use Tests\TestCase;

class CupOfficialResultLifecycleTest extends TestCase
{
    use CreatesOfficialCupFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['public_identity.authorization_enabled' => true]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_officializes_v1_as_one_champion_and_three_decisive_snapshots(): void
    {
        CarbonImmutable::setTestNow('2026-09-05 10:20:30');
        $fixture = $this->createReadySinglesCup();
        $champion = $fixture['seed'][0];
        $champion->player->update(['nickname' => '  Àlies   Campió  ']);
        $actor = $this->createActiveAdmin();

        $result = app(OfficializeCupResultService::class)->officialize(
            $fixture['category'],
            $actor,
        );

        $this->assertSame(1, $result->version);
        $this->assertSame(OfficialResultCompetitionPart::CUP, $result->competition_part);
        $this->assertSame(OfficialResultStatus::OFFICIAL, $result->status);
        $this->assertSame(1, $result->current_slot);
        $this->assertSame('2026-09-05 10:20:30', $result->officialized_at->format('Y-m-d H:i:s'));
        $this->assertSame($actor->id, $result->officialized_by_user_id);
        $this->assertSame('Ada Administradora', $result->officialized_by_name_snapshot);
        $this->assertNull($result->reopened_at);
        $this->assertNull($result->reopen_reason);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $result->source_digest);
        $this->assertSame(0, $result->leagueRows()->count());
        $this->assertSame(1, $result->cupWinner()->count());
        $this->assertCount(3, $result->matchSnapshots);
        $this->assertSame(
            ['final', 'semifinal', 'semifinal'],
            $result->matchSnapshots->pluck('stage')->sort()->values()->all(),
        );

        $winner = $result->cupWinner;
        $this->assertSame($champion->id, $winner->source_entry_id);
        $this->assertSame($champion->player_id, $winner->source_player_id);
        $this->assertNull($winner->source_team_id);
        $this->assertSame($fixture['finalMatch']->id, $winner->source_final_match_id);
        $this->assertSame(OfficialIdentityProjection::ALIAS, $winner->identity_projection);
        $this->assertSame('Àlies Campió', $winner->display_name_snapshot);
        $this->assertSame('Àlies Campió', $winner->public_display_name);
        $this->assertNull($winner->public_anonymized_at);
        $this->assertNotContains(
            $fixture['thirdPlaceMatch']->id,
            $result->matchSnapshots->pluck('source_game_match_id')->all(),
        );
    }

    public function test_officializes_team_and_minor_champion_identity_without_pii(): void
    {
        $doubles = $this->createReadyDoublesCup();
        $teamResult = app(OfficializeCupResultService::class)->officialize(
            $doubles['category'],
            $this->createActiveAdmin(),
        );
        $this->assertSame(OfficialIdentityProjection::TEAM_NAME, $teamResult->cupWinner->identity_projection);
        $this->assertSame($doubles['seed'][0]->team->name, $teamResult->cupWinner->public_display_name);

        $minor = $this->createReadySinglesCup();
        $champion = $minor['seed'][0]->player;
        $champion->update([
            'birth_date' => '2014-01-01',
            'nickname' => 'Àlies autoritzat',
            'dni' => '12345678Z',
        ]);
        PublicIdentityAuthorization::factory()->approved()->create([
            'player_id' => $champion->id,
            'mode' => 'alias',
        ]);
        $minorResult = app(OfficializeCupResultService::class)->officialize(
            $minor['category'],
            $this->createActiveAdmin(),
        );
        $this->assertSame(OfficialIdentityProjection::ALIAS, $minorResult->cupWinner->identity_projection);
        $serialized = json_encode($minorResult->cupWinner->getAttributes(), JSON_THROW_ON_ERROR);
        foreach (['dni', 'email', 'birth_date', 'license_number', 'token', 'photo'] as $pii) {
            $this->assertStringNotContainsString($pii, $serialized);
        }

        $anonymous = $this->createReadySinglesCup();
        $anonymous['seed'][0]->player->update(['birth_date' => '2014-01-01']);
        $anonymousResult = app(OfficializeCupResultService::class)->officialize(
            $anonymous['category'],
            $this->createActiveAdmin(),
        );
        $this->assertSame(OfficialIdentityProjection::ANONYMOUS, $anonymousResult->cupWinner->identity_projection);
        $this->assertSame('Participante', $anonymousResult->cupWinner->public_display_name);
    }

    public function test_not_ready_and_child_failure_roll_back_the_whole_aggregate(): void
    {
        $notReady = $this->createReadySinglesCup();
        $notReady['finalMatch']->update(['status' => 'scheduled']);

        try {
            app(OfficializeCupResultService::class)->officialize(
                $notReady['category'],
                $this->createActiveAdmin(),
            );
            $this->fail('Una Copa incompleta no puede oficializarse.');
        } catch (CupOfficializationNotReadyException $exception) {
            $this->assertContains('match_not_validated', $exception->reasonCodes());
            $this->assertNotEmpty($exception->safeIssues());
        }
        $this->assertDatabaseCount('category_official_results', 0);

        $failure = $this->createReadySinglesCup();
        $listener = static function (): void {
            throw new \RuntimeException('Fallo de winner controlado.');
        };
        Event::listen('eloquent.creating: '.CategoryOfficialCupWinner::class, $listener);
        try {
            app(OfficializeCupResultService::class)->officialize(
                $failure['category'],
                $this->createActiveAdmin(),
            );
            $this->fail('La creación del winner debía fallar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo de winner controlado.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.CategoryOfficialCupWinner::class);
        }
        $this->assertDatabaseMissing('category_official_results', [
            'category_id' => $failure['category']->id,
        ]);
    }

    public function test_current_cup_is_rejected_and_history_must_be_contiguous_and_complete(): void
    {
        $current = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        app(OfficializeCupResultService::class)->officialize($current['category'], $actor);

        $this->expectException(CupAlreadyOfficialException::class);
        app(OfficializeCupResultService::class)->officialize($current['category'], $actor);
    }

    public function test_incomplete_or_gapped_cup_history_fails_closed(): void
    {
        $incomplete = $this->createReadySinglesCup();
        CategoryOfficialResult::factory()->cup()->reopened()->create([
            'category_id' => $incomplete['category']->id,
            'version' => 1,
        ]);
        try {
            app(OfficializeCupResultService::class)->officialize(
                $incomplete['category'],
                $this->createActiveAdmin(),
            );
            $this->fail('El histórico sin hijos debe fallar cerrado.');
        } catch (OfficialResultSourceIntegrityException) {
            $this->assertSame(1, CategoryOfficialResult::query()
                ->where('category_id', $incomplete['category']->id)
                ->count());
        }

        $gap = $this->createReadySinglesCup();
        CategoryOfficialResult::factory()->cup()->reopened()->create([
            'category_id' => $gap['category']->id,
            'version' => 2,
        ]);
        $this->expectException(OfficialResultSourceIntegrityException::class);
        app(OfficializeCupResultService::class)->officialize(
            $gap['category'],
            $this->createActiveAdmin(),
        );
    }

    public function test_reopen_preserves_all_evidence_and_reofficialize_creates_v2(): void
    {
        CarbonImmutable::setTestNow('2026-09-05 08:00:00');
        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        $officialize = app(OfficializeCupResultService::class);
        $first = $officialize->officialize($fixture['category'], $actor);
        $original = [
            'officialized_at' => $first->officialized_at->toISOString(),
            'officialized_by_user_id' => $first->officialized_by_user_id,
            'officialized_by_name_snapshot' => $first->officialized_by_name_snapshot,
            'source_digest' => $first->source_digest,
            'winner' => $first->cupWinner->getAttributes(),
            'matches' => $first->matchSnapshots->map->getAttributes()->all(),
        ];

        CarbonImmutable::setTestNow('2026-09-05 09:10:11');
        $reopened = app(ReopenCupResultService::class)->reopen(
            $fixture['category'],
            $actor,
            "  Línea uno\r\nLínea dos  ",
        );
        $this->assertSame(OfficialResultStatus::REOPENED, $reopened->status);
        $this->assertNull($reopened->current_slot);
        $this->assertSame("Línea uno\nLínea dos", $reopened->reopen_reason);
        $this->assertSame($original['officialized_at'], $reopened->officialized_at->toISOString());
        $this->assertSame($original['officialized_by_user_id'], $reopened->officialized_by_user_id);
        $this->assertSame($original['officialized_by_name_snapshot'], $reopened->officialized_by_name_snapshot);
        $this->assertSame($original['source_digest'], $reopened->source_digest);
        $this->assertSame($original['winner'], $reopened->cupWinner->getAttributes());
        $this->assertSame($original['matches'], $reopened->matchSnapshots->map->getAttributes()->all());

        CarbonImmutable::setTestNow('2026-09-06 10:00:00');
        $second = $officialize->officialize($fixture['category'], $actor);
        $this->assertSame(2, $second->version);
        $this->assertSame($first->source_digest, $second->source_digest);
        $this->assertSame(
            [OfficialResultStatus::REOPENED, OfficialResultStatus::OFFICIAL],
            CategoryOfficialResult::query()->cup()->orderBy('version')->get()->pluck('status')->all(),
        );
    }

    public function test_source_change_after_reopen_changes_v2_digest_and_keeps_v1_immutable(): void
    {
        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        $officialize = app(OfficializeCupResultService::class);
        $first = $officialize->officialize($fixture['category'], $actor);
        $firstEvidence = $first->matchSnapshots->map->getAttributes()->all();
        app(ReopenCupResultService::class)->reopen(
            $fixture['category'],
            $actor,
            'Corregir tanteo de la Final',
        );
        $fixture['finalMatch']->update(['away_score' => 8]);
        $second = $officialize->officialize($fixture['category'], $actor);

        $this->assertNotSame($first->source_digest, $second->source_digest);
        $this->assertSame(
            $firstEvidence,
            $first->fresh()->matchSnapshots()->orderBy('id')->get()->map->getAttributes()->all(),
        );
    }

    public function test_reopen_rejects_invalid_actor_reason_absence_and_second_attempt(): void
    {
        $withoutCurrent = $this->createReadySinglesCup();
        try {
            app(ReopenCupResultService::class)->reopen(
                $withoutCurrent['category'],
                $this->createActiveAdmin(),
                'No existe',
            );
            $this->fail('No debería reabrirse una Copa inexistente.');
        } catch (NoCurrentCupOfficialResultException) {
            $this->assertDatabaseCount('category_official_results', 0);
        }

        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        app(OfficializeCupResultService::class)->officialize($fixture['category'], $actor);

        try {
            app(ReopenCupResultService::class)->reopen(
                $fixture['category'],
                User::factory()->create(['active' => true]),
                'Actor inválido',
            );
            $this->fail('Un no administrador no puede reabrir.');
        } catch (InvalidOfficialResultActorException) {
            $this->assertSame(OfficialResultStatus::OFFICIAL, CategoryOfficialResult::query()->cup()->sole()->status);
        }

        foreach ([" \r\n ", str_repeat('á', 2001)] as $reason) {
            try {
                app(ReopenCupResultService::class)->reopen($fixture['category'], $actor, $reason);
                $this->fail('El motivo inválido debe rechazarse.');
            } catch (InvalidReopenReasonException) {
                $this->assertSame(OfficialResultStatus::OFFICIAL, CategoryOfficialResult::query()->cup()->sole()->status);
            }
        }

        app(ReopenCupResultService::class)->reopen($fixture['category'], $actor, 'Primera');
        $this->expectException(NoCurrentCupOfficialResultException::class);
        app(ReopenCupResultService::class)->reopen($fixture['category'], $actor, 'Segunda');
    }

    public function test_league_and_cup_lifecycles_coexist_without_cascades(): void
    {
        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        $league = app(OfficializeLeagueResultService::class)->officialize($fixture['category'], $actor);
        $cup = app(OfficializeCupResultService::class)->officialize($fixture['category'], $actor);
        $this->assertSame(2, CategoryOfficialResult::query()->official()->count());

        $reopenedCup = app(ReopenCupResultService::class)->reopen(
            $fixture['category'],
            $actor,
            'Reabrir sólo Copa',
        );
        $this->assertSame(OfficialResultStatus::REOPENED, $reopenedCup->status);
        $this->assertSame(OfficialResultStatus::OFFICIAL, $league->fresh()->status);

        $venue = Venue::factory()->create();
        $cupMatch = $fixture['finalMatch'];
        app(MatchResultService::class)->updateFromAdmin(
            $cupMatch,
            $fixture['category']->id,
            CarbonImmutable::now()->addDay(),
            $venue->id,
            GameMatchStatus::VALIDATED->value,
            10,
            8,
            $actor,
        );

        $leagueMatch = $fixture['matches']->first();
        $this->expectException(OfficialResultMutationBlockedException::class);
        app(MatchResultService::class)->updateFromAdmin(
            $leagueMatch,
            $fixture['category']->id,
            CarbonImmutable::now()->addDay(),
            $venue->id,
            GameMatchStatus::VALIDATED->value,
            10,
            8,
            $actor,
        );
    }

    public function test_reopened_league_uses_the_live_seed_when_officializing_cup(): void
    {
        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        app(OfficializeLeagueResultService::class)->officialize($fixture['category'], $actor);
        app(ReopenLeagueResultService::class)->reopen(
            $fixture['category'],
            $actor,
            'Reabrir Liga antes de oficializar Copa',
        );

        $cup = app(OfficializeCupResultService::class)->officialize($fixture['category'], $actor);

        $this->assertSame(OfficialResultStatus::OFFICIAL, $cup->status);
        $this->assertSame(1, $cup->version);
        $this->assertSame(OfficialResultStatus::REOPENED, CategoryOfficialResult::query()
            ->where('category_id', $fixture['category']->id)
            ->league()
            ->sole()
            ->status);
    }

    public function test_reofficializes_cup_while_league_remains_official(): void
    {
        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        $league = app(OfficializeLeagueResultService::class)->officialize($fixture['category'], $actor);
        app(OfficializeCupResultService::class)->officialize($fixture['category'], $actor);
        app(ReopenCupResultService::class)->reopen(
            $fixture['category'],
            $actor,
            'Reabrir sólo Copa',
        );

        $cup = app(OfficializeCupResultService::class)->officialize($fixture['category'], $actor);

        $this->assertSame(2, $cup->version);
        $this->assertSame(OfficialResultStatus::OFFICIAL, $league->fresh()->status);
        $this->assertSame(1, CategoryOfficialResult::query()
            ->where('category_id', $fixture['category']->id)
            ->league()
            ->official()
            ->count());
    }

    public function test_reopening_cup_without_league_releases_all_sporting_mutations(): void
    {
        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        app(OfficializeCupResultService::class)->officialize($fixture['category'], $actor);
        app(ReopenCupResultService::class)->reopen(
            $fixture['category'],
            $actor,
            'Liberar Copa sin Liga vigente',
        );

        $this->updateMatch($fixture['matches']->first(), $fixture['category']->id, $actor);
        $this->updateMatch($fixture['finalMatch'], $fixture['category']->id, $actor);
        DB::transaction(fn () => app(OfficialResultMutationGuard::class)->lockAndGuard(
            $fixture['category'],
            OfficialResultMutationImpact::PARTICIPANTS,
        ));

        $this->assertSame(0, CategoryOfficialResult::query()
            ->where('category_id', $fixture['category']->id)
            ->official()
            ->count());
    }

    public function test_both_reopened_release_general_sporting_mutations(): void
    {
        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        app(OfficializeLeagueResultService::class)->officialize($fixture['category'], $actor);
        app(OfficializeCupResultService::class)->officialize($fixture['category'], $actor);
        app(ReopenCupResultService::class)->reopen($fixture['category'], $actor, 'Reabrir Copa');
        app(ReopenLeagueResultService::class)->reopen($fixture['category'], $actor, 'Reabrir Liga');

        $this->updateMatch($fixture['matches']->first(), $fixture['category']->id, $actor);
        $this->updateMatch($fixture['finalMatch'], $fixture['category']->id, $actor);
        DB::transaction(fn () => app(OfficialResultMutationGuard::class)->lockAndGuard(
            $fixture['category'],
            OfficialResultMutationImpact::PARTICIPANTS,
        ));

        $this->assertSame(0, CategoryOfficialResult::query()
            ->where('category_id', $fixture['category']->id)
            ->official()
            ->count());
    }

    public function test_third_place_remains_editable_while_cup_is_official(): void
    {
        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        $result = app(OfficializeCupResultService::class)->officialize($fixture['category'], $actor);
        $digest = $result->source_digest;
        $venue = Venue::factory()->create();

        app(MatchResultService::class)->updateFromAdmin(
            $fixture['thirdPlaceMatch'],
            $fixture['category']->id,
            CarbonImmutable::now()->addDay(),
            $venue->id,
            GameMatchStatus::VALIDATED->value,
            10,
            7,
            $actor,
        );

        $this->assertSame($digest, $result->fresh()->source_digest);
        $this->assertCount(3, $result->fresh()->matchSnapshots);
    }

    private function updateMatch(GameMatch $match, int $categoryId, User $actor): void
    {
        app(MatchResultService::class)->updateFromAdmin(
            $match,
            $categoryId,
            CarbonImmutable::now()->addDay(),
            Venue::factory()->create()->id,
            GameMatchStatus::VALIDATED->value,
            10,
            8,
            $actor,
        );
    }
}
