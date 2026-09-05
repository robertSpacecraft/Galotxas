<?php

namespace Tests\Feature;

use App\Exceptions\CategoryCompetitionExportException;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PublicIdentityAuthorization;
use App\Models\Round;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Services\CompetitionExport\BuildCategoryCompetitionExportDocumentService;
use App\Services\CompetitionExport\RenderCategoryCompetitionPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CategoryCompetitionPdfExportTest extends TestCase
{
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

    public function test_export_route_and_admin_action_enforce_access_and_return_a_real_pdf(): void
    {
        [$category, $entries, $round] = $this->competitionBranch();
        $this->match($round, $entries[0], $entries[1], [
            'scheduled_date' => '2026-09-12 18:30:00',
        ]);

        $exportUrl = route('admin.categories.export', $category);

        $this->get($exportUrl)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())
            ->get($exportUrl)
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $show = $this->actingAs($admin)
            ->get(route('admin.categories.show', $category))
            ->assertOk()
            ->assertSee('Exportar')
            ->assertSee('href="'.$exportUrl.'"', false);
        $this->assertStringNotContainsString('data-bs-target="#export', $show->getContent());

        $response = $this->actingAs($admin)->get($exportUrl)->assertOk();

        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(
            'attachment; filename="temporada-2026-campionat-de-la-ribera-primera-categoria.pdf"',
            $response->headers->get('Content-Disposition')
        );
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('%%EOF', substr($response->getContent(), -32));
    }

    public function test_category_without_matches_disables_action_and_direct_export_fails_closed(): void
    {
        [$category] = $this->competitionBranch();
        $admin = User::factory()->admin()->create();
        $exportUrl = route('admin.categories.export', $category);

        $show = $this->actingAs($admin)
            ->get(route('admin.categories.show', $category))
            ->assertOk()
            ->assertSee('Exportar')
            ->assertSee('disabled', false);
        $this->assertStringNotContainsString('href="'.$exportUrl.'"', $show->getContent());

        $this->actingAs($admin)
            ->get($exportUrl)
            ->assertRedirect(route('admin.categories.show', $category))
            ->assertSessionHas(
                'error',
                CategoryCompetitionExportException::NO_MATCHES
            );
    }

    public function test_builder_projects_live_content_statuses_and_deterministic_league_and_cup_order(): void
    {
        [$category, $entries] = $this->competitionBranch(createRound: false);
        $venue = Venue::factory()->create(['name' => 'Pista Central']);

        $legacyLeague = Round::factory()->create([
            'category_id' => $category->id,
            'name' => 'Texto libre irrelevante',
            'order' => 2,
            'type' => 'league',
            'phase' => null,
            'stage' => null,
        ]);
        $normalizedLeague = Round::factory()->create([
            'category_id' => $category->id,
            'order' => 1,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);

        $this->match($legacyLeague, $entries[0], $entries[1], [
            'scheduled_date' => '2026-09-14 19:45:00',
            'status' => 'under_review',
            'home_score' => 10,
            'away_score' => 8,
        ]);
        $this->match($normalizedLeague, $entries[0], $entries[1], [
            'venue_id' => $venue->id,
            'scheduled_date' => null,
            'status' => 'scheduled',
        ]);
        $this->match($normalizedLeague, $entries[1], $entries[0], [
            'venue_id' => $venue->id,
            'scheduled_date' => '2026-09-10 18:15:00',
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 7,
        ]);

        $thirdPlace = $this->cupRound($category, 'third_place', 30);
        $final = $this->cupRound($category, 'final', 20);
        $semifinal = $this->cupRound($category, 'semifinal', 10);
        $secondSemifinalRound = $this->cupRound($category, 'semifinal', 1);

        $this->match($thirdPlace, $entries[0], $entries[1], [
            'scheduled_date' => '2026-09-23 20:00:00',
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 4,
            'winner_entry_id' => $entries[0]->id,
        ]);
        $this->match($final, $entries[0], $entries[1], [
            'scheduled_date' => '2026-09-22 20:00:00',
            'status' => 'cancelled',
        ]);
        $this->match($semifinal, $entries[1], $entries[0], [
            'scheduled_date' => '2026-09-21 20:00:00',
            'status' => 'postponed',
        ]);
        $this->match($secondSemifinalRound, $entries[0], $entries[1], [
            'scheduled_date' => '2026-09-24 20:00:00',
            'status' => 'scheduled',
        ]);

        $document = app(BuildCategoryCompetitionExportDocumentService::class)->build(
            $category,
            CarbonImmutable::parse('2026-09-05 12:00:00')
        );

        $this->assertSame('Temporada 2026', $document->seasonName);
        $this->assertSame('Campionat de la Ribera', $document->championshipName);
        $this->assertSame('Primera Categoria', $document->categoryName);
        $this->assertSame('Individual', $document->modalityLabel);
        $this->assertSame(2, $document->participantCount);
        $this->assertSame(['Alba', 'Bernat'], $document->participants);

        $this->assertSame(
            ['Jornada 1', 'Jornada 1', 'Jornada 2'],
            array_column($document->leagueMatches, 'groupLabel')
        );
        $this->assertSame(
            ['10/09/2026', null, '14/09/2026'],
            array_column($document->leagueMatches, 'date')
        );
        $this->assertSame(['18:15', null, '19:45'], array_column($document->leagueMatches, 'time'));
        $this->assertSame(
            ['Pista Central', 'Pista Central', null],
            array_column($document->leagueMatches, 'venue')
        );
        $this->assertSame([null, null, null], array_column($document->leagueMatches, 'resultText'));

        $this->assertSame(
            ['Semifinal', 'Semifinal', 'Final', '3.º/4.º puesto'],
            array_column($document->cupMatches, 'groupLabel')
        );
        $this->assertSame(
            ['Aplazado', null, 'Cancelado', '10-4'],
            array_column($document->cupMatches, 'resultText')
        );

        $this->assertDatabaseHas('game_matches', [
            'round_id' => $normalizedLeague->id,
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => null,
        ]);
    }

    public function test_builder_rejects_every_incoherent_validated_result_without_mutating_it(): void
    {
        $cases = [
            ['invalid score', 9, 4, 'home'],
            ['missing winner', 10, 4, null],
            ['wrong winner', 10, 4, 'away'],
        ];

        foreach ($cases as [$label, $homeScore, $awayScore, $winner]) {
            [$category, $entries, $round] = $this->competitionBranch();
            $winnerId = match ($winner) {
                'home' => $entries[0]->id,
                'away' => $entries[1]->id,
                default => null,
            };
            $match = $this->match($round, $entries[0], $entries[1], [
                'status' => 'validated',
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'winner_entry_id' => $winnerId,
            ]);

            $this->assertExportException(
                fn () => app(BuildCategoryCompetitionExportDocumentService::class)->build($category),
                CategoryCompetitionExportException::INVALID_RESULT,
                $label
            );

            $match->refresh();
            $this->assertSame($homeScore, $match->home_score, $label);
            $this->assertSame($awayScore, $match->away_score, $label);
            $this->assertSame($winnerId, $match->winner_entry_id, $label);
            $this->assertSame('validated', $match->status->value, $label);
        }
    }

    public function test_builder_rejects_ambiguous_rounds_and_non_approved_match_participants(): void
    {
        [$leagueCategory, $leagueEntries] = $this->competitionBranch(createRound: false);
        $ambiguousLeague = Round::factory()->create([
            'category_id' => $leagueCategory->id,
            'order' => 1,
            'type' => 'league',
            'phase' => 'cup',
            'stage' => 'matchday',
        ]);
        $this->match($ambiguousLeague, $leagueEntries[0], $leagueEntries[1]);
        $this->assertExportException(
            fn () => app(BuildCategoryCompetitionExportDocumentService::class)->build($leagueCategory),
            CategoryCompetitionExportException::AMBIGUOUS_STRUCTURE
        );

        [$cupCategory, $cupEntries] = $this->competitionBranch(createRound: false);
        $invalidCup = Round::factory()->create([
            'category_id' => $cupCategory->id,
            'order' => 1,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'quarterfinal',
        ]);
        $this->match($invalidCup, $cupEntries[0], $cupEntries[1]);
        $this->assertExportException(
            fn () => app(BuildCategoryCompetitionExportDocumentService::class)->build($cupCategory),
            CategoryCompetitionExportException::AMBIGUOUS_STRUCTURE
        );

        [$participantCategory, $participantEntries, $round] = $this->competitionBranch();
        $pending = $this->playerEntry($participantCategory, 'Privado', [
            'status' => 'pending',
            'user_name' => 'Nombre privado',
            'user_lastname' => 'Apellido privado',
        ]);
        $this->match($round, $participantEntries[0], $pending);
        $this->assertExportException(
            fn () => app(BuildCategoryCompetitionExportDocumentService::class)->build($participantCategory),
            CategoryCompetitionExportException::INVALID_PARTICIPANTS
        );
    }

    public function test_builder_uses_public_identity_for_adults_minors_and_teams_at_one_as_of(): void
    {
        $asOf = CarbonImmutable::parse('2026-08-06 12:00:00');
        $admin = User::factory()->admin()->create();
        [$category] = $this->competitionBranch(createEntries: false, createRound: false);

        $adult = $this->playerEntry($category, '  La   Ràpida  ', [
            'birth_date' => '1990-01-01',
            'user_name' => 'Adulto Privado',
            'user_lastname' => 'Apellido Privado',
            'email' => 'adult.private@example.test',
            'dni' => '11111111H',
        ]);
        $minorAlias = $this->playerEntry($category, 'Alias Menor', [
            'birth_date' => '2014-01-01',
            'user_name' => 'Menor Alias Privado',
            'user_lastname' => 'Apellido Alias Privado',
        ]);
        $minorName = $this->playerEntry($category, null, [
            'birth_date' => '2014-02-01',
            'user_name' => 'Biel',
            'user_lastname' => 'Casanova Privado',
        ]);
        $minorAnonymous = $this->playerEntry($category, 'Alias no publicable', [
            'birth_date' => '2014-03-01',
            'user_name' => 'Anónimo Privado',
            'user_lastname' => 'Apellido Anónimo',
        ]);
        $minorRevoked = $this->playerEntry($category, 'Alias Revocado', [
            'birth_date' => '2014-04-01',
            'user_name' => 'Revocado Privado',
            'user_lastname' => 'Apellido Revocado',
        ]);
        $minorExpired = $this->playerEntry($category, 'Alias Caducado', [
            'birth_date' => '2014-05-01',
            'user_name' => 'Caducado Privado',
            'user_lastname' => 'Apellido Caducado',
        ]);

        $this->authorization($minorAlias->player, $admin, 'alias');
        $this->authorization($minorName->player, $admin, 'name_initial');
        PublicIdentityAuthorization::factory()->create([
            'player_id' => $minorAnonymous->player_id,
            'mode' => 'anonymous',
            'state' => 'denied',
            'approval_slot' => null,
        ]);
        PublicIdentityAuthorization::factory()->revoked()->create([
            'player_id' => $minorRevoked->player_id,
            'reviewed_by' => $admin->id,
            'revoked_by' => $admin->id,
            'mode' => 'alias',
        ]);
        $this->authorization($minorExpired->player, $admin, 'alias', [
            'expires_at' => '2026-08-06 11:59:59',
        ]);

        $round = Round::factory()->create([
            'category_id' => $category->id,
            'order' => 1,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);
        $entries = [$adult, $minorAlias, $minorName, $minorAnonymous, $minorRevoked, $minorExpired];
        for ($index = 0; $index < count($entries); $index += 2) {
            $this->match($round, $entries[$index], $entries[$index + 1]);
        }

        $document = app(BuildCategoryCompetitionExportDocumentService::class)->build($category, $asOf);

        $this->assertSame([
            'La Ràpida',
            'Alias Menor',
            'Biel C.',
            'Participante',
            'Participante',
            'Participante',
        ], $document->participants);
        $serialized = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        foreach ([
            'Adulto Privado',
            'adult.private@example.test',
            '11111111H',
            'Menor Alias Privado',
            'Anónimo Privado',
            'Revocado Privado',
            'Caducado Privado',
        ] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $serialized);
        }

        [$teamCategory] = $this->competitionBranch(
            type: 'doubles',
            createEntries: false,
            createRound: false
        );
        $team = Team::factory()->create([
            'category_id' => $teamCategory->id,
            'name' => '  Equip   Roig  ',
        ]);
        $opponent = Team::factory()->create([
            'category_id' => $teamCategory->id,
            'name' => 'Equip Blau',
        ]);
        $privateMember = $this->player('Membre privat', [
            'user_name' => 'Membre Secret',
            'user_lastname' => 'No Publicar',
            'email' => 'member.private@example.test',
        ]);
        $team->players()->attach($privateMember->id, ['role_in_team' => 'front']);
        $teamEntries = [
            CategoryEntry::factory()->teamEntry()->create([
                'category_id' => $teamCategory->id,
                'team_id' => $team->id,
                'status' => 'approved',
            ]),
            CategoryEntry::factory()->teamEntry()->create([
                'category_id' => $teamCategory->id,
                'team_id' => $opponent->id,
                'status' => 'approved',
            ]),
        ];
        $teamRound = Round::factory()->create([
            'category_id' => $teamCategory->id,
            'order' => 1,
            'type' => 'league',
            'phase' => null,
            'stage' => null,
        ]);
        $this->match($teamRound, $teamEntries[0], $teamEntries[1]);

        $teamDocument = app(BuildCategoryCompetitionExportDocumentService::class)
            ->build($teamCategory, $asOf);

        $this->assertSame('Dobles', $teamDocument->modalityLabel);
        $this->assertSame(['Equip Roig', 'Equip Blau'], $teamDocument->participants);
        $this->assertStringNotContainsString(
            'Membre Secret',
            json_encode($teamDocument, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_reference_fixture_builds_all_ten_participants_and_forty_nine_matches_on_one_page(): void
    {
        $category = $this->referenceCategoryFixture();
        $document = app(BuildCategoryCompetitionExportDocumentService::class)->build(
            $category,
            CarbonImmutable::parse('2026-09-05 12:00:00')
        );

        $this->assertSame(10, $document->participantCount);
        $this->assertCount(10, $document->participants);
        $this->assertCount(45, $document->leagueMatches);
        $this->assertCount(4, $document->cupMatches);

        $rendered = app(RenderCategoryCompetitionPdfService::class)->render($document);

        $this->assertSame(1, $rendered->pageCount);
        $this->assertContains($rendered->preset, ['standard', 'compact', 'dense']);
        $this->assertStringStartsWith('%PDF-', $rendered->bytes);

        $referencePath = getenv('COMPETITION_EXPORT_REFERENCE_PATH');
        if (is_string($referencePath) && $referencePath !== '') {
            File::ensureDirectoryExists(dirname($referencePath), 0750, true);
            File::put($referencePath, $rendered->bytes);

            $metrics = [];
            for ($iteration = 1; $iteration <= 3; $iteration++) {
                gc_collect_cycles();
                $startedAt = hrtime(true);
                $benchmarkRender = app(RenderCategoryCompetitionPdfService::class)->render($document);
                $metrics[] = [
                    'iteration' => $iteration,
                    'wall_seconds' => (hrtime(true) - $startedAt) / 1_000_000_000,
                    'peak_memory_bytes' => memory_get_peak_usage(true),
                    'pdf_bytes' => strlen($benchmarkRender->bytes),
                    'preset' => $benchmarkRender->preset,
                    'page_count' => $benchmarkRender->pageCount,
                ];
            }

            $metricsPath = getenv('COMPETITION_EXPORT_METRICS_PATH');
            if (is_string($metricsPath) && $metricsPath !== '') {
                File::ensureDirectoryExists(dirname($metricsPath), 0750, true);
                File::put(
                    $metricsPath,
                    json_encode($metrics, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL
                );
            }
        }
    }

    public function referenceCategoryFixture(): Category
    {
        [$category] = $this->competitionBranch(createEntries: false, createRound: false);
        $names = [
            'Alba la Ràpida',
            'Bernat del Túria',
            'Carme Pilota Viva',
            'Dídac el Restador',
            'Elena de l Horta',
            'Ferran el Feridor',
            'Gemma de la Ribera',
            'Hugo el Mitger',
            'Irene Pilota Forta',
            'Jordi del Trinquet',
        ];
        $entries = array_map(
            fn (string $name): CategoryEntry => $this->playerEntry($category, $name),
            $names
        );
        $venues = [
            Venue::factory()->create(['name' => 'Trinquet Municipal']),
            Venue::factory()->create(['name' => 'Pista de la Ribera']),
        ];
        $rounds = [];
        for ($number = 1; $number <= 9; $number++) {
            $rounds[] = Round::factory()->create([
                'category_id' => $category->id,
                'name' => 'Jornada '.$number,
                'order' => $number,
                'type' => 'league',
                'phase' => $number % 2 === 0 ? null : 'league',
                'stage' => $number % 2 === 0 ? null : 'matchday',
            ]);
        }

        $matchIndex = 0;
        for ($home = 0; $home < count($entries); $home++) {
            for ($away = $home + 1; $away < count($entries); $away++) {
                $attributes = $this->referenceMatchAttributes(
                    $matchIndex,
                    $entries[$home],
                    $venues[$matchIndex % count($venues)]
                );
                $this->match(
                    $rounds[$matchIndex % count($rounds)],
                    $entries[$home],
                    $entries[$away],
                    $attributes
                );
                $matchIndex++;
            }
        }

        $cupRounds = [
            $this->cupRound($category, 'semifinal', 10),
            $this->cupRound($category, 'final', 20),
            $this->cupRound($category, 'third_place', 30),
        ];
        $cupPairs = [[0, 3], [1, 2], [0, 1], [2, 3]];
        foreach ($cupPairs as $index => [$home, $away]) {
            $round = $cupRounds[$index < 2 ? 0 : $index - 1];
            $this->match($round, $entries[$home], $entries[$away], [
                'venue_id' => $venues[$index % 2]->id,
                'scheduled_date' => CarbonImmutable::parse('2026-10-20 19:00:00')
                    ->addDays($index)
                    ->format('Y-m-d H:i:s'),
                'status' => $index === 2 ? 'validated' : 'scheduled',
                'home_score' => $index === 2 ? 10 : null,
                'away_score' => $index === 2 ? 6 : null,
                'winner_entry_id' => $index === 2 ? $entries[$home]->id : null,
            ]);
        }

        return $category;
    }

    /**
     * @return array{0: Category, 1: list<CategoryEntry>, 2?: Round}
     */
    private function competitionBranch(
        string $type = 'singles',
        bool $createEntries = true,
        bool $createRound = true,
    ): array {
        $season = Season::factory()->create(['name' => 'Temporada 2026']);
        $championship = Championship::factory()->create([
            'season_id' => $season->id,
            'name' => 'Campionat de la Ribera',
            'type' => $type,
        ]);
        $category = Category::factory()->create([
            'championship_id' => $championship->id,
            'name' => 'Primera Categoria',
        ]);
        $entries = $createEntries ? [
            $this->playerEntry($category, 'Alba'),
            $this->playerEntry($category, 'Bernat'),
        ] : [];

        if (! $createRound) {
            return [$category, $entries];
        }

        $round = Round::factory()->create([
            'category_id' => $category->id,
            'name' => 'Jornada 1',
            'order' => 1,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);

        return [$category, $entries, $round];
    }

    /** @param array<string, mixed> $attributes */
    private function playerEntry(Category $category, ?string $nickname, array $attributes = []): CategoryEntry
    {
        $player = $this->player($nickname, $attributes);

        return CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => $attributes['status'] ?? 'approved',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function player(?string $nickname, array $attributes = []): Player
    {
        $user = User::factory()->create([
            'name' => $attributes['user_name'] ?? 'Nombre',
            'lastname' => $attributes['user_lastname'] ?? 'Apellido',
            'email' => $attributes['email'] ?? fake()->unique()->safeEmail(),
        ]);

        return Player::factory()->create([
            'user_id' => $user->id,
            'nickname' => $nickname,
            'birth_date' => $attributes['birth_date'] ?? '1990-01-01',
            'dni' => $attributes['dni'] ?? null,
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function match(
        Round $round,
        CategoryEntry $home,
        CategoryEntry $away,
        array $attributes = [],
    ): GameMatch {
        return GameMatch::factory()->create(array_merge([
            'round_id' => $round->id,
            'venue_id' => null,
            'home_entry_id' => $home->id,
            'away_entry_id' => $away->id,
            'scheduled_date' => '2026-09-10 18:00:00',
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
        ], $attributes));
    }

    private function cupRound(Category $category, string $stage, int $order): Round
    {
        return Round::factory()->create([
            'category_id' => $category->id,
            'name' => 'Nombre no canónico',
            'order' => $order,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => $stage,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function authorization(
        Player $player,
        User $admin,
        string $mode,
        array $attributes = [],
    ): PublicIdentityAuthorization {
        return PublicIdentityAuthorization::factory()->approved()->create(array_merge([
            'player_id' => $player->id,
            'reviewed_by' => $admin->id,
            'mode' => $mode,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function referenceMatchAttributes(
        int $index,
        CategoryEntry $home,
        Venue $venue,
    ): array {
        $status = ['scheduled', 'submitted', 'under_review', 'postponed', 'cancelled', 'validated'][$index % 6];
        $hasSubmittedScore = in_array($status, ['submitted', 'under_review', 'validated'], true);

        return [
            'venue_id' => $venue->id,
            'scheduled_date' => CarbonImmutable::parse('2026-09-01 18:00:00')
                ->addHours($index * 3)
                ->format('Y-m-d H:i:s'),
            'status' => $status,
            'home_score' => $hasSubmittedScore ? 10 : null,
            'away_score' => $hasSubmittedScore ? ($index % 8) + 1 : null,
            'winner_entry_id' => $status === 'validated' ? $home->id : null,
        ];
    }

    private function assertExportException(
        callable $callback,
        string $expectedMessage,
        string $context = '',
    ): void {
        try {
            $callback();
            $this->fail('Se esperaba una excepción de exportación. '.$context);
        } catch (CategoryCompetitionExportException $exception) {
            $this->assertSame($expectedMessage, $exception->getMessage(), $context);
        }
    }
}
