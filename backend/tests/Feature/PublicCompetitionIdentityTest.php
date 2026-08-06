<?php

namespace Tests\Feature;

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
use App\Services\PublicPlayerIdentityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCompetitionIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_adult_identity_uses_normalized_alias_or_given_names_and_surname_initial(): void
    {
        $service = app(PublicPlayerIdentityService::class);
        $referenceDate = CarbonImmutable::parse('2026-08-05');

        $withAlias = $this->player([
            'name' => 'Nombre Privado',
            'lastname' => 'Apellido Privado',
        ], [
            'nickname' => '  La   Ràpida  ',
            'birth_date' => '1990-02-10',
        ]);
        $withoutAlias = $this->player([
            'name' => '  María   del Mar ',
            'lastname' => ' de la   Cruz ',
        ], [
            'nickname' => '   ',
            'birth_date' => '1988-06-11',
        ]);

        $this->assertSame('La Ràpida', $service->displayName($withAlias, $referenceDate));
        $this->assertSame('María del Mar D.', $service->displayName($withoutAlias, $referenceDate));
    }

    public function test_identity_fails_closed_for_minors_missing_birth_date_or_incomplete_names(): void
    {
        $service = app(PublicPlayerIdentityService::class);
        $referenceDate = CarbonImmutable::parse('2026-08-05');

        $minor = $this->player([
            'name' => 'Menor',
            'lastname' => 'Identificable',
        ], [
            'nickname' => 'Alias menor',
            'birth_date' => '2012-05-04',
        ]);
        $unknownAge = $this->player([
            'name' => 'Edad',
            'lastname' => 'Desconocida',
        ], [
            'nickname' => 'Alias sin edad',
            'birth_date' => null,
        ]);
        $missingName = $this->player([
            'name' => '   ',
            'lastname' => 'Apellido',
        ], [
            'nickname' => null,
            'birth_date' => '1980-01-01',
        ]);

        $this->assertSame('Participante', $service->displayName($minor, $referenceDate));
        $this->assertSame('Participante', $service->displayName($unknownAge, $referenceDate));
        $this->assertSame('Participante', $service->displayName($missingName, $referenceDate));
    }

    public function test_public_player_endpoints_use_closed_allowlists_without_private_fields(): void
    {
        [$season, $championship, $category, $round] = $this->publicBranch();
        $homePlayer = $this->player([
            'name' => 'Aina',
            'lastname' => 'Martínez Serrano',
            'email' => 'aina.private@example.test',
        ], [
            'nickname' => null,
            'birth_date' => '1990-05-03',
            'dni' => '11111111H',
            'notes' => 'Dato privado de prueba',
        ]);
        $awayPlayer = $this->player([
            'name' => 'Biel',
            'lastname' => 'Pérez Gómez',
            'email' => 'biel.private@example.test',
        ], [
            'nickname' => 'El Blau',
            'birth_date' => '1989-08-14',
        ]);
        $homeEntry = $this->playerEntry($category, $homePlayer);
        $awayEntry = $this->playerEntry($category, $awayPlayer);
        $match = $this->validatedMatch($round, $homeEntry, $awayEntry);

        Model::preventLazyLoading();

        try {
            $matchResponse = $this->getJson('/api/v1/matches/'.$match->id)->assertOk();
            $scheduleResponse = $this->getJson('/api/v1/categories/'.$category->id.'/schedule')->assertOk();
            $standingsResponse = $this->getJson('/api/v1/categories/'.$category->id.'/standings')->assertOk();
            $championshipResponse = $this->getJson(
                '/api/v1/championships/'.$championship->id.'/ranking'
            )->assertOk();
            $seasonResponse = $this->getJson('/api/v1/seasons/'.$season->id.'/ranking')->assertOk();
            $allTimeResponse = $this->getJson('/api/v1/rankings/all-time')->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }

        $matchResponse
            ->assertJsonPath('data.home_entry.public_display_name', 'Aina M.')
            ->assertJsonPath('data.away_entry.public_display_name', 'El Blau')
            ->assertJsonMissingPath('data.home_entry.id')
            ->assertJsonMissingPath('data.home_entry.player')
            ->assertJsonMissingPath('data.home_entry.player_id')
            ->assertJsonMissingPath('data.home_entry.team_id')
            ->assertJsonMissingPath('data.home_entry_id')
            ->assertJsonMissingPath('data.winner_entry_id');
        $this->assertSame(
            ['entry_type', 'public_display_name'],
            array_keys($matchResponse->json('data.home_entry'))
        );

        $scheduleResponse
            ->assertJsonPath('data.0.matches.0.home_entry.public_display_name', 'Aina M.')
            ->assertJsonMissingPath('data.0.matches.0.home_entry.player');
        $standingsResponse
            ->assertJsonPath('data.0.public_display_name', 'Aina M.')
            ->assertJsonMissingPath('data.0.entry_id')
            ->assertJsonMissingPath('data.0.entry')
            ->assertJsonMissingPath('data.0.name');

        foreach ([$championshipResponse, $seasonResponse, $allTimeResponse] as $rankingResponse) {
            $rankingResponse
                ->assertJsonPath('data.0.public_display_name', 'Aina M.')
                ->assertJsonMissingPath('data.0.player_id')
                ->assertJsonMissingPath('data.0.player')
                ->assertJsonMissingPath('data.0.name');
        }

        foreach ([
            $matchResponse,
            $scheduleResponse,
            $standingsResponse,
            $championshipResponse,
            $seasonResponse,
            $allTimeResponse,
        ] as $response) {
            $content = $response->getContent();
            $this->assertStringNotContainsString('Martínez Serrano', $content);
            $this->assertStringNotContainsString('aina.private@example.test', $content);
            $this->assertStringNotContainsString('11111111H', $content);
            $this->assertStringNotContainsString('Dato privado de prueba', $content);
            $this->assertStringNotContainsString('1990-05-03', $content);
        }
    }

    public function test_public_team_entry_exposes_only_type_and_team_name(): void
    {
        [, , $category, $round] = $this->publicBranch();
        $homeTeam = Team::factory()->create([
            'category_id' => $category->id,
            'name' => '  Equip   Roig  ',
        ]);
        $awayTeam = Team::factory()->create([
            'category_id' => $category->id,
            'name' => 'Equip Blau',
        ]);
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
        $match = $this->validatedMatch($round, $homeEntry, $awayEntry);

        $response = $this->getJson('/api/v1/matches/'.$match->id)
            ->assertOk()
            ->assertJsonPath('data.home_entry.entry_type', 'team')
            ->assertJsonPath('data.home_entry.public_display_name', 'Equip Roig')
            ->assertJsonMissingPath('data.home_entry.team')
            ->assertJsonMissingPath('data.home_entry.id');

        $this->assertSame(
            ['entry_type', 'public_display_name'],
            array_keys($response->json('data.home_entry'))
        );
    }

    public function test_effective_minor_authorization_applies_to_every_public_competition_projection_and_revocation(): void
    {
        config(['public_identity.authorization_enabled' => true]);
        CarbonImmutable::setTestNow('2026-08-06 12:00:00');
        [$season, $championship, $category, $round] = $this->publicBranch();
        $minor = $this->player([
            'name' => 'Nom Menor',
            'lastname' => 'Privat',
        ], [
            'nickname' => 'Alias Menor',
            'birth_date' => '2014-08-07',
        ]);
        $opponent = $this->player([
            'name' => 'Persona',
            'lastname' => 'Adulta',
        ], [
            'nickname' => 'Rival',
            'birth_date' => '1990-01-01',
        ]);
        $admin = User::factory()->admin()->create();
        $authorization = PublicIdentityAuthorization::factory()->approved()->create([
            'player_id' => $minor->id,
            'reviewed_by' => $admin->id,
            'mode' => 'alias',
        ]);
        $homeEntry = $this->playerEntry($category, $minor);
        $awayEntry = $this->playerEntry($category, $opponent);
        $match = $this->validatedMatch($round, $homeEntry, $awayEntry);

        Model::preventLazyLoading();
        try {
            $matchResponse = $this->getJson('/api/v1/matches/'.$match->id)->assertOk();
            $scheduleResponse = $this->getJson('/api/v1/categories/'.$category->id.'/schedule')->assertOk();
            $standingsResponse = $this->getJson('/api/v1/categories/'.$category->id.'/standings')->assertOk();
            $championshipResponse = $this->getJson('/api/v1/championships/'.$championship->id.'/ranking')->assertOk();
            $seasonResponse = $this->getJson('/api/v1/seasons/'.$season->id.'/ranking')->assertOk();
            $allTimeResponse = $this->getJson('/api/v1/rankings/all-time')->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }

        $matchResponse->assertJsonPath('data.home_entry.public_display_name', 'Alias Menor');
        $scheduleResponse->assertJsonPath('data.0.matches.0.home_entry.public_display_name', 'Alias Menor');
        foreach ([$standingsResponse, $championshipResponse, $seasonResponse, $allTimeResponse] as $response) {
            $this->assertContains('Alias Menor', collect($response->json('data'))->pluck('public_display_name'));
            $this->assertStringNotContainsString('Nom Menor', $response->getContent());
            $this->assertStringNotContainsString('Privat', $response->getContent());
        }

        $authorization->update([
            'state' => 'revoked',
            'approval_slot' => null,
            'revoked_at' => CarbonImmutable::now(),
            'revoked_by' => $admin->id,
        ]);

        $this->getJson('/api/v1/matches/'.$match->id)
            ->assertOk()
            ->assertJsonPath('data.home_entry.public_display_name', 'Participante');
    }

    public function test_authenticated_and_admin_identity_contracts_remain_available(): void
    {
        $player = $this->player([
            'name' => 'Nom Privat',
            'lastname' => 'Cognom Privat',
            'email' => 'private.contract@example.test',
        ], [
            'nickname' => 'Alias privat',
            'birth_date' => '1990-01-01',
            'dni' => '22222222J',
        ]);
        $token = $player->user->createToken('identity-private-contract')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'private.contract@example.test')
            ->assertJsonPath('data.player.birth_date', '1990-01-01')
            ->assertJsonPath('data.player.dni', '22222222J')
            ->assertJsonPath('data.player.full_name', 'Nom Privat Cognom Privat');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.players.show', $player))
            ->assertOk()
            ->assertSee('Nom Privat')
            ->assertSee('Cognom Privat');
    }

    public function test_authorized_minor_modes_never_fallback_and_adulthood_uses_adult_policy(): void
    {
        config(['public_identity.authorization_enabled' => true]);
        $service = app(PublicPlayerIdentityService::class);
        $admin = User::factory()->admin()->create();

        $withoutAlias = $this->player([
            'name' => 'Aina',
            'lastname' => 'Àlvarez Privat',
        ], [
            'nickname' => ' ',
            'birth_date' => '2014-08-07',
        ]);
        PublicIdentityAuthorization::factory()->approved()->create([
            'player_id' => $withoutAlias->id,
            'reviewed_by' => $admin->id,
            'mode' => 'alias',
        ]);

        $withoutLastname = $this->player([
            'name' => 'Biel',
            'lastname' => ' ',
        ], [
            'nickname' => 'Alias no utilizable por este modo',
            'birth_date' => '2014-08-07',
        ]);
        PublicIdentityAuthorization::factory()->approved()->create([
            'player_id' => $withoutLastname->id,
            'reviewed_by' => $admin->id,
            'mode' => 'name_initial',
        ]);

        $becomesAdult = $this->player([
            'name' => 'Noa',
            'lastname' => 'Écija Privat',
        ], [
            'nickname' => ' ',
            'birth_date' => '2008-08-07',
        ]);
        PublicIdentityAuthorization::factory()->approved()->create([
            'player_id' => $becomesAdult->id,
            'reviewed_by' => $admin->id,
            'minor_assent_recorded_at' => '2026-08-06 10:00:00',
            'minor_assent_recorded_by' => $admin->id,
            'mode' => 'alias',
        ]);
        $revokedAsMinor = $this->player([
            'name' => 'Iu',
            'lastname' => 'Revocat Privat',
        ], [
            'nickname' => 'Alias Adult',
            'birth_date' => '2008-08-07',
        ]);
        PublicIdentityAuthorization::factory()->revoked()->create([
            'player_id' => $revokedAsMinor->id,
            'reviewed_by' => $admin->id,
            'revoked_by' => $admin->id,
            'mode' => 'name_initial',
        ]);

        $this->assertSame(
            'Participante',
            $service->displayName(
                $withoutAlias->load('publicIdentityAuthorizations'),
                CarbonImmutable::parse('2026-08-06')
            )
        );
        $this->assertSame(
            'Participante',
            $service->displayName(
                $withoutLastname->load('publicIdentityAuthorizations'),
                CarbonImmutable::parse('2026-08-06')
            )
        );
        $this->assertSame(
            'Participante',
            $service->displayName(
                $becomesAdult->load('publicIdentityAuthorizations'),
                CarbonImmutable::parse('2026-08-06')
            )
        );
        $this->assertSame(
            'Noa É.',
            $service->displayName(
                $becomesAdult->load('publicIdentityAuthorizations'),
                CarbonImmutable::parse('2026-08-08')
            )
        );
        $this->assertSame(
            'Participante',
            $service->displayName(
                $revokedAsMinor->load('publicIdentityAuthorizations'),
                CarbonImmutable::parse('2026-08-06')
            )
        );
        $this->assertSame(
            'Alias Adult',
            $service->displayName(
                $revokedAsMinor->load('publicIdentityAuthorizations'),
                CarbonImmutable::parse('2026-08-07')
            )
        );

        [, , $category, $round] = $this->publicBranch();
        $opponent = $this->player([
            'name' => 'Persona',
            'lastname' => 'Adulta',
        ], [
            'nickname' => 'Rival',
            'birth_date' => '1990-01-01',
        ]);
        $match = $this->validatedMatch(
            $round,
            $this->playerEntry($category, $becomesAdult),
            $this->playerEntry($category, $opponent)
        );
        CarbonImmutable::setTestNow('2026-08-08 10:00:00');
        $response = $this->getJson('/api/v1/matches/'.$match->id)
            ->assertOk()
            ->assertJsonPath('data.home_entry.public_display_name', 'Noa É.')
            ->assertJsonMissingPath('data.home_entry.birth_date');
        $this->assertStringNotContainsString('2008-08-07', $response->getContent());
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $playerAttributes
     */
    private function player(array $userAttributes, array $playerAttributes): Player
    {
        $user = User::factory()->create($userAttributes);

        return Player::factory()->create([
            'user_id' => $user->id,
            ...$playerAttributes,
        ])->load('user');
    }

    /**
     * @return array{Season, Championship, Category, Round}
     */
    private function publicBranch(): array
    {
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
        ]);
        $category = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);
        $round = Round::factory()->create([
            'category_id' => $category->id,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);

        return [$season, $championship, $category, $round];
    }

    private function playerEntry(Category $category, Player $player): CategoryEntry
    {
        return CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]);
    }

    private function validatedMatch(
        Round $round,
        CategoryEntry $homeEntry,
        CategoryEntry $awayEntry
    ): GameMatch {
        return GameMatch::factory()->create([
            'round_id' => $round->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'winner_entry_id' => $homeEntry->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 7,
        ]);
    }
}
