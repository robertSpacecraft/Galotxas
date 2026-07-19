<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ChampionshipRegistrationPaymentStatus;
use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Enums\ChampionshipRegistrationStatus;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\Player;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChampionshipRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_register(): void
    {
        $championship = $this->createPublicChampionship();

        $response = $this->postJson("/api/v1/championships/{$championship->id}/register", []);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_without_player_cannot_register(): void
    {
        $user = User::factory()->create();
        $championship = $this->createPublicChampionship([
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'registration_starts_at' => now()->subDay(),
            'registration_ends_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/championships/{$championship->id}/register", []);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['message' => 'El usuario autenticado no tiene un perfil de jugador asociado.']);
    }

    public function test_authenticated_user_with_player_can_register_to_open_championship(): void
    {
        $player = Player::factory()->create();
        $championship = $this->createPublicChampionship([
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'registration_starts_at' => now()->subDay(),
            'registration_ends_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($player->user)->postJson("/api/v1/championships/{$championship->id}/register", [
            'comment' => 'Deseo jugar con mi hermano.',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Solicitud de inscripción enviada correctamente.']);
        $this->assertDatabaseHas('championship_registration_requests', [
            'championship_id' => $championship->id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
            'comment' => 'Deseo jugar con mi hermano.',
        ]);
    }

    public function test_cannot_register_if_championship_is_closed(): void
    {
        $player = Player::factory()->create();
        $championship = $this->createPublicChampionship([
            'registration_status' => ChampionshipRegistrationStatus::CLOSED->value,
            'registration_starts_at' => now()->subDays(10),
            'registration_ends_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($player->user)->postJson("/api/v1/championships/{$championship->id}/register", []);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['message' => 'Las inscripciones de este campeonato no están abiertas.']);
    }

    public function test_cannot_duplicate_registration_to_same_championship(): void
    {
        $player = Player::factory()->create();
        $championship = $this->createPublicChampionship([
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'registration_starts_at' => now()->subDay(),
            'registration_ends_at' => now()->addDay(),
        ]);

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PENDING->value,
        ]);

        $response = $this->actingAs($player->user)->postJson("/api/v1/championships/{$championship->id}/register", []);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['message' => 'Ya existe una solicitud de inscripción para este campeonato.']);
    }

    public function test_get_registration_status_returns_correct_state_for_authenticated_player(): void
    {
        $player = Player::factory()->create();
        $championship = $this->createPublicChampionship([
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'registration_starts_at' => now()->subDay(),
            'registration_ends_at' => now()->addDay(),
        ]);

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PENDING->value,
        ]);

        $response = $this->actingAs($player->user)->getJson("/api/v1/championships/{$championship->id}/registration");

        $response->assertOk();
        $response->assertJsonPath('data.registration_is_open', true);
        $response->assertJsonPath('data.request.status', 'approved');
        $response->assertJsonPath('data.request.player.id', $player->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPublicChampionship(array $attributes = []): Championship
    {
        $season = Season::factory()->publiclyVisible()->create();

        return Championship::factory()->publiclyVisible()->create(array_merge([
            'season_id' => $season->id,
        ], $attributes));
    }
}
