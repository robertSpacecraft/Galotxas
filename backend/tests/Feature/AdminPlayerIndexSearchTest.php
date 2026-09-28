<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPlayerIndexSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_nickname_and_user_identity_and_excludes_unrelated_players(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@admin.test']);
        $byNickname = Player::factory()->create(['nickname' => 'El Pollo Zarquonick']);
        $byName = Player::factory()
            ->for(User::factory()->state(['name' => 'Zarquonname', 'email' => 'first@search.test']))
            ->create(['nickname' => null]);
        $byLastname = Player::factory()
            ->for(User::factory()->state(['lastname' => 'Zarquonlast', 'email' => 'second@search.test']))
            ->create(['nickname' => null]);
        $byEmail = Player::factory()
            ->for(User::factory()->state(['email' => 'zarquon.mail@search.test']))
            ->create(['nickname' => null]);
        $unrelated = Player::factory()
            ->for(User::factory()->state([
                'name' => 'Unrelated',
                'lastname' => 'Person',
                'email' => 'unrelated@search.test',
            ]))
            ->create(['nickname' => 'Nobody']);

        $this->assertIndexIds($admin, ['q' => 'pollo'], [$byNickname->id]);
        $this->assertIndexIds($admin, ['q' => 'ZARQUONNAME'], [$byName->id]);
        $this->assertIndexIds($admin, ['q' => 'zarquonlast'], [$byLastname->id]);
        $this->assertIndexIds($admin, ['q' => 'zarquon.mail'], [$byEmail->id]);
        $this->assertIndexIds(
            $admin,
            ['q' => ' zarquon '],
            [$byEmail->id, $byLastname->id, $byName->id, $byNickname->id],
        );

        $this->actingAs($admin)
            ->get(route('admin.players.index', ['q' => 'zarquon']))
            ->assertOk()
            ->assertDontSee($unrelated->user->email);
    }

    public function test_search_does_not_match_private_fields_outside_the_identity_scope(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@admin.test']);
        Player::factory()->create([
            'nickname' => null,
            'dni' => '12345678Z',
            'license_number' => 'LIC-77777',
        ]);

        $this->assertIndexIds($admin, ['q' => '12345678Z'], []);
        $this->assertIndexIds($admin, ['q' => 'LIC-77777'], []);
    }

    public function test_blank_search_keeps_the_full_ordered_index(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Player::factory()->create();
        $second = Player::factory()->create();

        $this->assertIndexIds($admin, [], [$second->id, $first->id]);
        $this->assertIndexIds($admin, ['q' => '   '], [$second->id, $first->id]);
    }

    public function test_search_applies_to_the_whole_dataset_and_pagination_keeps_it(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@admin.test']);
        $matching = Player::factory()
            ->count(17)
            ->sequence(fn ($sequence) => ['nickname' => "Pagetoken {$sequence->index}"])
            ->create();
        Player::factory()->count(3)->create(['nickname' => null]);

        $response = $this->actingAs($admin)
            ->get(route('admin.players.index', ['q' => 'pagetoken']));

        $players = $response->assertOk()->viewData('players');
        $this->assertSame(17, $players->total());
        $nextPageUrl = $players->nextPageUrl();
        $this->assertStringContainsString('q=pagetoken', $nextPageUrl);
        $response->assertSee(e($nextPageUrl), false);

        $secondPage = $this->actingAs($admin)
            ->get($nextPageUrl)
            ->assertOk()
            ->viewData('players');

        $this->assertEqualsCanonicalizing(
            $matching->pluck('id')->sortDesc()->slice(15)->values()->all(),
            $secondPage->pluck('id')->all(),
        );
    }

    public function test_form_shows_clear_only_for_an_active_search(): void
    {
        $admin = User::factory()->admin()->create();
        Player::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.players.index'))
            ->assertOk()
            ->assertSee('name="q"', false)
            ->assertSee('placeholder="Nombre, apellidos, email o apodo"', false)
            ->assertSee('Buscar')
            ->assertDontSee('Limpiar');

        $this->actingAs($admin)
            ->get(route('admin.players.index', ['q' => 'algo']))
            ->assertOk()
            ->assertSee('value="algo"', false)
            ->assertSee('Limpiar');
    }

    public function test_empty_states_distinguish_no_players_from_no_matches(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.players.index'))
            ->assertOk()
            ->assertSee('No hay jugadores registrados todavía.')
            ->assertDontSee('No se han encontrado jugadores con los criterios indicados.');

        Player::factory()->create(['nickname' => 'Existing']);

        $this->actingAs($admin)
            ->get(route('admin.players.index', ['q' => 'no-such-player-anywhere']))
            ->assertOk()
            ->assertSee('No se han encontrado jugadores con los criterios indicados.')
            ->assertDontSee('No hay jugadores registrados todavía.');
    }

    /**
     * @param  array<string, string>  $query
     * @param  list<int>  $expectedIds
     */
    private function assertIndexIds(User $admin, array $query, array $expectedIds): void
    {
        $players = $this->actingAs($admin)
            ->get(route('admin.players.index', $query))
            ->assertOk()
            ->viewData('players');

        $this->assertSame($expectedIds, $players->pluck('id')->all());
    }
}
