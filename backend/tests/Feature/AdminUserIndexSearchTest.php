<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserIndexSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_name_lastname_email_and_player_nickname_and_excludes_unrelated_users(): void
    {
        $admin = User::factory()->admin()->create();
        $byName = User::factory()->create(['name' => 'Zarquonname', 'email' => 'first@search.test']);
        $byLastname = User::factory()->create(['lastname' => 'Zarquonlast', 'email' => 'second@search.test']);
        $byEmail = User::factory()->create(['email' => 'zarquon.mail@search.test']);
        $byNickname = User::factory()->create(['email' => 'fourth@search.test']);
        Player::factory()->for($byNickname)->create(['nickname' => 'El Pollo Zarquonick']);
        $unrelated = User::factory()->create([
            'name' => 'Unrelated',
            'lastname' => 'Person',
            'email' => 'unrelated@search.test',
        ]);

        $this->assertIndexIds($admin, ['q' => 'zarquonname'], [$byName->id]);
        $this->assertIndexIds($admin, ['q' => 'ZARQUONLAST'], [$byLastname->id]);
        $this->assertIndexIds($admin, ['q' => 'zarquon.mail'], [$byEmail->id]);
        $this->assertIndexIds($admin, ['q' => 'pollo'], [$byNickname->id]);
        $this->assertIndexIds(
            $admin,
            ['q' => 'zarquon'],
            [$byNickname->id, $byEmail->id, $byLastname->id, $byName->id],
        );

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'zarquon']))
            ->assertOk()
            ->assertDontSee($unrelated->email);
    }

    public function test_search_is_trimmed_and_blank_search_returns_the_full_index(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['name' => 'Trimmedname']);
        $other = User::factory()->create();

        $this->assertIndexIds($admin, ['q' => '  trimmedname  '], [$target->id]);
        $this->assertIndexIds($admin, ['q' => '   '], [$other->id, $target->id, $admin->id]);
    }

    public function test_search_composes_with_player_filters(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@admin.test']);
        $withPlayer = User::factory()->create(['name' => 'Robert Player', 'email' => 'robert.player@club.test']);
        Player::factory()->for($withPlayer)->create(['nickname' => null]);
        $withoutPlayer = User::factory()->create(['name' => 'Robert Solo', 'email' => 'robert.solo@club.test']);
        $otherWithPlayer = User::factory()->create(['name' => 'Other', 'email' => 'other@club.test']);
        Player::factory()->for($otherWithPlayer)->create(['nickname' => null]);

        $this->assertIndexIds(
            $admin,
            ['q' => 'robert', 'player_filter' => 'with_player'],
            [$withPlayer->id],
        );
        $this->assertIndexIds(
            $admin,
            ['q' => 'club.test', 'player_filter' => 'without_player'],
            [$withoutPlayer->id],
        );
        $this->assertIndexIds(
            $admin,
            ['q' => 'club.test', 'player_filter' => 'all'],
            [$otherWithPlayer->id, $withoutPlayer->id, $withPlayer->id],
        );
        $this->assertIndexIds(
            $admin,
            ['player_filter' => 'without_player'],
            [$withoutPlayer->id, $admin->id],
        );
    }

    public function test_like_wildcards_in_search_are_treated_literally(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@admin.test']);
        User::factory()->create(['name' => 'Plain', 'email' => 'plain@search.test']);
        $underscore = User::factory()->create(['name' => 'Under', 'email' => 'under_score@search.test']);

        $this->assertIndexIds($admin, ['q' => '%'], []);
        $this->assertIndexIds($admin, ['q' => '_'], [$underscore->id]);
    }

    public function test_search_applies_to_the_whole_dataset_and_pagination_keeps_filters(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@admin.test']);
        $matching = User::factory()
            ->count(17)
            ->sequence(fn ($sequence) => ['email' => "pagetoken{$sequence->index}@search.test"])
            ->create();
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'q' => 'pagetoken',
            'player_filter' => 'without_player',
        ]));

        $users = $response->assertOk()->viewData('users');
        $this->assertSame(17, $users->total());
        $nextPageUrl = $users->nextPageUrl();
        $this->assertStringContainsString('q=pagetoken', $nextPageUrl);
        $this->assertStringContainsString('player_filter=without_player', $nextPageUrl);
        $response->assertSee(e($nextPageUrl), false);

        $secondPage = $this->actingAs($admin)
            ->get($nextPageUrl)
            ->assertOk()
            ->viewData('users');

        $this->assertEqualsCanonicalizing(
            $matching->pluck('id')->sortDesc()->slice(15)->values()->all(),
            $secondPage->pluck('id')->all(),
        );
    }

    public function test_form_keeps_search_and_filter_and_shows_clear_only_when_active(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('name="q"', false)
            ->assertSee('placeholder="Nombre, apellidos, email o apodo"', false)
            ->assertSee('name="player_filter"', false)
            ->assertDontSee('Limpiar');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'algo "raro"']))
            ->assertOk()
            ->assertSee('value="algo &quot;raro&quot;"', false)
            ->assertSee('Limpiar');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['player_filter' => 'with_player']))
            ->assertOk()
            ->assertSee('<option value="with_player" selected>', false)
            ->assertSee('Limpiar');
    }

    public function test_empty_result_under_active_criteria_does_not_claim_there_are_no_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'no-such-user-anywhere']))
            ->assertOk()
            ->assertSee('No se han encontrado usuarios con los criterios indicados.')
            ->assertDontSee('No hay usuarios disponibles.');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['player_filter' => 'with_player']))
            ->assertOk()
            ->assertSee('No se han encontrado usuarios con los criterios indicados.')
            ->assertDontSee('No hay usuarios disponibles.');
    }

    /**
     * @param  array<string, string>  $query
     * @param  list<int>  $expectedIds
     */
    private function assertIndexIds(User $admin, array $query, array $expectedIds): void
    {
        $users = $this->actingAs($admin)
            ->get(route('admin.users.index', $query))
            ->assertOk()
            ->viewData('users');

        $this->assertSame($expectedIds, $users->pluck('id')->all());
    }
}
