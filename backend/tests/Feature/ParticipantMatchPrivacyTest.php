<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\MatchResultReport;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ParticipantMatchPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const REPORTER_EMAIL = 'rival-reporter@example.test';

    private const OWNER_EMAIL = 'panel-owner@example.test';

    private const SENSITIVE_COMMENT = 'Comentario confidencial del rival';

    /**
     * Campos que el legacy MatchResource exponia y que el contrato de
     * participante ya no debe alcanzar.
     */
    private const FORBIDDEN_MATCH_PATHS = [
        'result_reports',
        'submitted_by',
        'validated_by',
        'submitted_by_user',
        'validated_by_user',
        'round_id',
        'venue_id',
        'home_entry_id',
        'away_entry_id',
        'winner_entry_id',
        'created_at',
        'updated_at',
        'home_entry.player_id',
        'home_entry.team_id',
        'away_entry.player_id',
        'away_entry.team_id',
        'round.order',
        'round.category.slug',
        'round.category.level',
        'round.category.gender',
        'round.category.status',
        'round.category.championship.slug',
        'round.category.championship.type',
        'round.category.championship.season.status',
    ];

    public function test_my_matches_returns_the_participant_allowlist_only(): void
    {
        [$match, $owner] = $this->createSinglesMatch();

        $response = $this->actingAs($owner->user)->getJson('/api/v1/me/matches');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.status', 'scheduled')
            ->assertJsonPath('data.0.home_entry.entry_type', 'player')
            ->assertJsonPath('data.0.home_entry.public_display_name', 'Local')
            ->assertJsonPath('data.0.away_entry.public_display_name', 'Rival')
            ->assertJsonPath('data.0.venue.name', 'Pista Central')
            ->assertJsonPath('data.0.round.category.championship.season.name', 'Temporada E2E');

        $this->assertEqualsCanonicalizing([
            'id',
            'scheduled_date',
            'status',
            'home_score',
            'away_score',
            'home_entry',
            'away_entry',
            'winner_entry',
            'venue',
            'round',
        ], array_keys($response->json('data.0')));

        $this->assertEqualsCanonicalizing([
            'id',
            'entry_type',
            'public_display_name',
            'player',
            'team',
        ], array_keys($response->json('data.0.home_entry')));

        $this->assertForbiddenPathsAreAbsent($response, 'data.0');
    }

    public function test_my_matches_never_exposes_reporter_email_reports_or_comments(): void
    {
        [$match, $owner, $rival] = $this->createSinglesMatch();
        $this->createReport($match, $rival, 'away', self::SENSITIVE_COMMENT);

        $response = $this->actingAs($owner->user)
            ->getJson('/api/v1/me/matches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.result_reports');

        $content = $response->getContent();

        $this->assertStringNotContainsString(self::REPORTER_EMAIL, $content);
        $this->assertStringNotContainsString(self::OWNER_EMAIL, $content);
        $this->assertStringNotContainsString(self::SENSITIVE_COMMENT, $content);
        $this->assertStringNotContainsString('@example.test', $content);

        $this->assertDatabaseHas('match_result_reports', [
            'game_match_id' => $match->id,
            'player_id' => $rival->id,
            'comment' => self::SENSITIVE_COMMENT,
        ]);
    }

    public function test_my_matches_never_exposes_submitted_or_validated_actor_traceability(): void
    {
        [$match, $owner, $rival] = $this->createSinglesMatch();
        $validator = User::factory()->create(['email' => 'validator@example.test']);
        $match->update([
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $match->home_entry_id,
            'submitted_by' => $rival->user_id,
            'validated_by' => $validator->id,
        ]);

        $response = $this->actingAs($owner->user)
            ->getJson('/api/v1/me/matches')
            ->assertOk()
            ->assertJsonMissingPath('data.0.submitted_by')
            ->assertJsonMissingPath('data.0.validated_by')
            ->assertJsonMissingPath('data.0.submitted_by_user')
            ->assertJsonMissingPath('data.0.validated_by_user');

        $this->assertStringNotContainsString('validator@example.test', $response->getContent());

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'submitted_by' => $rival->user_id,
            'validated_by' => $validator->id,
        ]);
    }

    public function test_my_matches_hides_stored_scores_and_winner_until_the_match_is_validated(): void
    {
        [$match, $owner] = $this->createSinglesMatch();
        $match->update([
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $match->home_entry_id,
        ]);

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 7,
        ]);

        $this->actingAs($owner->user)
            ->getJson('/api/v1/me/matches')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'submitted')
            ->assertJsonPath('data.0.home_score', null)
            ->assertJsonPath('data.0.away_score', null)
            ->assertJsonPath('data.0.winner_entry', null)
            ->assertJsonMissingPath('data.0.winner_entry_id');
    }

    public function test_my_matches_exposes_validated_scores_and_winner_without_internal_keys(): void
    {
        [$match, $owner] = $this->createSinglesMatch();
        $match->update([
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $match->home_entry_id,
        ]);

        $this->actingAs($owner->user)
            ->getJson('/api/v1/me/matches')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'validated')
            ->assertJsonPath('data.0.home_score', 10)
            ->assertJsonPath('data.0.away_score', 7)
            ->assertJsonPath('data.0.winner_entry.id', $match->home_entry_id)
            ->assertJsonPath('data.0.winner_entry.entry_type', 'player')
            ->assertJsonPath('data.0.winner_entry.public_display_name', 'Local')
            ->assertJsonMissingPath('data.0.winner_entry_id')
            ->assertJsonMissingPath('data.0.winner_entry.player_id');
    }

    public function test_my_matches_keeps_doubles_team_identity_without_reports_or_emails(): void
    {
        [$match, $owner, $teammate, $rival] = $this->createDoublesMatch();
        $this->createReport($match, $rival, 'away', self::SENSITIVE_COMMENT);

        $response = $this->actingAs($owner->user)
            ->getJson('/api/v1/me/matches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.home_entry.entry_type', 'team')
            ->assertJsonPath('data.0.home_entry.team.name', 'Equipo Local')
            ->assertJsonPath('data.0.home_entry.public_display_name', 'Equipo Local')
            ->assertJsonCount(2, 'data.0.home_entry.team.players')
            ->assertJsonMissingPath('data.0.result_reports')
            ->assertJsonMissingPath('data.0.home_entry.team.players.0.email');

        $content = $response->getContent();

        $this->assertStringNotContainsString(self::SENSITIVE_COMMENT, $content);
        $this->assertStringNotContainsString('@example.test', $content);
        $this->assertStringNotContainsString($teammate->user->email, $content);

        $this->assertForbiddenPathsAreAbsent($response, 'data.0');
    }

    public function test_my_matches_still_isolates_unrelated_players(): void
    {
        [$match, $owner] = $this->createSinglesMatch();
        $outsider = $this->createPlayer('outsider@example.test', 'Ajeno', 'Externo', 'Ajeno');

        $this->actingAs($owner->user)
            ->getJson('/api/v1/me/matches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);

        $this->actingAs($outsider->user)
            ->getJson('/api/v1/me/matches')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_calendar_matches_return_the_participant_allowlist_only(): void
    {
        [$match, $owner] = $this->createSinglesMatch();

        $response = $this->actingAs($owner->user)->getJson('/api/v1/me/calendar');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-07-15')
            ->assertJsonPath('data.0.matches.0.id', $match->id)
            ->assertJsonPath('data.0.matches.0.home_entry.public_display_name', 'Local')
            ->assertJsonPath('data.0.matches.0.round.category.id', $match->round->category_id);

        $this->assertEqualsCanonicalizing([
            'id',
            'scheduled_date',
            'status',
            'home_score',
            'away_score',
            'home_entry',
            'away_entry',
            'venue',
            'round',
        ], array_keys($response->json('data.0.matches.0')));

        $this->assertForbiddenPathsAreAbsent($response, 'data.0.matches.0');
    }

    public function test_calendar_never_exposes_reporter_email_reports_or_actor_traceability(): void
    {
        [$match, $owner, $rival] = $this->createSinglesMatch();
        $this->createReport($match, $rival, 'away', self::SENSITIVE_COMMENT);
        $match->update([
            'submitted_by' => $rival->user_id,
            'validated_by' => $rival->user_id,
        ]);

        $response = $this->actingAs($owner->user)
            ->getJson('/api/v1/me/calendar')
            ->assertOk()
            ->assertJsonMissingPath('data.0.matches.0.result_reports')
            ->assertJsonMissingPath('data.0.matches.0.submitted_by')
            ->assertJsonMissingPath('data.0.matches.0.validated_by');

        $content = $response->getContent();

        $this->assertStringNotContainsString(self::REPORTER_EMAIL, $content);
        $this->assertStringNotContainsString(self::OWNER_EMAIL, $content);
        $this->assertStringNotContainsString(self::SENSITIVE_COMMENT, $content);
        $this->assertStringNotContainsString('@example.test', $content);
    }

    public function test_calendar_hides_stored_scores_until_validated_and_shows_them_afterwards(): void
    {
        [$match, $owner] = $this->createSinglesMatch();
        $match->update([
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $match->home_entry_id,
        ]);

        $this->actingAs($owner->user)
            ->getJson('/api/v1/me/calendar')
            ->assertOk()
            ->assertJsonPath('data.0.matches.0.status', 'submitted')
            ->assertJsonPath('data.0.matches.0.home_score', null)
            ->assertJsonPath('data.0.matches.0.away_score', null)
            ->assertJsonMissingPath('data.0.matches.0.winner_entry_id');

        $match->update(['status' => 'validated']);

        $this->actingAs($owner->user)
            ->getJson('/api/v1/me/calendar')
            ->assertOk()
            ->assertJsonPath('data.0.matches.0.status', 'validated')
            ->assertJsonPath('data.0.matches.0.home_score', 10)
            ->assertJsonPath('data.0.matches.0.away_score', 7);
    }

    private function assertForbiddenPathsAreAbsent(TestResponse $response, string $prefix): void
    {
        foreach (self::FORBIDDEN_MATCH_PATHS as $path) {
            $response->assertJsonMissingPath($prefix.'.'.$path);
        }
    }

    /**
     * @return array{GameMatch, Player, Player}
     */
    private function createSinglesMatch(): array
    {
        $category = $this->createCategory('singles');
        $round = $this->createRound($category);

        $owner = $this->createPlayer(self::OWNER_EMAIL, 'Jugador', 'Local', 'Local');
        $rival = $this->createPlayer(self::REPORTER_EMAIL, 'Jugador', 'Visitante', 'Rival');

        $homeEntry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $owner->id,
            'status' => 'approved',
        ]);
        $awayEntry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $rival->id,
            'status' => 'approved',
        ]);

        $match = GameMatch::factory()->create([
            'round_id' => $round->id,
            'venue_id' => Venue::factory()->create(['name' => 'Pista Central'])->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'scheduled_date' => '2026-07-15 18:30:00',
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
            'submitted_by' => null,
            'validated_by' => null,
        ]);

        return [$match, $owner, $rival];
    }

    /**
     * @return array{GameMatch, Player, Player, Player}
     */
    private function createDoublesMatch(): array
    {
        $category = $this->createCategory('doubles');
        $round = $this->createRound($category);

        $owner = $this->createPlayer(self::OWNER_EMAIL, 'Jugador', 'Local', 'Local');
        $teammate = $this->createPlayer('teammate@example.test', 'Jugador', 'Pareja', 'Pareja');
        $rival = $this->createPlayer(self::REPORTER_EMAIL, 'Jugador', 'Visitante', 'Rival');
        $rivalTwo = $this->createPlayer('rival-two@example.test', 'Jugador', 'Segundo', 'Segon');

        $homeTeam = Team::factory()->create([
            'category_id' => $category->id,
            'name' => 'Equipo Local',
        ]);
        $awayTeam = Team::factory()->create([
            'category_id' => $category->id,
            'name' => 'Equipo Visitante',
        ]);

        $homeTeam->players()->attach($owner->id, ['role_in_team' => 'front']);
        $homeTeam->players()->attach($teammate->id, ['role_in_team' => 'back']);
        $awayTeam->players()->attach($rival->id, ['role_in_team' => 'front']);
        $awayTeam->players()->attach($rivalTwo->id, ['role_in_team' => 'back']);

        $homeEntry = CategoryEntry::factory()->teamEntry()->create([
            'category_id' => $category->id,
            'team_id' => $homeTeam->id,
            'status' => 'approved',
        ]);
        $awayEntry = CategoryEntry::factory()->teamEntry()->create([
            'category_id' => $category->id,
            'team_id' => $awayTeam->id,
            'status' => 'approved',
        ]);

        $match = GameMatch::factory()->create([
            'round_id' => $round->id,
            'venue_id' => Venue::factory()->create(['name' => 'Pista Central'])->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'scheduled_date' => '2026-07-15 18:30:00',
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
            'submitted_by' => null,
            'validated_by' => null,
        ]);

        return [$match, $owner, $teammate, $rival];
    }

    private function createCategory(string $championshipType): Category
    {
        $season = Season::factory()->publiclyVisible()->create(['name' => 'Temporada E2E']);
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
            'type' => $championshipType,
        ]);

        return Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);
    }

    private function createRound(Category $category): Round
    {
        return Round::factory()->create([
            'category_id' => $category->id,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);
    }

    private function createPlayer(
        string $email,
        string $name,
        string $lastname,
        string $nickname
    ): Player {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $name,
            'lastname' => $lastname,
            'active' => true,
        ]);

        return Player::factory()->create([
            'user_id' => $user->id,
            'nickname' => $nickname,
            'birth_date' => '1990-01-01',
            'active' => true,
        ])->load('user');
    }

    private function createReport(
        GameMatch $match,
        Player $player,
        string $side,
        string $comment
    ): MatchResultReport {
        return MatchResultReport::query()->create([
            'game_match_id' => $match->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'side' => $side,
            'home_score' => 10,
            'away_score' => 7,
            'status' => 'submitted',
            'comment' => $comment,
        ]);
    }
}
