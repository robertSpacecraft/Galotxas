<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Round;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Services\GenerateLeagueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GenerateLeagueScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_without_venues_fails_without_creating_rounds_or_matches(): void
    {
        $category = $this->createCategoryWithEntries('singles', 4);

        $this->assertGenerationFails(
            $category,
            'No hay pistas configuradas. Crea al menos una pista desde el panel de administración antes de generar la liga.'
        );

        $this->assertNoLeagueDataExists($category);
    }

    public function test_admin_receives_an_actionable_error_when_no_venues_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->createCategoryWithEntries('singles', 4);

        $response = $this->actingAs($admin)
            ->from(route('admin.categories.show', $category))
            ->post(route('admin.categories.generate-league', $category));

        $response->assertRedirect(route('admin.categories.show', $category));
        $response->assertSessionHas(
            'error',
            'No hay pistas configuradas. Crea al menos una pista desde el panel de administración antes de generar la liga.'
        );
        $this->assertNoLeagueDataExists($category);
    }

    public function test_generation_uses_custom_venues_with_non_consecutive_ids_in_stable_order(): void
    {
        Venue::factory()->count(5)->create()->each->delete();

        $firstVenue = Venue::factory()->create(['name' => 'Frontó Blau']);
        Venue::factory()->create(['name' => 'Pista temporal'])->delete();
        $secondVenue = Venue::factory()->create(['name' => 'Cancha del Nord']);

        $category = $this->createCategoryWithEntries('singles', 4);

        $this->service()->generate($category);

        $configuredVenueIds = [$firstVenue->id, $secondVenue->id];
        $usedVenueIds = $this->leagueMatches($category)
            ->pluck('venue_id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $firstRoundVenueIds = Round::query()
            ->where('category_id', $category->id)
            ->where('type', 'league')
            ->orderBy('order')
            ->firstOrFail()
            ->matches()
            ->orderBy('id')
            ->pluck('venue_id')
            ->all();

        $this->assertSame($configuredVenueIds, $usedVenueIds);
        $this->assertSame($configuredVenueIds, $firstRoundVenueIds);
        $this->assertGreaterThan(5, $firstVenue->id);
        $this->assertGreaterThan($firstVenue->id + 1, $secondVenue->id);
    }

    public function test_one_venue_is_reused_only_in_distinct_time_slots(): void
    {
        $venue = Venue::factory()->create(['name' => 'Pista única personalizada']);
        $category = $this->createCategoryWithEntries('singles', 6);

        $this->service()->generate($category);

        $matches = $this->leagueMatches($category);
        $slotKeys = $matches->map(
            fn (GameMatch $match): string => $match->venue_id.'|'.$match->scheduled_date->format('Y-m-d H:i:s')
        );

        $this->assertCount(15, $matches);
        $this->assertSame([$venue->id], $matches->pluck('venue_id')->unique()->values()->all());
        $this->assertCount($matches->count(), $slotKeys->unique());
    }

    public function test_insufficient_venues_fail_atomically_without_partial_rounds_or_matches(): void
    {
        Venue::factory()->create(['name' => 'Una sola pista']);
        $category = $this->createCategoryWithEntries('singles', 16);

        $this->assertGenerationFails(
            $category,
            'No hay suficientes pistas configuradas para programar la jornada 1 sin colisiones en los horarios disponibles.'
        );

        $this->assertNoLeagueDataExists($category);
    }

    public function test_singles_with_an_odd_number_of_entries_keep_rounds_matches_and_byes(): void
    {
        Venue::factory()->create(['name' => 'Singles central']);
        $category = $this->createCategoryWithEntries('singles', 5);

        $this->service()->generate($category);

        $rounds = Round::query()
            ->where('category_id', $category->id)
            ->where('type', 'league')
            ->withCount('matches')
            ->orderBy('order')
            ->get();

        $appearancesByEntry = $this->leagueMatches($category)
            ->flatMap(fn (GameMatch $match): array => [$match->home_entry_id, $match->away_entry_id])
            ->countBy();

        $this->assertCount(5, $rounds);
        $this->assertSame([2, 2, 2, 2, 2], $rounds->pluck('matches_count')->all());
        $this->assertSame(10, $this->leagueMatches($category)->count());
        $this->assertSame([4, 4, 4, 4, 4], $appearancesByEntry->sort()->values()->all());
    }

    public function test_doubles_keep_round_robin_and_use_only_configured_venues(): void
    {
        $firstVenue = Venue::factory()->create(['name' => 'Dobles A']);
        $secondVenue = Venue::factory()->create(['name' => 'Dobles B']);
        $category = $this->createCategoryWithEntries('doubles', 4, 1);

        $this->service()->generate($category);

        $rounds = Round::query()
            ->where('category_id', $category->id)
            ->where('type', 'league')
            ->withCount('matches')
            ->orderBy('order')
            ->get();
        $usedVenueIds = $this->leagueMatches($category)
            ->pluck('venue_id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertCount(3, $rounds);
        $this->assertSame([2, 2, 2], $rounds->pluck('matches_count')->all());
        $this->assertSame(6, $this->leagueMatches($category)->count());
        $this->assertSame([$firstVenue->id, $secondVenue->id], $usedVenueIds);
    }

    public function test_generation_still_rejects_regeneration_without_adding_data(): void
    {
        Venue::factory()->create(['name' => 'Pista regeneración']);
        $category = $this->createCategoryWithEntries('singles', 4);

        $this->service()->generate($category);

        $roundCount = Round::query()->where('category_id', $category->id)->count();
        $matchCount = $this->leagueMatches($category)->count();

        $this->assertGenerationFails($category, 'La categoría ya tiene jornadas de liga generadas.');

        $this->assertSame($roundCount, Round::query()->where('category_id', $category->id)->count());
        $this->assertSame($matchCount, $this->leagueMatches($category)->count());
    }

    private function createCategoryWithEntries(string $type, int $entryCount, int $level = 2): Category
    {
        $championship = Championship::factory()->create([
            'type' => $type,
            'start_date' => '2026-07-03',
        ]);
        $category = Category::factory()->create([
            'championship_id' => $championship->id,
            'level' => $level,
        ]);

        if ($type === 'doubles') {
            for ($i = 0; $i < $entryCount; $i++) {
                $team = Team::factory()->create(['category_id' => $category->id]);

                CategoryEntry::factory()->create([
                    'category_id' => $category->id,
                    'entry_type' => 'team',
                    'player_id' => null,
                    'team_id' => $team->id,
                    'status' => 'approved',
                ]);
            }

            return $category;
        }

        CategoryEntry::factory()
            ->count($entryCount)
            ->playerEntry()
            ->create([
                'category_id' => $category->id,
                'status' => 'approved',
            ]);

        return $category;
    }

    private function service(): GenerateLeagueScheduleService
    {
        return app(GenerateLeagueScheduleService::class);
    }

    private function leagueMatches(Category $category)
    {
        return GameMatch::query()
            ->whereHas('round', function ($query) use ($category) {
                $query->where('category_id', $category->id)
                    ->where('type', 'league');
            })
            ->orderBy('id')
            ->get();
    }

    private function assertGenerationFails(Category $category, string $message): void
    {
        try {
            $this->service()->generate($category);
            $this->fail('La generación debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    private function assertNoLeagueDataExists(Category $category): void
    {
        $this->assertSame(
            0,
            Round::query()
                ->where('category_id', $category->id)
                ->where('type', 'league')
                ->count()
        );
        $this->assertCount(0, $this->leagueMatches($category));
    }
}
