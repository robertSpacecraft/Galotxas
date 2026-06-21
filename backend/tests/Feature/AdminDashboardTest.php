<?php

namespace Tests\Feature;

use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_sees_pending_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();

        $pendingRequest = ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Solicitudes de inscripción pendientes');
        $response->assertSee($player->nickname ?: $player->user->name);
        $response->assertSee($championship->name);
    }

    public function test_approved_or_rejected_requests_are_not_shown(): void
    {
        $admin = User::factory()->admin()->create();
        $playerApproved = Player::factory()->create();
        $playerRejected = Player::factory()->create();
        $championship = Championship::factory()->create();

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $playerApproved->user_id,
            'player_id' => $playerApproved->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $playerRejected->user_id,
            'player_id' => $playerRejected->id,
            'status' => ChampionshipRegistrationRequestStatus::REJECTED->value,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('No hay solicitudes pendientes de revisión.');
        $response->assertDontSee($playerApproved->nickname ?: $playerApproved->user->name);
        $response->assertDontSee($playerRejected->nickname ?: $playerRejected->user->name);
    }

    public function test_admin_can_approve_pending_request_from_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();

        $pendingRequest = ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
        ]);

        // Simular que el origen de la petición es el dashboard para verificar la redirección (back())
        $response = $this->actingAs($admin)->from(route('admin.dashboard'))->post(route(
            'admin.championships.registration-requests.approve',
            [$championship, $pendingRequest]
        ));

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('success', 'Solicitud aprobada correctamente.');

        $this->assertDatabaseHas('championship_registration_requests', [
            'id' => $pendingRequest->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);
    }

    public function test_admin_can_reject_pending_request_from_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();

        $pendingRequest = ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
        ]);

        $response = $this->actingAs($admin)->from(route('admin.dashboard'))->post(route(
            'admin.championships.registration-requests.reject',
            [$championship, $pendingRequest]
        ));

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('success', 'Solicitud rechazada correctamente.');

        $this->assertDatabaseHas('championship_registration_requests', [
            'id' => $pendingRequest->id,
            'status' => ChampionshipRegistrationRequestStatus::REJECTED->value,
        ]);
    }
}
