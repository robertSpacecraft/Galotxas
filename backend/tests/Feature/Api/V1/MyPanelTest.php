<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_the_current_user_and_player_profile(): void
    {
        [$user, $player] = $this->createAuthenticatedPlayer();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.active', true)
            ->assertJsonPath('data.user.has_player', true)
            ->assertJsonPath('data.player.id', $player->id)
            ->assertJsonPath('data.player.nickname', $player->nickname);
    }

    public function test_me_returns_null_player_when_user_has_no_player_profile(): void
    {
        $user = $this->createAuthenticatedUser();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.has_player', false)
            ->assertJsonPath('data.player', null);
    }

    public function test_user_can_get_their_player_profile(): void
    {
        [, $player] = $this->createAuthenticatedPlayer();

        $this->getJson('/api/v1/me/player-profile')
            ->assertOk()
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.id', $player->id)
            ->assertJsonPath('data.nickname', $player->nickname)
            ->assertJsonPath('data.user.id', $player->user_id);
    }

    public function test_get_player_profile_returns_current_error_when_user_has_no_profile(): void
    {
        $this->createAuthenticatedUser();

        $this->getJson('/api/v1/me/player-profile')
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'El usuario autenticado no tiene un perfil de jugador asociado.',
                'data' => null,
            ]);
    }

    public function test_user_can_create_their_player_profile(): void
    {
        $user = $this->createAuthenticatedUser();

        $this->postJson('/api/v1/me/player-profile', [
            'nickname' => 'Pilotari',
            'gender' => 'male',
            'level' => 6,
            'dominant_hand' => 'right',
            'notes' => 'Perfil creado desde Mi Panel.',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Perfil de jugador creado correctamente.')
            ->assertJsonPath('data.nickname', 'Pilotari')
            ->assertJsonPath('data.gender', 'male')
            ->assertJsonPath('data.level', 6)
            ->assertJsonPath('data.dominant_hand', 'right')
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('players', [
            'user_id' => $user->id,
            'nickname' => 'Pilotari',
            'level' => 6,
            'active' => true,
        ]);
    }

    public function test_user_can_update_their_player_profile(): void
    {
        [, $player] = $this->createAuthenticatedPlayer();

        $this->patchJson('/api/v1/me/player-profile', [
            'nickname' => 'Nou Apodo',
            'dominant_hand' => 'left',
            'notes' => 'Notas actualizadas.',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Perfil de jugador actualizado correctamente.')
            ->assertJsonPath('data.id', $player->id)
            ->assertJsonPath('data.nickname', 'Nou Apodo')
            ->assertJsonPath('data.dominant_hand', 'left')
            ->assertJsonPath('data.notes', 'Notas actualizadas.');

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'nickname' => 'Nou Apodo',
            'dominant_hand' => 'left',
            'notes' => 'Notas actualizadas.',
        ]);
    }

    public function test_update_player_profile_returns_current_error_when_user_has_no_profile(): void
    {
        $this->createAuthenticatedUser();

        $this->patchJson('/api/v1/me/player-profile', [
            'nickname' => 'Sense perfil',
        ])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'El usuario autenticado no tiene un perfil de jugador asociado.',
                'data' => null,
            ]);
    }

    public function test_championship_registrations_only_return_the_current_users_requests(): void
    {
        [$user, $player] = $this->createAuthenticatedPlayer();
        $championship = Championship::factory()->create();
        $otherPlayer = Player::factory()->create();

        $ownRequest = ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $user->id,
            'player_id' => $player->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'comment' => 'Solicitud propia.',
        ]);

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $otherPlayer->user_id,
            'player_id' => $otherPlayer->id,
            'status' => 'approved',
            'payment_status' => 'paid',
        ]);

        $this->getJson('/api/v1/me/championship-registrations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownRequest->id)
            ->assertJsonPath('data.0.user_id', $user->id)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.payment_status', 'pending')
            ->assertJsonPath('data.0.championship.id', $championship->id);
    }

    public function test_user_without_player_profile_receives_an_empty_registration_collection(): void
    {
        $this->createAuthenticatedUser();

        $this->getJson('/api/v1/me/championship-registrations')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [],
            ]);
    }

    public function test_matches_only_return_matches_for_the_authenticated_player(): void
    {
        [, $player] = $this->createAuthenticatedPlayer();
        $match = $this->createSinglesMatchFor($player);
        $unrelatedMatch = GameMatch::factory()->create();

        $response = $this->getJson('/api/v1/me/matches');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.home_entry.player.id', $player->id);

        $returnedMatchIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$match->id], $returnedMatchIds);
        $this->assertNotContains($unrelatedMatch->id, $returnedMatchIds);
    }

    public function test_matches_return_current_error_when_user_has_no_player_profile(): void
    {
        $this->createAuthenticatedUser();

        $this->getJson('/api/v1/me/matches')
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'El usuario autenticado no tiene un perfil de jugador asociado.',
                'data' => null,
            ]);
    }

    public function test_calendar_groups_the_authenticated_players_scheduled_matches_by_date(): void
    {
        [, $player] = $this->createAuthenticatedPlayer();
        $match = $this->createSinglesMatchFor($player, [
            'scheduled_date' => '2026-07-15 18:30:00',
        ]);

        $this->getJson('/api/v1/me/calendar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-07-15')
            ->assertJsonPath('data.0.matches.0.id', $match->id)
            ->assertJsonPath('data.0.matches.0.home_entry_id', $match->home_entry_id);
    }

    public function test_calendar_returns_an_empty_collection_when_user_has_no_player_profile(): void
    {
        $this->createAuthenticatedUser();

        $this->getJson('/api/v1/me/calendar')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [],
            ]);
    }

    public function test_rankings_return_the_authenticated_players_category_position(): void
    {
        [, $player] = $this->createAuthenticatedPlayer();
        $match = $this->createSinglesMatchFor($player, [
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 7,
        ]);

        $this->getJson('/api/v1/me/rankings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.championship.id', $match->round->category->championship_id)
            ->assertJsonPath('data.0.category.id', $match->round->category_id)
            ->assertJsonPath('data.0.entry_type', 'player')
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.0.played', 1)
            ->assertJsonPath('data.0.wins', 1)
            ->assertJsonPath('data.0.points', 3);
    }

    public function test_rankings_return_an_empty_collection_when_user_has_no_player_profile(): void
    {
        $this->createAuthenticatedUser();

        $this->getJson('/api/v1/me/rankings')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [],
            ]);
    }

    public function test_all_my_panel_endpoints_require_authentication(): void
    {
        $requests = [
            ['GET', '/api/v1/me', []],
            ['GET', '/api/v1/me/player-profile', []],
            ['POST', '/api/v1/me/player-profile', ['level' => 5]],
            ['PATCH', '/api/v1/me/player-profile', ['nickname' => 'Pilotari']],
            ['GET', '/api/v1/me/championship-registrations', []],
            ['GET', '/api/v1/me/matches', []],
            ['GET', '/api/v1/me/calendar', []],
            ['GET', '/api/v1/me/rankings', []],
        ];

        foreach ($requests as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)
                ->assertUnauthorized();
        }
    }

    private function createAuthenticatedUser(): User
    {
        $user = User::factory()->create(['active' => true]);

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return array{User, Player}
     */
    private function createAuthenticatedPlayer(): array
    {
        $user = User::factory()->create(['active' => true]);
        $player = Player::factory()->for($user)->create(['active' => true]);

        Sanctum::actingAs($user);

        return [$user, $player];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createSinglesMatchFor(Player $player, array $attributes = []): GameMatch
    {
        $category = Category::factory()->create();
        $round = Round::factory()->for($category)->create([
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);
        $opponent = Player::factory()->create();

        $homeEntry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]);
        $awayEntry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $opponent->id,
            'status' => 'approved',
        ]);

        return GameMatch::factory()->create(array_merge([
            'round_id' => $round->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'scheduled_date' => '2026-07-15 18:30:00',
            'status' => 'scheduled',
        ], $attributes))->load('round.category.championship');
    }
}
