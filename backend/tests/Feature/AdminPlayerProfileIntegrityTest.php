<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryRegistration;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPlayerProfileIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_create_normalizes_and_enforces_nickname_uniqueness(): void
    {
        $admin = User::factory()->admin()->create();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.players.store'), $this->payload($firstUser, [
            'nickname' => '  La   Ràpida ',
        ]))->assertRedirect(route('admin.players.index'));

        $this->assertDatabaseHas('players', ['nickname' => 'La Ràpida']);

        $this->actingAs($admin)->from(route('admin.players.create'))
            ->post(route('admin.players.store'), $this->payload($secondUser, [
                'nickname' => 'la rapida',
            ]))
            ->assertRedirect(route('admin.players.create'))
            ->assertSessionHasErrors('nickname');
    }

    public function test_admin_update_normalizes_fields_and_keeps_slug_immutable_when_user_and_nickname_change(): void
    {
        $admin = User::factory()->admin()->create();
        $player = Player::factory()->create([
            'nickname' => 'Original',
            'slug' => 'slug-inmutable',
        ]);
        $replacementUser = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.players.update', $player), $this->payload($replacementUser, [
            'nickname' => "  Alias\t Nuevo ",
            'license_number' => "\u{00A0} LIC-ADMIN \u{00A0}",
        ]))->assertRedirect(route('admin.players.index'));

        $player->refresh();
        $this->assertSame('Alias Nuevo', $player->nickname);
        $this->assertSame('LIC-ADMIN', $player->license_number);
        $this->assertSame('slug-inmutable', $player->slug);
        $this->assertSame($replacementUser->id, $player->user_id);
    }

    public function test_admin_update_rejects_normalized_nickname_and_license_collisions(): void
    {
        $admin = User::factory()->admin()->create();
        Player::factory()->create([
            'nickname' => 'Alias Reservado',
            'license_number' => 'LIC-RESERVADA',
        ]);
        $player = Player::factory()->create([
            'nickname' => 'Original',
            'license_number' => 'LIC-ORIGINAL',
        ]);

        $this->actingAs($admin)->from(route('admin.players.edit', $player))
            ->put(route('admin.players.update', $player), $this->payload($player->user, [
                'nickname' => '  alias   reservado ',
                'license_number' => ' LIC-RESERVADA ',
            ]))
            ->assertRedirect(route('admin.players.edit', $player))
            ->assertSessionHasErrors(['nickname', 'license_number']);

        $this->assertSame('Original', $player->fresh()->nickname);
        $this->assertSame('LIC-ORIGINAL', $player->fresh()->license_number);
    }

    public function test_category_page_disambiguates_exactly_the_three_approved_selectors(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create(['type' => 'doubles']);
        $category = Category::factory()->for($championship)->create();

        $available = Player::factory()->create(['nickname' => 'Disponible']);
        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $available->user_id,
            'player_id' => $available->id,
            'status' => 'approved',
            'payment_status' => 'pending',
        ]);

        $teamPlayer = Player::factory()->create(['nickname' => 'Equipo']);
        CategoryRegistration::query()->create([
            'category_id' => $category->id,
            'player_id' => $teamPlayer->id,
            'status' => 'approved',
        ]);

        $fallback = Player::factory()->create(['nickname' => null]);
        CategoryRegistration::query()->create([
            'category_id' => $category->id,
            'player_id' => $fallback->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.categories.show', $category))
            ->assertOk();

        $availableLabel = 'Disponible — '.$available->user->name.' '.$available->user->lastname;
        $teamLabel = 'Equipo — '.$teamPlayer->user->name.' '.$teamPlayer->user->lastname;
        $fallbackLabel = $fallback->user->name.' '.$fallback->user->lastname;

        $availableOptions = $this->selectMarkup($response->getContent(), 'player_id');
        $frontOptions = $this->selectMarkup($response->getContent(), 'front_player_id');
        $backOptions = $this->selectMarkup($response->getContent(), 'back_player_id');

        $this->assertStringContainsString(e($availableLabel), $availableOptions);
        $this->assertStringContainsString(e($teamLabel), $frontOptions);
        $this->assertStringContainsString(e($teamLabel), $backOptions);
        $this->assertStringContainsString(e($fallbackLabel), $frontOptions);
        $this->assertStringContainsString(e($fallbackLabel), $backOptions);
    }

    /** @return array<string, mixed> */
    private function payload(User $user, array $overrides = []): array
    {
        return [
            'user_id' => $user->id,
            'nickname' => null,
            'dni' => null,
            'birth_date' => null,
            'gender' => null,
            'level' => 5,
            'license_number' => null,
            'dominant_hand' => null,
            'notes' => null,
            'active' => '1',
            ...$overrides,
        ];
    }

    private function selectMarkup(string $html, string $name): string
    {
        $matched = preg_match(
            '/<select[^>]*name="'.preg_quote($name, '/').'"[^>]*>.*?<\/select>/s',
            $html,
            $matches
        );

        $this->assertSame(1, $matched, "No se encontró el selector {$name}.");

        return $matches[0];
    }
}
