<?php

namespace Tests\Feature;

use App\Enums\ChampionshipRegistrationPaymentStatus;
use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChampionshipRegistrationRequestAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_return_a_rejected_request_to_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $registrationRequest = ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::REJECTED->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PAID->value,
        ]);

        $response = $this->actingAs($admin)->post(route(
            'admin.championships.registration-requests.mark-as-pending',
            [$championship, $registrationRequest]
        ));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Solicitud devuelta a pendiente correctamente.');
        $this->assertDatabaseHas('championship_registration_requests', [
            'id' => $registrationRequest->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PAID->value,
        ]);
    }

    public function test_admin_cannot_return_a_request_from_another_championship_to_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $otherChampionship = Championship::factory()->create();
        $registrationRequest = ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $otherChampionship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::REJECTED->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PENDING->value,
        ]);

        $response = $this->actingAs($admin)->post(route(
            'admin.championships.registration-requests.mark-as-pending',
            [$championship, $registrationRequest]
        ));

        $response->assertNotFound();
        $this->assertDatabaseHas('championship_registration_requests', [
            'id' => $registrationRequest->id,
            'status' => ChampionshipRegistrationRequestStatus::REJECTED->value,
        ]);
    }

    public function test_admin_can_approve_a_pending_request(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $registrationRequest = ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PENDING->value,
        ]);

        $response = $this->actingAs($admin)->post(route(
            'admin.championships.registration-requests.approve',
            [$championship, $registrationRequest]
        ));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Solicitud aprobada correctamente.');
        $this->assertDatabaseHas('championship_registration_requests', [
            'id' => $registrationRequest->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);
    }

    public function test_admin_can_reject_a_pending_request(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $registrationRequest = ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PENDING->value,
        ]);

        $response = $this->actingAs($admin)->post(route(
            'admin.championships.registration-requests.reject',
            [$championship, $registrationRequest]
        ));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Solicitud rechazada correctamente.');
        $this->assertDatabaseHas('championship_registration_requests', [
            'id' => $registrationRequest->id,
            'status' => ChampionshipRegistrationRequestStatus::REJECTED->value,
        ]);
    }

    public function test_non_admin_cannot_approve_reject_or_return_request(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $registrationRequest = ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PENDING->value,
        ]);

        $responseApprove = $this->actingAs($user)->post(route(
            'admin.championships.registration-requests.approve',
            [$championship, $registrationRequest]
        ));

        $responseReject = $this->actingAs($user)->post(route(
            'admin.championships.registration-requests.reject',
            [$championship, $registrationRequest]
        ));

        $responseApprove->assertForbidden();
        $responseReject->assertForbidden();
    }
}
