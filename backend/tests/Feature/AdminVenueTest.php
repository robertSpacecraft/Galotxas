<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

class AdminVenueTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        try {
            $this->truncateDatabaseTables();
        } finally {
            parent::tearDown();
        }
    }

    public function test_admin_can_list_venues(): void
    {
        $admin = User::factory()->admin()->create();
        Venue::factory()->create([
            'court_number' => 4,
            'name' => 'Pista Central',
            'location' => 'València',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.venues.index'));

        $response->assertOk();
        $response->assertSee('Pistas');
        $response->assertSee('Número de pista');
        $response->assertSee('4');
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
            ->assertSee('Crear pista')
            ->assertSee('Número de pista')
            ->assertSee('name="court_number"', false);

        $response = $this->actingAs($admin)
            ->post(route('admin.venues.store'), [
                'court_number' => 2,
                'name' => 'Pista Nord',
                'location' => 'Almussafes',
                'description' => 'Pista coberta.',
            ]);

        $response->assertRedirect(route('admin.venues.index'));
        $response->assertSessionHas('success', 'Pista creada correctamente.');

        $this->assertDatabaseHas('venues', [
            'court_number' => 2,
            'name' => 'Pista Nord',
            'location' => 'Almussafes',
            'description' => 'Pista coberta.',
        ]);
    }

    public function test_admin_can_edit_a_venue(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Venue::factory()->create([
            'court_number' => 3,
            'name' => 'Pista Antiga',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.venues.edit', $venue))
            ->assertOk()
            ->assertSee('Pista Antiga');

        $response = $this->actingAs($admin)
            ->put(route('admin.venues.update', $venue), [
                'court_number' => 3,
                'name' => 'Pista Renovada',
                'location' => 'Sueca',
                'description' => 'Pista actualitzada.',
            ]);

        $response->assertRedirect(route('admin.venues.index'));
        $response->assertSessionHas('success', 'Pista actualizada correctamente.');

        $this->assertDatabaseHas('venues', [
            'id' => $venue->id,
            'court_number' => 3,
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
                'court_number' => 8,
                'name' => 'Pista Duplicada',
                'location' => str_repeat('a', 256),
                'description' => str_repeat('b', 5001),
            ]);

        $response->assertRedirect(route('admin.venues.create'));
        $response->assertSessionHasErrors(['name', 'location', 'description']);

        $this->actingAs($admin)
            ->post(route('admin.venues.store'), ['name' => 'Pista sin número'])
            ->assertSessionHasErrors('court_number');

        $this->actingAs($admin)
            ->post(route('admin.venues.store'), [
                'court_number' => 9,
                'name' => '',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_venue_creation_rejects_duplicate_and_non_positive_court_numbers(): void
    {
        $admin = User::factory()->admin()->create();
        Venue::factory()->create(['court_number' => 5]);

        $this->actingAs($admin)
            ->post(route('admin.venues.store'), [
                'court_number' => 5,
                'name' => 'Pista con número duplicado',
            ])
            ->assertSessionHasErrors('court_number');

        foreach ([0, -1] as $courtNumber) {
            $this->actingAs($admin)
                ->post(route('admin.venues.store'), [
                    'court_number' => $courtNumber,
                    'name' => 'Pista no positiva '.$courtNumber,
                ])
                ->assertSessionHasErrors('court_number');
        }

        $this->assertDatabaseMissing('venues', ['name' => 'Pista con número duplicado']);
    }

    public function test_venue_update_requires_a_unique_name_but_allows_its_current_name(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Venue::factory()->create([
            'court_number' => 10,
            'name' => 'Pista A',
        ]);
        Venue::factory()->create([
            'court_number' => 11,
            'name' => 'Pista B',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.venues.update', $venue), [
                'court_number' => 10,
                'name' => 'Pista B',
                'location' => '',
                'description' => '',
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->put(route('admin.venues.update', $venue), [
                'court_number' => 10,
                'name' => 'Pista A',
                'location' => '',
                'description' => '',
            ])
            ->assertRedirect(route('admin.venues.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_venue_update_rejects_another_venue_number_but_allows_its_current_number(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Venue::factory()->create(['court_number' => 12]);
        Venue::factory()->create(['court_number' => 13]);

        $this->actingAs($admin)
            ->put(route('admin.venues.update', $venue), [
                'court_number' => 13,
                'name' => $venue->name,
                'location' => $venue->location,
                'description' => '',
            ])
            ->assertSessionHasErrors('court_number');

        $this->actingAs($admin)
            ->put(route('admin.venues.update', $venue), [
                'court_number' => 12,
                'name' => $venue->name,
                'location' => $venue->location,
                'description' => '',
            ])
            ->assertRedirect(route('admin.venues.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(12, $venue->fresh()->court_number);
    }

    public function test_legacy_venue_remains_readable_but_editing_requires_explicit_classification(): void
    {
        $admin = User::factory()->admin()->create();
        $legacy = Venue::query()->create([
            'name' => 'Pista 6 legacy',
            'location' => 'València',
        ]);

        $this->assertNull($legacy->court_number);

        $this->actingAs($admin)
            ->get(route('admin.venues.index'))
            ->assertOk()
            ->assertSee('Pista 6 legacy')
            ->assertSee('Sin clasificar');

        $this->actingAs($admin)
            ->put(route('admin.venues.update', $legacy), [
                'name' => 'Pista 6 legacy',
                'location' => 'València',
                'description' => '',
            ])
            ->assertSessionHasErrors('court_number');

        $this->assertNull($legacy->fresh()->court_number);

        $this->actingAs($admin)
            ->put(route('admin.venues.update', $legacy), [
                'court_number' => 21,
                'name' => 'Pista 6 legacy',
                'location' => 'València',
                'description' => '',
            ])
            ->assertRedirect(route('admin.venues.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(21, $legacy->fresh()->court_number);
    }

    public function test_venue_name_and_database_id_do_not_determine_court_number(): void
    {
        $legacy = Venue::query()->create(['name' => 'Pista 4']);
        $classified = Venue::factory()->create([
            'court_number' => 37,
            'name' => 'Nombre sin número físico',
        ]);

        $this->assertNotNull($legacy->id);
        $this->assertNull($legacy->court_number);
        $this->assertSame(37, $classified->court_number);
        $this->assertNotSame($classified->id, $classified->court_number);
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

    public function test_venue_creation_rejects_court_number_above_unsigned_int_range(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.venues.store'), [
                'court_number' => 4294967296,
                'name' => 'Pista fuera de rango',
                'location' => '',
                'description' => '',
            ])
            ->assertSessionHasErrors('court_number');

        $this->assertDatabaseMissing('venues', [
            'name' => 'Pista fuera de rango',
        ]);
    }

}
