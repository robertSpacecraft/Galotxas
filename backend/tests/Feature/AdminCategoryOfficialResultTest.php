<?php

namespace Tests\Feature;

use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use App\Exceptions\OfficialResultConcurrencyConflictException;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Models\User;
use App\Services\OfficializeCupResultService;
use App\Services\OfficializeLeagueResultService;
use App\Services\ReopenCupResultService;
use App\Services\ReopenLeagueResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOfficialCupFixture;
use Tests\TestCase;

class AdminCategoryOfficialResultTest extends TestCase
{
    use CreatesOfficialCupFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['public_identity.authorization_enabled' => true]);
    }

    public function test_category_show_presents_both_not_ready_states_with_human_messages(): void
    {
        $admin = $this->createActiveAdmin();
        $category = Category::factory()->create(['name' => 'Categoría sin calendario']);

        $response = $this->actingAs($admin)
            ->get(route('admin.categories.show', $category));

        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('Resultados oficiales')
            ->assertSee('No lista para oficializar')
            ->assertSee('Hay 0 inscripciones aprobadas; se necesitan al menos 3 para oficializar la Liga.')
            ->assertSee('Hay 0 inscripciones aprobadas; se necesitan al menos 4 para oficializar la Copa.')
            ->assertSee('No existe ninguna jornada de Liga.')
            ->assertSee('Falta la ronda de semifinales.')
            ->assertSee('Falta la ronda final.')
            ->assertSee('Oficializar Liga')
            ->assertSee('Oficializar Copa');

        $this->assertOfficializationUnavailable(
            $html,
            'Liga',
            route('admin.categories.official-results.league.officialize', $category),
        );
        $this->assertOfficializationUnavailable(
            $html,
            'Copa',
            route('admin.categories.official-results.cup.officialize', $category),
        );
    }

    public function test_ready_sources_enable_both_explicit_officialization_actions(): void
    {
        $fixture = $this->createReadySinglesCup();
        $admin = $this->createActiveAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.categories.show', $fixture['category']));

        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('Lista para oficializar')
            ->assertSee('Oficializar Liga')
            ->assertSee('Oficializar Copa');

        $this->assertOfficializationPostAvailable(
            $html,
            'Liga',
            route('admin.categories.official-results.league.officialize', $fixture['category']),
        );
        $this->assertOfficializationPostAvailable(
            $html,
            'Copa',
            route('admin.categories.official-results.cup.officialize', $fixture['category']),
        );
    }

    public function test_admin_officializes_league_through_real_flow_and_sees_v1_in_history(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $admin = $this->createActiveAdmin();

        $response = $this->actingAs($admin)->post(
            route('admin.categories.official-results.league.officialize', $fixture['category']),
            ['version' => 99, 'source_digest' => str_repeat('f', 64)],
        );

        $response
            ->assertRedirect(route('admin.categories.show', $fixture['category']))
            ->assertSessionHas('success', 'Liga oficializada correctamente como v1.');

        $result = CategoryOfficialResult::query()->sole();
        $this->assertSame(OfficialResultCompetitionPart::LEAGUE, $result->competition_part);
        $this->assertSame(OfficialResultStatus::OFFICIAL, $result->status);
        $this->assertSame(1, $result->version);
        $this->assertNotSame(str_repeat('f', 64), $result->source_digest);

        $this->actingAs($admin)
            ->get(route('admin.categories.show', $fixture['category']))
            ->assertOk()
            ->assertSee('Histórico')
            ->assertSee('v1')
            ->assertSee(route('admin.categories.official-results.show', [$fixture['category'], $result]), false);
    }

    public function test_admin_officializes_cup_through_real_flow_and_sees_v1_in_history(): void
    {
        $fixture = $this->createReadySinglesCup();
        $admin = $this->createActiveAdmin();

        $response = $this->actingAs($admin)->post(
            route('admin.categories.official-results.cup.officialize', $fixture['category']),
            ['actor' => User::factory()->create()->id, 'identity' => 'manipulada'],
        );

        $response
            ->assertRedirect(route('admin.categories.show', $fixture['category']))
            ->assertSessionHas('success', 'Copa oficializada correctamente como v1.');

        $result = CategoryOfficialResult::query()->sole();
        $this->assertSame(OfficialResultCompetitionPart::CUP, $result->competition_part);
        $this->assertSame(OfficialResultStatus::OFFICIAL, $result->status);
        $this->assertSame($admin->id, $result->officialized_by_user_id);
        $this->assertSame(1, $result->cupWinner()->count());
        $this->assertSame(3, $result->matchSnapshots()->count());

        $this->actingAs($admin)
            ->get(route('admin.categories.show', $fixture['category']))
            ->assertOk()
            ->assertSee(route('admin.categories.official-results.show', [$fixture['category'], $result]), false);
    }

    public function test_post_rechecks_readiness_after_render_and_returns_safe_feedback_without_version(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $admin = $this->createActiveAdmin();

        $this->actingAs($admin)
            ->get(route('admin.categories.show', $fixture['category']))
            ->assertOk()
            ->assertSee('Lista para oficializar');

        $changedMatch = $fixture['matches']->first();
        $changedMatch->update(['status' => 'scheduled']);

        $response = $this->actingAs($admin)->post(
            route('admin.categories.official-results.league.officialize', $fixture['category']),
        );

        $response
            ->assertRedirect(route('admin.categories.show', $fixture['category']))
            ->assertSessionHas('error', 'La Liga ya no reúne las condiciones para oficializarse.')
            ->assertSessionHas(
                'official_result_issues',
                fn (array $issues): bool => in_array(
                    "El partido todavía no está validado (partido #{$changedMatch->id}).",
                    $issues,
                    true,
                ),
            );
        $this->assertDatabaseCount('category_official_results', 0);
    }

    public function test_current_results_replace_officialize_actions_with_version_detail_and_reopen(): void
    {
        $fixture = $this->createReadySinglesCup();
        $admin = $this->createActiveAdmin();
        $league = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);
        $cup = app(OfficializeCupResultService::class)
            ->officialize($fixture['category'], $admin);

        $response = $this->actingAs($admin)
            ->get(route('admin.categories.show', $fixture['category']));

        $response
            ->assertOk()
            ->assertSee('Oficial')
            ->assertSee('v1')
            ->assertSee('Las mutaciones relacionadas con Liga permanecen bloqueadas')
            ->assertSee('Las mutaciones relacionadas con Copa permanecen bloqueadas')
            ->assertSee(route('admin.categories.official-results.show', [$fixture['category'], $league]), false)
            ->assertSee(route('admin.categories.official-results.show', [$fixture['category'], $cup]), false)
            ->assertSee(route('admin.categories.official-results.reopen.form', [$fixture['category'], $league]), false)
            ->assertSee(route('admin.categories.official-results.reopen.form', [$fixture['category'], $cup]), false)
            ->assertDontSee(
                route('admin.categories.official-results.league.officialize', $fixture['category']),
                false,
            )
            ->assertDontSee(
                route('admin.categories.official-results.cup.officialize', $fixture['category']),
                false,
            );
    }

    public function test_mixed_history_is_stable_and_keeps_league_and_cup_independent(): void
    {
        $fixture = $this->createReadySinglesCup();
        $admin = $this->createActiveAdmin();
        $leagueV1 = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);
        $cupV1 = app(OfficializeCupResultService::class)
            ->officialize($fixture['category'], $admin);
        app(ReopenLeagueResultService::class)
            ->reopen($fixture['category'], $admin, 'Nueva acta de Liga');
        app(ReopenCupResultService::class)
            ->reopen($fixture['category'], $admin, 'Nueva acta de Copa');
        $leagueV2 = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);
        $cupV2 = app(OfficializeCupResultService::class)
            ->officialize($fixture['category'], $admin);

        $response = $this->actingAs($admin)
            ->get(route('admin.categories.show', $fixture['category']));

        $response
            ->assertOk()
            ->assertViewHas(
                'officialResults',
                fn (array $panel): bool => $panel['history']->modelKeys() === [
                    $leagueV2->id,
                    $leagueV1->id,
                    $cupV2->id,
                    $cupV1->id,
                ],
            );

        foreach ([$leagueV1, $leagueV2, $cupV1, $cupV2] as $result) {
            $response->assertSee(
                route('admin.categories.official-results.show', [$fixture['category'], $result]),
                false,
            );
        }
    }

    public function test_league_detail_uses_only_persisted_identity_ranking_matches_and_metadata(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $admin = $this->createActiveAdmin(['email' => 'admin-secret@example.test']);
        $result = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);
        $result = app(ReopenLeagueResultService::class)
            ->reopen($fixture['category'], $admin, 'Acta corregida por revisión');
        $result->load(['leagueRows', 'matchSnapshots']);
        $snapshotNames = $result->leagueRows->pluck('display_name_snapshot')->all();

        foreach ($fixture['players']->values() as $index => $player) {
            $player->update(['nickname' => 'NOMBRE-LIVE-SECRETO-'.($index + 1)]);
            $player->user->update(['email' => "jugador{$index}@secret.example"]);
        }

        $response = $this->actingAs($admin)->get(
            route('admin.categories.official-results.show', [$fixture['category'], $result]),
        );

        $response
            ->assertOk()
            ->assertSee('Resultado oficial de Liga · v1')
            ->assertSee('Reabierto')
            ->assertSee('Acta corregida por revisión')
            ->assertSee($result->source_digest)
            ->assertSee('Clasificación persistida')
            ->assertSee('Evidencia de partidos persistida')
            ->assertDontSee('admin-secret@example.test');

        foreach ($snapshotNames as $snapshotName) {
            $response->assertSee($snapshotName);
        }
        foreach ($fixture['players']->values() as $index => $player) {
            $response
                ->assertDontSee('NOMBRE-LIVE-SECRETO-'.($index + 1))
                ->assertDontSee("jugador{$index}@secret.example");
        }

        $response->assertViewHas(
            'officialResult',
            fn (CategoryOfficialResult $loaded): bool => $loaded->leagueRows
                ->pluck('position')->all() === [1, 2, 3]
                && $loaded->matchSnapshots->pluck('source_game_match_id')->all()
                    === $fixture['matches']->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_cup_detail_shows_only_persisted_champion_and_decisive_evidence(): void
    {
        $fixture = $this->createReadySinglesCup();
        $admin = $this->createActiveAdmin(['email' => 'cup-admin-secret@example.test']);
        $result = app(OfficializeCupResultService::class)
            ->officialize($fixture['category'], $admin);
        $result->load(['cupWinner', 'matchSnapshots']);
        $championName = $result->cupWinner->display_name_snapshot;

        foreach ($fixture['players']->values() as $index => $player) {
            $player->update(['nickname' => 'SEMIFINALISTA-LIVE-'.($index + 1)]);
            $player->user->update(['email' => "semifinal{$index}@secret.example"]);
        }

        $response = $this->actingAs($admin)->get(
            route('admin.categories.official-results.show', [$fixture['category'], $result]),
        );

        $response
            ->assertOk()
            ->assertSee('Resultado oficial de Copa · v1')
            ->assertSee('Campeón persistido')
            ->assertSee($championName)
            ->assertSee((string) $result->cupWinner->source_entry_id)
            ->assertSee((string) $result->cupWinner->source_final_match_id)
            ->assertSee($result->source_digest)
            ->assertDontSee('Subcampeón')
            ->assertDontSee('Tercer puesto')
            ->assertDontSee('cup-admin-secret@example.test');

        foreach ($fixture['players']->values() as $index => $player) {
            $response
                ->assertDontSee('SEMIFINALISTA-LIVE-'.($index + 1))
                ->assertDontSee("semifinal{$index}@secret.example");
        }

        $response->assertViewHas(
            'officialResult',
            fn (CategoryOfficialResult $loaded): bool => $loaded->matchSnapshots->count() === 3
                && $loaded->matchSnapshots->pluck('stage')->all() === [
                    'semifinal',
                    'semifinal',
                    'final',
                ]
                && ! $loaded->matchSnapshots
                    ->pluck('source_game_match_id')
                    ->contains($fixture['thirdPlaceMatch']->id),
        );
    }

    public function test_cross_category_result_is_not_found_in_detail_or_reopen_routes(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $otherCategory = Category::factory()->create();
        $admin = $this->createActiveAdmin();
        $result = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);

        $this->actingAs($admin)
            ->get(route('admin.categories.official-results.show', [$otherCategory, $result]))
            ->assertNotFound();
        $this->actingAs($admin)
            ->get(route('admin.categories.official-results.reopen.form', [$otherCategory, $result]))
            ->assertNotFound();
        $this->actingAs($admin)
            ->post(
                route('admin.categories.official-results.reopen', [$otherCategory, $result]),
                ['reason' => 'No debe aplicarse'],
            )
            ->assertNotFound();

        $this->assertSame(OfficialResultStatus::OFFICIAL, $result->fresh()->status);
    }

    public function test_reopen_form_validates_required_and_maximum_reason_without_mutation(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $admin = $this->createActiveAdmin();
        $result = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);
        $url = route('admin.categories.official-results.reopen', [$fixture['category'], $result]);

        $this->actingAs($admin)
            ->from(route('admin.categories.official-results.reopen.form', [$fixture['category'], $result]))
            ->post($url, [])
            ->assertSessionHasErrors(['reason' => 'Debes indicar el motivo de la reapertura.']);

        $this->actingAs($admin)
            ->post($url, ['reason' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('reason');

        $result->refresh();
        $this->assertSame(OfficialResultStatus::OFFICIAL, $result->status);
        $this->assertNull($result->reopened_at);
        $this->assertNull($result->reopen_reason);
    }

    public function test_reopen_current_league_preserves_evidence_and_keeps_cup_official(): void
    {
        $fixture = $this->createReadySinglesCup();
        $admin = $this->createActiveAdmin();
        $league = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);
        $cup = app(OfficializeCupResultService::class)
            ->officialize($fixture['category'], $admin);
        $league->load(['leagueRows', 'matchSnapshots']);
        $before = $this->leagueEvidence($league);

        $response = $this->actingAs($admin)->post(
            route('admin.categories.official-results.reopen', [$fixture['category'], $league]),
            ['reason' => 'Corregir clasificación aprobada'],
        );

        $response
            ->assertRedirect(route('admin.categories.show', $fixture['category']))
            ->assertSessionHas('success', 'Liga reabierta correctamente (v1).');

        $league->refresh()->load(['leagueRows', 'matchSnapshots']);
        $this->assertSame(OfficialResultStatus::REOPENED, $league->status);
        $this->assertSame('Corregir clasificación aprobada', $league->reopen_reason);
        $this->assertSame($before, $this->leagueEvidence($league));
        $this->assertSame(OfficialResultStatus::OFFICIAL, $cup->fresh()->status);
    }

    public function test_reopen_current_cup_preserves_evidence_and_keeps_league_official(): void
    {
        $fixture = $this->createReadySinglesCup();
        $admin = $this->createActiveAdmin();
        $league = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);
        $cup = app(OfficializeCupResultService::class)
            ->officialize($fixture['category'], $admin);
        $cup->load(['cupWinner', 'matchSnapshots']);
        $before = $this->cupEvidence($cup);

        $response = $this->actingAs($admin)->post(
            route('admin.categories.official-results.reopen', [$fixture['category'], $cup]),
            ['reason' => 'Revisar el acta de la final'],
        );

        $response
            ->assertRedirect(route('admin.categories.show', $fixture['category']))
            ->assertSessionHas('success', 'Copa reabierta correctamente (v1).');

        $cup->refresh()->load(['cupWinner', 'matchSnapshots']);
        $this->assertSame(OfficialResultStatus::REOPENED, $cup->status);
        $this->assertSame('Revisar el acta de la final', $cup->reopen_reason);
        $this->assertSame($before, $this->cupEvidence($cup));
        $this->assertSame(OfficialResultStatus::OFFICIAL, $league->fresh()->status);
    }

    public function test_reopened_historical_versions_cannot_be_reopened_again(): void
    {
        $fixture = $this->createReadySinglesCup();
        $admin = $this->createActiveAdmin();
        $leagueV1 = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);
        $cupV1 = app(OfficializeCupResultService::class)
            ->officialize($fixture['category'], $admin);
        app(ReopenLeagueResultService::class)
            ->reopen($fixture['category'], $admin, 'Cerrar v1 Liga');
        app(ReopenCupResultService::class)
            ->reopen($fixture['category'], $admin, 'Cerrar v1 Copa');
        $leagueV2 = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);
        $cupV2 = app(OfficializeCupResultService::class)
            ->officialize($fixture['category'], $admin);

        foreach ([$leagueV1, $cupV1] as $historical) {
            $this->actingAs($admin)
                ->get(route('admin.categories.official-results.reopen.form', [$fixture['category'], $historical]))
                ->assertNotFound();
            $this->actingAs($admin)
                ->post(
                    route('admin.categories.official-results.reopen', [$fixture['category'], $historical]),
                    ['reason' => 'No debe reabrirse'],
                )
                ->assertNotFound();
            $this->assertSame(OfficialResultStatus::REOPENED, $historical->fresh()->status);
        }

        $this->assertSame(OfficialResultStatus::OFFICIAL, $leagueV2->fresh()->status);
        $this->assertSame(OfficialResultStatus::OFFICIAL, $cupV2->fresh()->status);
    }

    public function test_service_refuses_to_reopen_a_different_version_than_the_requested_one(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $admin = $this->createActiveAdmin();
        $leagueV1 = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);
        app(ReopenLeagueResultService::class)
            ->reopen($fixture['category'], $admin, 'Crear una segunda versión');
        $leagueV2 = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);

        try {
            app(ReopenLeagueResultService::class)->reopen(
                $fixture['category'],
                $admin,
                'No reabrir otra versión',
                $leagueV1,
            );
            $this->fail('La reapertura debía rechazar el cambio concurrente de versión.');
        } catch (OfficialResultConcurrencyConflictException) {
            $this->assertSame(OfficialResultStatus::REOPENED, $leagueV1->fresh()->status);
            $this->assertSame(OfficialResultStatus::OFFICIAL, $leagueV2->fresh()->status);
        }
    }

    public function test_official_result_routes_require_active_admin_authentication(): void
    {
        $fixture = $this->createReadySinglesLeague();
        $admin = $this->createActiveAdmin();
        $result = app(OfficializeLeagueResultService::class)
            ->officialize($fixture['category'], $admin);
        $detailUrl = route('admin.categories.official-results.show', [$fixture['category'], $result]);
        $reopenUrl = route('admin.categories.official-results.reopen.form', [$fixture['category'], $result]);
        $officializeUrl = route(
            'admin.categories.official-results.cup.officialize',
            $fixture['category'],
        );

        auth()->logout();
        $this->get($detailUrl)->assertRedirect(route('admin.login'));
        $this->get($reopenUrl)->assertRedirect(route('admin.login'));
        $this->post($officializeUrl)->assertRedirect(route('admin.login'));

        $normalUser = User::factory()->create();
        $this->actingAs($normalUser)->get($detailUrl)->assertForbidden();
        $this->actingAs($normalUser)->get($reopenUrl)->assertForbidden();
        $this->actingAs($normalUser)->post($officializeUrl)->assertForbidden();

        $inactiveAdmin = User::factory()->admin()->create(['active' => false]);
        $this->actingAs($inactiveAdmin)
            ->withSession(['session_sentinel' => 'remove'])
            ->get($detailUrl)
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Tu usuario está inactivo.'])
            ->assertSessionMissing('session_sentinel');
        $this->assertGuest('web');
    }

    public function test_missing_persisted_children_render_clear_read_only_empty_states(): void
    {
        $category = Category::factory()->create();
        $league = CategoryOfficialResult::factory()->league()->reopened()->create([
            'category_id' => $category->id,
        ]);
        $cup = CategoryOfficialResult::factory()->cup()->reopened()->create([
            'category_id' => $category->id,
        ]);
        $admin = $this->createActiveAdmin();

        $this->actingAs($admin)
            ->get(route('admin.categories.official-results.show', [$category, $league]))
            ->assertOk()
            ->assertSee('Esta versión no contiene filas de clasificación persistidas.')
            ->assertSee('Esta versión no contiene evidencia de partidos persistida.');

        $this->actingAs($admin)
            ->get(route('admin.categories.official-results.show', [$category, $cup]))
            ->assertOk()
            ->assertSee('Esta versión no contiene un campeón persistido.')
            ->assertSee('Esta versión no contiene evidencia de partidos persistida.');

        $this->assertDatabaseHas('category_official_results', ['id' => $league->id]);
        $this->assertDatabaseHas('category_official_results', ['id' => $cup->id]);
    }

    public function test_category_show_keeps_export_ranking_league_cup_and_existing_forms(): void
    {
        $fixture = $this->createReadySinglesCup();
        $admin = $this->createActiveAdmin();
        $category = $fixture['category'];
        $match = $fixture['matches']->first();

        $this->actingAs($admin)
            ->get(route('admin.categories.show', $category))
            ->assertOk()
            ->assertSee('Exportar')
            ->assertSee(route('admin.categories.export', $category), false)
            ->assertSee('Inscripciones')
            ->assertSee('Ranking de categoría')
            ->assertSee('Liga')
            ->assertSee('Copa')
            ->assertSee(route('admin.categories.registrations.store', $category), false)
            ->assertSee(route('admin.categories.generate-league', $category), false)
            ->assertSee(route('admin.categories.generate-cup', $category), false)
            ->assertSee(route('admin.categories.generate-finals', $category), false)
            ->assertSee(route('admin.categories.matches.update', [$category, $match]), false);
    }

    /** @return array<string, mixed> */
    private function leagueEvidence(CategoryOfficialResult $result): array
    {
        return [
            'officialized_at' => $result->officialized_at?->format('Y-m-d H:i:s.u'),
            'officialized_by_user_id' => $result->officialized_by_user_id,
            'officialized_by_name_snapshot' => $result->officialized_by_name_snapshot,
            'source_digest' => $result->source_digest,
            'league_rows' => $result->leagueRows->map->getAttributes()->all(),
            'match_snapshots' => $result->matchSnapshots->map->getAttributes()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function cupEvidence(CategoryOfficialResult $result): array
    {
        return [
            'officialized_at' => $result->officialized_at?->format('Y-m-d H:i:s.u'),
            'officialized_by_user_id' => $result->officialized_by_user_id,
            'officialized_by_name_snapshot' => $result->officialized_by_name_snapshot,
            'source_digest' => $result->source_digest,
            'cup_winner' => $result->cupWinner?->getAttributes(),
            'match_snapshots' => $result->matchSnapshots->map->getAttributes()->all(),
        ];
    }

    private function assertOfficializationUnavailable(string $html, string $label, string $action): void
    {
        $this->assertStringNotContainsString('action="'.$action.'"', $html);
        $this->assertMatchesRegularExpression(
            '/<button\\b(?=[^>]*\\bclass="[^"]*\\bbtn-outline-secondary\\b[^"]*")(?=[^>]*\\bdisabled\\b)(?=[^>]*\\baria-disabled="true")[^>]*>\\s*Oficializar '.preg_quote($label, '/').'\\s*<\\/button>/s',
            $html,
        );
    }

    private function assertOfficializationPostAvailable(string $html, string $label, string $action): void
    {
        $this->assertMatchesRegularExpression(
            '/<form\\b(?=[^>]*\\bmethod="POST")(?=[^>]*\\baction="'.preg_quote($action, '/').'")(?=[^>]*\\bonsubmit="return confirm\\()[^>]*>.*?<button\\b(?=[^>]*\\btype="submit")[^>]*>\\s*Oficializar '.preg_quote($label, '/').'\\s*<\\/button>\\s*<\\/form>/s',
            $html,
        );
    }
}
