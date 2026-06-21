<?php

namespace Tests\Feature;

use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Models\Category;
use App\Models\CategoryRegistration;
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

    public function test_approved_or_rejected_requests_are_not_shown_in_pending_section(): void
    {
        $admin = User::factory()->admin()->create();
        $playerApproved = Player::factory()->create();
        $playerRejected = Player::factory()->create();
        $championship = Championship::factory()->create();
        $category = Category::factory()->create(['championship_id' => $championship->id]);

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $playerApproved->user_id,
            'player_id' => $playerApproved->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);

        // Asignar para que no aparezca tampoco en la sección de aprobados sin categoría
        CategoryRegistration::query()->create([
            'category_id' => $category->id,
            'player_id' => $playerApproved->id,
            'status' => 'approved',
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

    public function test_admin_sees_approved_unassigned_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Solicitudes aprobadas pendientes de categoría');
        $response->assertSee($player->nickname ?: $player->user->name);
        $response->assertSee($championship->name);
    }

    public function test_pending_requests_are_not_shown_in_unassigned_section(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('No hay solicitudes aprobadas pendientes de asignación a categoría.');
    }

    public function test_rejected_requests_are_not_shown_in_unassigned_section(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::REJECTED->value,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('No hay solicitudes aprobadas pendientes de asignación a categoría.');
    }

    public function test_approved_request_not_shown_if_player_assigned_to_category(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $category = Category::factory()->create(['championship_id' => $championship->id]);

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);

        // Asignar el jugador a una categoría del mismo campeonato
        CategoryRegistration::query()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('No hay solicitudes aprobadas pendientes de asignación a categoría.');
    }

    public function test_unassigned_section_contains_link_to_championship_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.championships.categories', $championship));
    }

    public function test_non_admin_user_cannot_access_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }
}
