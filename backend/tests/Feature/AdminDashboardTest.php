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

    public function test_admin_sees_registration_request_summary_counts_and_management_link(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();
        $category = Category::factory()->create(['championship_id' => $championship->id]);

        $pendingPlayers = Player::factory()->count(2)->create();
        foreach ($pendingPlayers as $player) {
            ChampionshipRegistrationRequest::query()->create([
                'championship_id' => $championship->id,
                'user_id' => $player->user_id,
                'player_id' => $player->id,
                'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
            ]);
        }

        $unassignedPlayer = Player::factory()->create();
        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $unassignedPlayer->user_id,
            'player_id' => $unassignedPlayer->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);

        $assignedPlayer = Player::factory()->create();
        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $assignedPlayer->user_id,
            'player_id' => $assignedPlayer->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);
        CategoryRegistration::query()->create([
            'category_id' => $category->id,
            'player_id' => $assignedPlayer->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Solicitudes pendientes');
        $response->assertSee('id="pending-requests-count"', false);
        $response->assertSee('data-count="2"', false);
        $response->assertSee('Aprobadas pendientes de categoría');
        $response->assertSee('id="approved-unassigned-requests-count"', false);
        $response->assertSee('data-count="1"', false);
        $response->assertSee(route('admin.registration-requests.index'));
    }

    public function test_dashboard_does_not_render_operational_request_forms(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $request = ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee(route(
            'admin.championships.registration-requests.approve',
            [$championship, $request]
        ));
        $response->assertDontSee(route('admin.categories.registrations.store', $category));
        $response->assertDontSee('name="player_id"', false);
    }

    public function test_non_admin_user_cannot_access_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }
}
