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

class AdminRegistrationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_registration_request_management_screen(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.registration-requests.index'));

        $response->assertOk();
        $response->assertSee('Solicitudes e inscripciones');
        $response->assertSee('Solicitudes de inscripción pendientes');
        $response->assertSee('Solicitudes aprobadas pendientes de categoría');
    }

    public function test_non_admin_user_cannot_access_registration_request_management_screen(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get(route('admin.registration-requests.index'));

        $response->assertForbidden();
    }

    public function test_screen_shows_pending_and_approved_unassigned_requests_only_in_their_sections(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();
        $category = Category::factory()->create(['championship_id' => $championship->id]);

        $pendingPlayer = Player::factory()->create(['nickname' => 'JugadorPendiente']);
        $approvedPlayer = Player::factory()->create(['nickname' => 'JugadorAprobado']);
        $rejectedPlayer = Player::factory()->create(['nickname' => 'JugadorRechazado']);
        $assignedPlayer = Player::factory()->create(['nickname' => 'JugadorAsignado']);

        foreach ([
            [$pendingPlayer, ChampionshipRegistrationRequestStatus::PENDING],
            [$approvedPlayer, ChampionshipRegistrationRequestStatus::APPROVED],
            [$rejectedPlayer, ChampionshipRegistrationRequestStatus::REJECTED],
            [$assignedPlayer, ChampionshipRegistrationRequestStatus::APPROVED],
        ] as [$player, $status]) {
            ChampionshipRegistrationRequest::query()->create([
                'championship_id' => $championship->id,
                'user_id' => $player->user_id,
                'player_id' => $player->id,
                'status' => $status->value,
            ]);
        }

        CategoryRegistration::query()->create([
            'category_id' => $category->id,
            'player_id' => $assignedPlayer->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.registration-requests.index'));

        $response->assertOk();
        $response->assertSee('JugadorPendiente');
        $response->assertSee('JugadorAprobado');
        $response->assertDontSee('JugadorRechazado');
        $response->assertDontSee('JugadorAsignado');
    }

    public function test_admin_can_approve_pending_request_from_management_screen(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $request = $this->createRequest($championship, $player, ChampionshipRegistrationRequestStatus::PENDING);

        $response = $this->actingAs($admin)
            ->from(route('admin.registration-requests.index'))
            ->post(route(
                'admin.championships.registration-requests.approve',
                [$championship, $request]
            ));

        $response->assertRedirect(route('admin.registration-requests.index'));
        $response->assertSessionHas('success', 'Solicitud aprobada correctamente.');
        $this->assertDatabaseHas('championship_registration_requests', [
            'id' => $request->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);
    }

    public function test_admin_can_reject_pending_request_from_management_screen(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $request = $this->createRequest($championship, $player, ChampionshipRegistrationRequestStatus::PENDING);

        $response = $this->actingAs($admin)
            ->from(route('admin.registration-requests.index'))
            ->post(route(
                'admin.championships.registration-requests.reject',
                [$championship, $request]
            ));

        $response->assertRedirect(route('admin.registration-requests.index'));
        $response->assertSessionHas('success', 'Solicitud rechazada correctamente.');
        $this->assertDatabaseHas('championship_registration_requests', [
            'id' => $request->id,
            'status' => ChampionshipRegistrationRequestStatus::REJECTED->value,
        ]);
    }

    public function test_assignment_form_contains_only_championship_categories_and_preselects_suggestion(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $otherChampionship = Championship::factory()->create();
        $firstCategory = Category::factory()->create([
            'championship_id' => $championship->id,
            'name' => 'Primera categoría',
            'level' => 1,
        ]);
        $suggestedCategory = Category::factory()->create([
            'championship_id' => $championship->id,
            'name' => 'Segunda categoría',
            'level' => 2,
        ]);
        $foreignCategory = Category::factory()->create([
            'championship_id' => $otherChampionship->id,
            'name' => 'Categoría ajena',
        ]);

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'suggested_category_id' => $suggestedCategory->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.registration-requests.index'));

        $response->assertOk();
        $response->assertSee(route('admin.categories.registrations.store', $firstCategory));
        $response->assertSee(route('admin.categories.registrations.store', $suggestedCategory));
        $response->assertDontSee($foreignCategory->name);
        $this->assertMatchesRegularExpression(
            '/<option\s+value="'.preg_quote(route('admin.categories.registrations.store', $suggestedCategory), '/').'"\s+selected\s*>/u',
            $response->getContent()
        );
    }

    public function test_screen_shows_category_management_links_when_no_categories_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $this->createRequest($championship, $player, ChampionshipRegistrationRequestStatus::APPROVED);

        $response = $this->actingAs($admin)->get(route('admin.registration-requests.index'));

        $response->assertOk();
        $response->assertSee('Este campeonato no tiene categorías disponibles.');
        $response->assertSee(route('admin.categories.create', $championship));
        $response->assertSee(route('admin.championships.categories', $championship));
        $response->assertDontSee('name="player_id"', false);
    }

    public function test_admin_can_assign_category_and_request_disappears_from_unassigned_section(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create(['nickname' => 'JugadorPorAsignar']);
        $championship = Championship::factory()->create();
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $this->createRequest($championship, $player, ChampionshipRegistrationRequestStatus::APPROVED);

        $response = $this->actingAs($admin)
            ->from(route('admin.registration-requests.index'))
            ->post(route('admin.categories.registrations.store', $category), [
                'player_id' => $player->id,
            ]);

        $response->assertRedirect(route('admin.registration-requests.index'));
        $response->assertSessionHas('success', 'Jugador inscrito correctamente en la categoría.');
        $this->assertDatabaseHas('category_registrations', [
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.registration-requests.index'))
            ->assertOk()
            ->assertSee('No hay solicitudes aprobadas pendientes de asignación a categoría.')
            ->assertDontSee('JugadorPorAsignar');
    }

    public function test_admin_cannot_assign_player_to_category_from_another_championship(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create();
        $championship = Championship::factory()->create();
        $otherChampionship = Championship::factory()->create();
        $foreignCategory = Category::factory()->create(['championship_id' => $otherChampionship->id]);
        $this->createRequest($championship, $player, ChampionshipRegistrationRequestStatus::APPROVED);

        $response = $this->actingAs($admin)
            ->from(route('admin.registration-requests.index'))
            ->post(route('admin.categories.registrations.store', $foreignCategory), [
                'player_id' => $player->id,
            ]);

        $response->assertRedirect(route('admin.registration-requests.index'));
        $response->assertSessionHas('error', 'El jugador no tiene una solicitud aprobada en este campeonato.');
        $this->assertDatabaseMissing('category_registrations', [
            'category_id' => $foreignCategory->id,
            'player_id' => $player->id,
        ]);
    }

    public function test_non_admin_user_cannot_execute_category_assignment(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $player = Player::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($user)->post(
            route('admin.categories.registrations.store', $category),
            ['player_id' => $player->id]
        );

        $response->assertForbidden();
        $this->assertDatabaseMissing('category_registrations', [
            'category_id' => $category->id,
            'player_id' => $player->id,
        ]);
    }

    private function createRequest(
        Championship $championship,
        Player $player,
        ChampionshipRegistrationRequestStatus $status
    ): ChampionshipRegistrationRequest {
        return ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => $status->value,
        ]);
    }
}
