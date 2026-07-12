<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_venues(): void
    {
        $admin = User::factory()->admin()->create();
        Venue::factory()->create([
            'name' => 'Pista Central',
            'location' => 'València',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.venues.index'));

        $response->assertOk();
        $response->assertSee('Pistas');
        $response->assertSee('Pista Central');
        $response->assertSee('València');
        $response->assertSee(route('admin.venues.create'));
    }

    public function test_admin_can_create_a_venue(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.venues.create'))
            ->assertOk()
            ->assertSee('Crear pista');

        $response = $this->actingAs($admin)
            ->post(route('admin.venues.store'), [
                'name' => 'Pista Nord',
                'location' => 'Almussafes',
                'description' => 'Pista coberta.',
            ]);

        $response->assertRedirect(route('admin.venues.index'));
        $response->assertSessionHas('success', 'Pista creada correctamente.');

        $this->assertDatabaseHas('venues', [
            'name' => 'Pista Nord',
            'location' => 'Almussafes',
            'description' => 'Pista coberta.',
        ]);
    }

    public function test_admin_can_edit_a_venue(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Venue::factory()->create(['name' => 'Pista Antiga']);

        $this->actingAs($admin)
            ->get(route('admin.venues.edit', $venue))
            ->assertOk()
            ->assertSee('Pista Antiga');

        $response = $this->actingAs($admin)
            ->put(route('admin.venues.update', $venue), [
                'name' => 'Pista Renovada',
                'location' => 'Sueca',
                'description' => 'Pista actualitzada.',
            ]);

        $response->assertRedirect(route('admin.venues.index'));
        $response->assertSessionHas('success', 'Pista actualizada correctamente.');

        $this->assertDatabaseHas('venues', [
            'id' => $venue->id,
            'name' => 'Pista Renovada',
            'location' => 'Sueca',
            'description' => 'Pista actualitzada.',
        ]);
    }

    public function test_non_admin_user_cannot_access_venue_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.venues.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.venues.store'), [
                'name' => 'Pista no autorizada',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('venues', ['name' => 'Pista no autorizada']);
    }

    public function test_venue_creation_validates_required_unique_and_maximum_lengths(): void
    {
        $admin = User::factory()->admin()->create();
        Venue::factory()->create(['name' => 'Pista Duplicada']);

        $response = $this->actingAs($admin)
            ->from(route('admin.venues.create'))
            ->post(route('admin.venues.store'), [
                'name' => 'Pista Duplicada',
                'location' => str_repeat('a', 256),
                'description' => str_repeat('b', 5001),
            ]);

        $response->assertRedirect(route('admin.venues.create'));
        $response->assertSessionHasErrors(['name', 'location', 'description']);

        $this->actingAs($admin)
            ->post(route('admin.venues.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_venue_update_requires_a_unique_name_but_allows_its_current_name(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Venue::factory()->create(['name' => 'Pista A']);
        Venue::factory()->create(['name' => 'Pista B']);

        $this->actingAs($admin)
            ->put(route('admin.venues.update', $venue), [
                'name' => 'Pista B',
                'location' => '',
                'description' => '',
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->put(route('admin.venues.update', $venue), [
                'name' => 'Pista A',
                'location' => '',
                'description' => '',
            ])
            ->assertRedirect(route('admin.venues.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_admin_can_delete_an_unused_venue(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Venue::factory()->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.venues.destroy', $venue));

        $response->assertRedirect(route('admin.venues.index'));
        $response->assertSessionHas('success', 'Pista eliminada correctamente.');
        $this->assertDatabaseMissing('venues', ['id' => $venue->id]);
    }

    public function test_admin_cannot_delete_a_venue_used_by_a_match(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Venue::factory()->create();
        $match = GameMatch::factory()->create(['venue_id' => $venue->id]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.venues.destroy', $venue));

        $response->assertRedirect(route('admin.venues.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('venues', ['id' => $venue->id]);
        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'venue_id' => $venue->id,
        ]);
    }
}
