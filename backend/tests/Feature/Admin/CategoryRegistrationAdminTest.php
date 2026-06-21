<?php

namespace Tests\Feature\Admin;

use App\Enums\ChampionshipRegistrationPaymentStatus;
use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Models\Category;
use App\Models\CategoryRegistration;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryRegistrationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_to_category_only_if_approved_request_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $player = Player::factory()->create();

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PENDING->value,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.categories.registrations.store', $category), [
            'player_id' => $player->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('category_registrations', [
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_cannot_assign_if_request_is_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $player = Player::factory()->create();

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PENDING->value,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.categories.registrations.store', $category), [
            'player_id' => $player->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'El jugador no tiene una solicitud aprobada en este campeonato.');
        $this->assertDatabaseMissing('category_registrations', [
            'category_id' => $category->id,
            'player_id' => $player->id,
        ]);
    }

    public function test_admin_cannot_assign_if_no_request_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $player = Player::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.categories.registrations.store', $category), [
            'player_id' => $player->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'El jugador no tiene una solicitud aprobada en este campeonato.');
    }

    public function test_prevents_duplicate_category_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $player = Player::factory()->create();

        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PENDING->value,
        ]);

        CategoryRegistration::query()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.categories.registrations.store', $category), [
            'player_id' => $player->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'El jugador ya está inscrito en esta categoría.');
    }
}
