<?php

namespace Tests\Feature;

use App\Enums\ChampionshipRegistrationStatus;
use App\Enums\ChampionshipStatus;
use App\Enums\SeasonStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
use App\Models\User;
use App\Services\Ranking\BuildAllTimeRankingService;
use App\Services\Ranking\BuildChampionshipRankingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompetitionPublicVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_scopes_and_instance_methods_apply_the_same_hierarchy_without_global_scopes(): void
    {
        $publicSeasons = collect([
            SeasonStatus::PLANNED,
            SeasonStatus::ACTIVE,
            SeasonStatus::FINISHED,
            SeasonStatus::CANCELLED,
        ])->map(fn (SeasonStatus $status) => Season::factory()->publiclyVisible()->create([
            'status' => $status->value,
        ]));
        $privateSeason = Season::factory()->privatelyVisible()->create();

        $publicChampionship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $publicSeasons->first()->id,
            'status' => ChampionshipStatus::PENDING->value,
        ]);
        $privateChampionship = Championship::factory()->privatelyVisible()->create([
            'season_id' => $publicSeasons->first()->id,
        ]);
        $publicChampionshipUnderPrivateSeason = Championship::factory()->publiclyVisible()->create([
            'season_id' => $privateSeason->id,
        ]);

        $publicCategory = Category::factory()->publiclyVisible()->create([
            'championship_id' => $publicChampionship->id,
            'status' => 'pending',
        ]);
        $privateCategory = Category::factory()->privatelyVisible()->create([
            'championship_id' => $publicChampionship->id,
        ]);
        $publicCategoryUnderPrivateChampionship = Category::factory()->publiclyVisible()->create([
            'championship_id' => $privateChampionship->id,
        ]);
        $publicCategoryUnderPrivateSeason = Category::factory()->publiclyVisible()->create([
            'championship_id' => $publicChampionshipUnderPrivateSeason->id,
        ]);

        $this->assertEqualsCanonicalizing(
            $publicSeasons->pluck('id')->all(),
            Season::query()->effectivelyPublic()->pluck('id')->all()
        );
        $this->assertSame(
            [$publicChampionship->id],
            Championship::query()->effectivelyPublic()->pluck('id')->all()
        );
        $this->assertSame(
            [$publicCategory->id],
            Category::query()->effectivelyPublic()->pluck('id')->all()
        );

        foreach ($publicSeasons as $season) {
            $this->assertTrue($season->isEffectivelyPublic());
        }

        $this->assertFalse($privateSeason->isEffectivelyPublic());
        $this->assertTrue($publicChampionship->isEffectivelyPublic());
        $this->assertFalse($privateChampionship->isEffectivelyPublic());
        $this->assertFalse($publicChampionshipUnderPrivateSeason->isEffectivelyPublic());
        $this->assertTrue($publicCategory->isEffectivelyPublic());
        $this->assertFalse($privateCategory->isEffectivelyPublic());
        $this->assertFalse($publicCategoryUnderPrivateChampionship->isEffectivelyPublic());
        $this->assertFalse($publicCategoryUnderPrivateSeason->isEffectivelyPublic());

        $this->assertNotNull(Season::query()->find($privateSeason->id));
        $this->assertNotNull(Championship::query()->find($privateChampionship->id));
        $this->assertNotNull(Category::query()->find($privateCategory->id));
    }

    public function test_season_listing_filters_roots_and_nested_relations_without_using_operational_status(): void
    {
        $newerSeason = Season::factory()->publiclyVisible()->create([
            'name' => 'Temporada planificada pública',
            'status' => SeasonStatus::PLANNED->value,
            'start_date' => '2028-01-01',
        ]);
        $olderSeason = Season::factory()->publiclyVisible()->create([
            'name' => 'Temporada finalizada pública',
            'status' => SeasonStatus::FINISHED->value,
            'start_date' => '2027-01-01',
        ]);
        $privateSeason = Season::factory()->privatelyVisible()->create([
            'name' => 'Temporada privada',
            'start_date' => '2029-01-01',
        ]);

        $publicChampionship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $newerSeason->id,
            'name' => 'Campeonato visible',
        ]);
        Championship::factory()->privatelyVisible()->create([
            'season_id' => $newerSeason->id,
            'name' => 'Campeonato privado',
        ]);
        Championship::factory()->publiclyVisible()->create([
            'season_id' => $privateSeason->id,
            'name' => 'Campeonato oculto por temporada',
        ]);
        Category::factory()->publiclyVisible()->create([
            'championship_id' => $publicChampionship->id,
            'name' => 'Categoría visible',
        ]);
        Category::factory()->privatelyVisible()->create([
            'championship_id' => $publicChampionship->id,
            'name' => 'Categoría privada',
        ]);

        $response = $this->getJson('/api/v1/seasons')->assertOk();

        $this->assertSame(
            [$newerSeason->id, $olderSeason->id],
            collect($response->json('data'))->pluck('id')->all()
        );
        $response
            ->assertJsonPath('data.0.status', SeasonStatus::PLANNED->value)
            ->assertJsonPath('data.0.championships.0.id', $publicChampionship->id)
            ->assertJsonPath('data.0.championships.0.categories_count', 1)
            ->assertJsonPath('data.1.status', SeasonStatus::FINISHED->value)
            ->assertJsonMissingPath('data.0.is_public')
            ->assertJsonMissingPath('data.0.championships.0.is_public');

        $this->assertStringNotContainsString('Campeonato privado', $response->getContent());
        $this->assertStringNotContainsString('Campeonato oculto por temporada', $response->getContent());
        $this->assertStringNotContainsString('"is_public"', $response->getContent());
    }

    public function test_championship_listing_filters_effective_visibility_before_existing_filters_and_detail_returns_404(): void
    {
        $publicSeason = Season::factory()->publiclyVisible()->create();
        $privateSeason = Season::factory()->privatelyVisible()->create();
        $pending = Championship::factory()->publiclyVisible()->create([
            'season_id' => $publicSeason->id,
            'name' => 'Pending público',
            'type' => 'singles',
            'status' => ChampionshipStatus::PENDING->value,
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'registration_starts_at' => now()->subDay(),
            'registration_ends_at' => now()->addDay(),
        ]);
        $active = Championship::factory()->publiclyVisible()->create([
            'season_id' => $publicSeason->id,
            'name' => 'Active público',
            'type' => 'doubles',
            'status' => ChampionshipStatus::ACTIVE->value,
        ]);
        $finished = Championship::factory()->publiclyVisible()->create([
            'season_id' => $publicSeason->id,
            'name' => 'Finished público',
            'type' => 'singles',
            'status' => ChampionshipStatus::FINISHED->value,
        ]);
        $cancelled = Championship::factory()->publiclyVisible()->create([
            'season_id' => $publicSeason->id,
            'name' => 'Cancelled público',
            'type' => 'singles',
            'status' => ChampionshipStatus::CANCELLED->value,
        ]);
        $privateChampionship = Championship::factory()->privatelyVisible()->create([
            'season_id' => $publicSeason->id,
        ]);
        $hiddenBySeason = Championship::factory()->publiclyVisible()->create([
            'season_id' => $privateSeason->id,
        ]);
        $publicCategory = Category::factory()->publiclyVisible()->create([
            'championship_id' => $cancelled->id,
            'name' => 'Categoría pública',
        ]);
        Category::factory()->privatelyVisible()->create([
            'championship_id' => $cancelled->id,
            'name' => 'Categoría privada',
        ]);

        $response = $this->getJson('/api/v1/championships')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        foreach ([$pending, $active, $finished, $cancelled] as $championship) {
            $this->assertTrue($ids->contains($championship->id));
        }

        $this->assertFalse($ids->contains($privateChampionship->id));
        $this->assertFalse($ids->contains($hiddenBySeason->id));
        $this->assertStringNotContainsString('"is_public"', $response->getContent());

        $cancelledListItem = collect($response->json('data'))->firstWhere('id', $cancelled->id);
        $this->assertSame([$publicCategory->id], collect($cancelledListItem['categories'])->pluck('id')->all());

        $this->getJson('/api/v1/championships?status=cancelled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cancelled->id);
        $this->getJson('/api/v1/championships?type=doubles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
        $this->getJson('/api/v1/championships?season_id='.$publicSeason->id)
            ->assertOk()
            ->assertJsonCount(4, 'data');
        $this->getJson('/api/v1/championships?registration_open=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id);

        $this->getJson('/api/v1/championships/'.$cancelled->id)
            ->assertOk()
            ->assertJsonPath('data.status', ChampionshipStatus::CANCELLED->value)
            ->assertJsonPath('data.categories.0.id', $publicCategory->id)
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonMissingPath('data.is_public');
        $this->getJson('/api/v1/championships/'.$privateChampionship->id)->assertNotFound();
        $this->getJson('/api/v1/championships/'.$hiddenBySeason->id)->assertNotFound();
    }

    public function test_category_standings_schedule_and_matches_require_a_complete_public_branch(): void
    {
        [$publicSeason, $publicChampionship, $publicCategory] = $this->createPublicBranch();
        [$publicMatch] = $this->createValidatedMatch($publicCategory, 'Visible A', 'Visible B');

        $privateCategory = Category::factory()->privatelyVisible()->create([
            'championship_id' => $publicChampionship->id,
        ]);
        [$privateCategoryMatch] = $this->createValidatedMatch($privateCategory, 'Privada A', 'Privada B');

        $privateChampionship = Championship::factory()->privatelyVisible()->create([
            'season_id' => $publicSeason->id,
        ]);
        $hiddenByChampionship = Category::factory()->publiclyVisible()->create([
            'championship_id' => $privateChampionship->id,
        ]);
        [$privateChampionshipMatch] = $this->createValidatedMatch(
            $hiddenByChampionship,
            'Campeonato A',
            'Campeonato B'
        );

        $privateSeason = Season::factory()->privatelyVisible()->create();
        $championshipUnderPrivateSeason = Championship::factory()->publiclyVisible()->create([
            'season_id' => $privateSeason->id,
        ]);
        $hiddenBySeason = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championshipUnderPrivateSeason->id,
        ]);
        [$privateSeasonMatch] = $this->createValidatedMatch($hiddenBySeason, 'Temporada A', 'Temporada B');

        $this->getJson('/api/v1/categories/'.$publicCategory->id)
            ->assertOk()
            ->assertJsonPath('data.id', $publicCategory->id)
            ->assertJsonMissingPath('data.is_public')
            ->assertJsonMissingPath('data.description')
            ->assertJsonMissingPath('data.image_path');
        $this->getJson('/api/v1/categories/'.$publicCategory->id.'/standings')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/categories/'.$publicCategory->id.'/schedule')
            ->assertOk()
            ->assertJsonPath('data.0.matches.0.id', $publicMatch->id);
        $this->getJson('/api/v1/matches/'.$publicMatch->id)
            ->assertOk()
            ->assertJsonPath('data.id', $publicMatch->id)
            ->assertJsonMissingPath('data.is_public');

        foreach ([$privateCategory, $hiddenByChampionship, $hiddenBySeason] as $category) {
            $this->getJson('/api/v1/categories/'.$category->id)->assertNotFound();
            $this->getJson('/api/v1/categories/'.$category->id.'/standings')->assertNotFound();
            $this->getJson('/api/v1/categories/'.$category->id.'/schedule')->assertNotFound();
        }

        foreach ([$privateCategoryMatch, $privateChampionshipMatch, $privateSeasonMatch] as $match) {
            $this->getJson('/api/v1/matches/'.$match->id)->assertNotFound();
            $this->assertFalse($match->isEffectivelyPublic());
        }

        $this->assertTrue($publicMatch->isEffectivelyPublic());
        $this->assertSame(
            [$publicMatch->id],
            GameMatch::query()->effectivelyPublic()->pluck('id')->all()
        );
    }

    public function test_public_rankings_exclude_private_results_while_internal_calculations_keep_them(): void
    {
        $publicSeason = Season::factory()->publiclyVisible()->create();
        $publicChampionship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $publicSeason->id,
            'status' => ChampionshipStatus::CANCELLED->value,
        ]);
        $publicCategory = Category::factory()->publiclyVisible()->create([
            'championship_id' => $publicChampionship->id,
        ]);
        $privateCategory = Category::factory()->privatelyVisible()->create([
            'championship_id' => $publicChampionship->id,
        ]);

        $privateChampionship = Championship::factory()->privatelyVisible()->create([
            'season_id' => $publicSeason->id,
        ]);
        $categoryUnderPrivateChampionship = Category::factory()->publiclyVisible()->create([
            'championship_id' => $privateChampionship->id,
        ]);

        $privateSeason = Season::factory()->privatelyVisible()->create();
        $championshipUnderPrivateSeason = Championship::factory()->publiclyVisible()->create([
            'season_id' => $privateSeason->id,
        ]);
        $categoryUnderPrivateSeason = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championshipUnderPrivateSeason->id,
        ]);

        [, $publicPlayer] = $this->createValidatedMatch($publicCategory, 'Ranking público', 'Rival público');
        [, $privateCategoryPlayer] = $this->createValidatedMatch(
            $privateCategory,
            'Ranking categoría privada',
            'Rival categoría privada'
        );
        [, $privateChampionshipPlayer] = $this->createValidatedMatch(
            $categoryUnderPrivateChampionship,
            'Ranking campeonato privado',
            'Rival campeonato privado'
        );
        [, $privateSeasonPlayer] = $this->createValidatedMatch(
            $categoryUnderPrivateSeason,
            'Ranking temporada privada',
            'Rival temporada privada'
        );

        $championshipResponse = $this->getJson(
            '/api/v1/championships/'.$publicChampionship->id.'/ranking'
        )->assertOk();
        $championshipIds = collect($championshipResponse->json('data'))->pluck('player_id');
        $this->assertTrue($championshipIds->contains($publicPlayer->id));
        $this->assertFalse($championshipIds->contains($privateCategoryPlayer->id));

        $seasonResponse = $this->getJson('/api/v1/seasons/'.$publicSeason->id.'/ranking')->assertOk();
        $seasonIds = collect($seasonResponse->json('data'))->pluck('player_id');
        $this->assertTrue($seasonIds->contains($publicPlayer->id));
        $this->assertFalse($seasonIds->contains($privateCategoryPlayer->id));
        $this->assertFalse($seasonIds->contains($privateChampionshipPlayer->id));

        $this->getJson('/api/v1/championships/'.$privateChampionship->id.'/ranking')->assertNotFound();
        $this->getJson('/api/v1/seasons/'.$privateSeason->id.'/ranking')->assertNotFound();

        $allTimeResponse = $this->getJson('/api/v1/rankings/all-time')->assertOk();
        $allTimeIds = collect($allTimeResponse->json('data'))->pluck('player_id');
        $this->assertTrue($allTimeIds->contains($publicPlayer->id));
        $this->assertFalse($allTimeIds->contains($privateCategoryPlayer->id));
        $this->assertFalse($allTimeIds->contains($privateChampionshipPlayer->id));
        $this->assertFalse($allTimeIds->contains($privateSeasonPlayer->id));

        $internalAllTimeIds = app(BuildAllTimeRankingService::class)->build()->pluck('player_id');
        $this->assertTrue($internalAllTimeIds->contains($privateCategoryPlayer->id));
        $this->assertTrue($internalAllTimeIds->contains($privateChampionshipPlayer->id));
        $this->assertTrue($internalAllTimeIds->contains($privateSeasonPlayer->id));

        $internalChampionshipIds = app(BuildChampionshipRankingService::class)
            ->build($publicChampionship)
            ->pluck('player_id');
        $this->assertTrue($internalChampionshipIds->contains($privateCategoryPlayer->id));
    }

    public function test_new_registrations_require_public_competitions_and_existing_requests_remain_in_my_panel(): void
    {
        $publicSeason = Season::factory()->publiclyVisible()->create();
        $publicChampionship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $publicSeason->id,
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'registration_starts_at' => now()->subDay(),
            'registration_ends_at' => now()->addDay(),
        ]);
        $publicCategory = Category::factory()->publiclyVisible()->create([
            'championship_id' => $publicChampionship->id,
        ]);
        $privateCategory = Category::factory()->privatelyVisible()->create([
            'championship_id' => $publicChampionship->id,
        ]);

        $player = Player::factory()->create();
        Sanctum::actingAs($player->user);
        $this->postJson('/api/v1/championships/'.$publicChampionship->id.'/register', [
            'suggested_category_id' => $publicCategory->id,
        ])->assertOk();

        $request = ChampionshipRegistrationRequest::query()
            ->where('player_id', $player->id)
            ->sole();

        $otherPlayer = Player::factory()->create();
        Sanctum::actingAs($otherPlayer->user);
        $this->postJson('/api/v1/championships/'.$publicChampionship->id.'/register', [
            'suggested_category_id' => $privateCategory->id,
        ])->assertNotFound();
        $this->assertDatabaseMissing('championship_registration_requests', [
            'player_id' => $otherPlayer->id,
            'championship_id' => $publicChampionship->id,
        ]);

        $privateChampionship = Championship::factory()->privatelyVisible()->create([
            'season_id' => $publicSeason->id,
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
        ]);
        $privateSeason = Season::factory()->privatelyVisible()->create();
        $hiddenBySeason = Championship::factory()->publiclyVisible()->create([
            'season_id' => $privateSeason->id,
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
        ]);

        $this->postJson('/api/v1/championships/'.$privateChampionship->id.'/register')->assertNotFound();
        $this->getJson('/api/v1/championships/'.$privateChampionship->id.'/registration')->assertNotFound();
        $this->postJson('/api/v1/championships/'.$hiddenBySeason->id.'/register')->assertNotFound();

        $publicSeason->is_public = false;
        $publicSeason->save();

        Sanctum::actingAs($player->user);
        $this->getJson('/api/v1/championships/'.$publicChampionship->id.'/registration')->assertNotFound();
        $this->getJson('/api/v1/me/championship-registrations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $request->id)
            ->assertJsonPath('data.0.championship.id', $publicChampionship->id);

        $this->assertDatabaseHas('championship_registration_requests', ['id' => $request->id]);
    }

    public function test_private_competition_remains_available_to_related_users_in_my_panel_and_workflows(): void
    {
        $privateSeason = Season::factory()->privatelyVisible()->create();
        $privateChampionship = Championship::factory()->privatelyVisible()->create([
            'season_id' => $privateSeason->id,
            'type' => 'singles',
        ]);
        $privateCategory = Category::factory()->privatelyVisible()->create([
            'championship_id' => $privateChampionship->id,
        ]);
        [$match, $homePlayer] = $this->createValidatedMatch(
            $privateCategory,
            'Mi jugador privado',
            'Rival privado',
            status: 'scheduled'
        );

        Sanctum::actingAs($homePlayer->user);
        $this->getJson('/api/v1/me/matches')
            ->assertOk()
            ->assertJsonPath('data.0.id', $match->id);
        $this->getJson('/api/v1/me/calendar')
            ->assertOk()
            ->assertJsonPath('data.0.matches.0.id', $match->id);
        $this->getJson('/api/v1/me/rankings')
            ->assertOk()
            ->assertJsonPath('data.0.category.id', $privateCategory->id);
        $this->getJson('/api/v1/matches/'.$match->id.'/workflow')
            ->assertOk()
            ->assertJsonPath('data.workflow.participates', true);
        $this->postJson('/api/v1/matches/'.$match->id.'/submit-result', [
            'home_score' => 10,
            'away_score' => 7,
        ])->assertOk();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/matches/'.$match->id.'/workflow')->assertNotFound();
    }

    public function test_administration_keeps_access_to_private_entities_without_applying_public_scopes(): void
    {
        $season = Season::factory()->privatelyVisible()->create();
        $championship = Championship::factory()->privatelyVisible()->create([
            'season_id' => $season->id,
        ]);
        $category = Category::factory()->privatelyVisible()->create([
            'championship_id' => $championship->id,
        ]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/admin/seasons/'.$season->id)
            ->assertOk()
            ->assertJsonPath('id', $season->id)
            ->assertJsonMissingPath('is_public');
        $this->getJson('/api/v1/admin/championships/'.$championship->id)
            ->assertOk()
            ->assertJsonPath('id', $championship->id)
            ->assertJsonMissingPath('is_public');
        $this->getJson('/api/v1/admin/categories/'.$category->id)
            ->assertOk()
            ->assertJsonPath('id', $category->id)
            ->assertJsonMissingPath('is_public');
    }

    public function test_hiding_and_restoring_parents_changes_effective_visibility_without_cascading_flags(): void
    {
        [$season, $championship, $category] = $this->createPublicBranch();
        [$match] = $this->createValidatedMatch($category, 'Restaurable A', 'Restaurable B');

        $this->assertPublicBranchIsVisible($championship, $category, $match);

        $season->is_public = false;
        $season->save();
        $this->getJson('/api/v1/championships/'.$championship->id)->assertNotFound();
        $this->getJson('/api/v1/categories/'.$category->id)->assertNotFound();
        $this->getJson('/api/v1/matches/'.$match->id)->assertNotFound();
        $this->assertTrue($championship->fresh()->is_public);
        $this->assertTrue($category->fresh()->is_public);

        $season->is_public = true;
        $season->save();
        $this->assertPublicBranchIsVisible($championship, $category, $match);

        $championship->is_public = false;
        $championship->save();
        $this->getJson('/api/v1/categories/'.$category->id)->assertNotFound();
        $this->getJson('/api/v1/matches/'.$match->id)->assertNotFound();
        $this->assertTrue($category->fresh()->is_public);

        $championship->is_public = true;
        $championship->save();
        $this->assertPublicBranchIsVisible($championship, $category, $match);

        $category->is_public = false;
        $category->save();
        $this->getJson('/api/v1/categories/'.$category->id)->assertNotFound();
        $this->getJson('/api/v1/matches/'.$match->id)->assertNotFound();

        $category->is_public = true;
        $category->save();
        $this->assertPublicBranchIsVisible($championship, $category, $match);
    }

    public function test_database_seeder_explicitly_creates_the_intended_public_hierarchy(): void
    {
        $this->seed(DatabaseSeeder::class);

        $season = Season::query()->sole();
        $this->assertTrue($season->is_public);
        $this->assertCount(2, $season->championships);
        $this->assertTrue($season->championships->every(fn (Championship $item) => $item->is_public));
        $this->assertSame(4, Category::query()->count());
        $this->assertSame(4, Category::query()->where('is_public', true)->count());

        $this->assertFalse(Season::factory()->create()->is_public);
        $this->assertFalse(Championship::factory()->create()->is_public);
        $this->assertFalse(Category::factory()->create()->is_public);
    }

    /**
     * @return array{Season, Championship, Category}
     */
    private function createPublicBranch(): array
    {
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
        ]);
        $category = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);

        return [$season, $championship, $category];
    }

    /**
     * @return array{GameMatch, Player, Player}
     */
    private function createValidatedMatch(
        Category $category,
        string $homeNickname,
        string $awayNickname,
        string $status = 'validated'
    ): array {
        $round = Round::factory()->create([
            'category_id' => $category->id,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);
        $homePlayer = Player::factory()->create(['nickname' => $homeNickname]);
        $awayPlayer = Player::factory()->create(['nickname' => $awayNickname]);
        $homeEntry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $homePlayer->id,
            'status' => 'approved',
        ]);
        $awayEntry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $awayPlayer->id,
            'status' => 'approved',
        ]);
        $match = GameMatch::factory()->create([
            'round_id' => $round->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'scheduled_date' => '2028-03-01 18:00:00',
            'status' => $status,
            'home_score' => $status === 'validated' ? 10 : null,
            'away_score' => $status === 'validated' ? 7 : null,
            'winner_entry_id' => $status === 'validated' ? $homeEntry->id : null,
        ]);

        return [$match, $homePlayer, $awayPlayer];
    }

    private function assertPublicBranchIsVisible(
        Championship $championship,
        Category $category,
        GameMatch $match
    ): void {
        $this->getJson('/api/v1/championships/'.$championship->id)->assertOk();
        $this->getJson('/api/v1/categories/'.$category->id)->assertOk();
        $this->getJson('/api/v1/matches/'.$match->id)->assertOk();
    }
}
