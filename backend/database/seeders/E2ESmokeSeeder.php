<?php

namespace Database\Seeders;

use App\Enums\CategoryGender;
use App\Enums\ChampionshipRegistrationStatus;
use App\Enums\ChampionshipType;
use App\Enums\CmsBlockType;
use App\Enums\CmsPageStatus;
use App\Enums\GameMatchStatus;
use App\Enums\PlayerGender;
use App\Enums\SeasonStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryRegistration;
use App\Models\Championship;
use App\Models\CmsPage;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class E2ESmokeSeeder extends Seeder
{
    public const PASSWORD = 'E2E-password-123!';

    public function run(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (! app()->environment('e2e') || $database !== 'galotxas_e2e') {
            throw new RuntimeException(
                'E2ESmokeSeeder solo puede ejecutarse con APP_ENV=e2e sobre la base galotxas_e2e.'
            );
        }

        DB::transaction(function (): void {
            User::query()->updateOrCreate(
                ['email' => 'admin.e2e@example.test'],
                [
                    'name' => 'Admin',
                    'lastname' => 'E2E',
                    'password' => Hash::make(self::PASSWORD),
                    'role' => 'admin',
                    'active' => true,
                ]
            );

            $homeUser = $this->createPlayerUser(
                'player1.e2e@example.test',
                'Jugador',
                'Uno E2E',
                'Pilotari E2E 1',
                'pilotari-e2e-1',
                'E2E00001A',
                'E2E-LIC-001',
                'right'
            );
            $awayUser = $this->createPlayerUser(
                'player2.e2e@example.test',
                'Jugador',
                'Dos E2E',
                'Pilotari E2E 2',
                'pilotari-e2e-2',
                'E2E00002B',
                'E2E-LIC-002',
                'left'
            );

            $season = Season::query()->updateOrCreate(
                ['name' => 'Temporada E2E 2026'],
                [
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'status' => SeasonStatus::ACTIVE->value,
                ]
            );
            $season->is_public = true;
            $season->save();

            $championship = Championship::query()->updateOrCreate(
                ['slug' => 'campeonato-individual-e2e'],
                [
                    'season_id' => $season->id,
                    'name' => 'Campeonato Individual E2E',
                    'description' => 'Escenario aislado para las pruebas de humo E2E.',
                    'type' => ChampionshipType::SINGLES->value,
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-08-31',
                    'status' => 'active',
                    'registration_status' => ChampionshipRegistrationStatus::CLOSED->value,
                    'registration_starts_at' => null,
                    'registration_ends_at' => null,
                ]
            );
            $championship->is_public = true;
            $championship->save();

            $category = Category::query()->updateOrCreate(
                ['slug' => 'individual-e2e'],
                [
                    'championship_id' => $championship->id,
                    'name' => 'Individual E2E',
                    'level' => 5,
                    'gender' => CategoryGender::MALE->value,
                    'description' => 'Categoría determinista para Playwright.',
                    'status' => 'active',
                ]
            );
            $category->is_public = true;
            $category->save();

            $homePlayer = $homeUser->player;
            $awayPlayer = $awayUser->player;

            foreach ([$homePlayer, $awayPlayer] as $player) {
                CategoryRegistration::query()->updateOrCreate(
                    ['category_id' => $category->id, 'player_id' => $player->id],
                    ['status' => 'approved']
                );
            }

            $homeEntry = CategoryEntry::query()->updateOrCreate(
                ['category_id' => $category->id, 'player_id' => $homePlayer->id],
                ['entry_type' => 'player', 'team_id' => null, 'status' => 'approved']
            );
            $awayEntry = CategoryEntry::query()->updateOrCreate(
                ['category_id' => $category->id, 'player_id' => $awayPlayer->id],
                ['entry_type' => 'player', 'team_id' => null, 'status' => 'approved']
            );

            $venue = Venue::query()->updateOrCreate(
                ['name' => 'Pista E2E'],
                [
                    'location' => 'Entorno aislado Playwright',
                    'description' => 'No pertenece a los datos de desarrollo.',
                ]
            );

            $confirmationRound = $this->createRound($category, 'Jornada E2E Confirmación', 1);
            $reviewRound = $this->createRound($category, 'Jornada E2E Discrepancia', 2);

            $this->createMatch(
                $confirmationRound,
                $venue,
                $homeEntry,
                $awayEntry,
                '2026-08-01 18:00:00'
            );
            $this->createMatch(
                $reviewRound,
                $venue,
                $homeEntry,
                $awayEntry,
                '2026-08-02 18:00:00'
            );

            $page = CmsPage::query()->updateOrCreate(
                ['slug' => 'e2e-publicada'],
                [
                    'title' => 'Contenido E2E publicado',
                    'status' => CmsPageStatus::PUBLISHED->value,
                    'published_at' => '2026-01-01 10:00:00',
                    'seo_title' => 'Contenido E2E publicado',
                    'seo_description' => 'Página pública determinista del escenario Playwright.',
                ]
            );

            $page->blocks()->updateOrCreate(
                ['type' => CmsBlockType::HEADING->value, 'sort_order' => 10],
                ['data' => ['text' => 'Escenario público E2E', 'level' => 2]]
            );
            $page->blocks()->updateOrCreate(
                ['type' => CmsBlockType::TEXT->value, 'sort_order' => 20],
                ['data' => ['text' => 'Este contenido procede exclusivamente de la base temporal E2E.']]
            );
        });
    }

    private function createPlayerUser(
        string $email,
        string $name,
        string $lastname,
        string $nickname,
        string $slug,
        string $dni,
        string $licenseNumber,
        string $dominantHand
    ): User {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'lastname' => $lastname,
                'password' => Hash::make(self::PASSWORD),
                'role' => 'user',
                'active' => true,
            ]
        );

        Player::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'nickname' => $nickname,
                'slug' => $slug,
                'dni' => $dni,
                'birth_date' => '1990-01-01',
                'gender' => PlayerGender::MALE->value,
                'level' => 5,
                'license_number' => $licenseNumber,
                'dominant_hand' => $dominantHand,
                'notes' => 'Perfil exclusivo del escenario E2E.',
                'active' => true,
            ]
        );

        return $user->load('player');
    }

    private function createRound(Category $category, string $name, int $order): Round
    {
        return Round::query()->updateOrCreate(
            ['category_id' => $category->id, 'name' => $name],
            [
                'order' => $order,
                'type' => 'league',
                'phase' => 'league',
                'stage' => 'matchday',
            ]
        );
    }

    private function createMatch(
        Round $round,
        Venue $venue,
        CategoryEntry $homeEntry,
        CategoryEntry $awayEntry,
        string $scheduledDate
    ): void {
        GameMatch::query()->updateOrCreate(
            ['round_id' => $round->id],
            [
                'venue_id' => $venue->id,
                'home_entry_id' => $homeEntry->id,
                'away_entry_id' => $awayEntry->id,
                'scheduled_date' => $scheduledDate,
                'status' => GameMatchStatus::SCHEDULED->value,
                'home_score' => null,
                'away_score' => null,
                'winner_entry_id' => null,
                'submitted_by' => null,
                'validated_by' => null,
            ]
        );
    }
}
