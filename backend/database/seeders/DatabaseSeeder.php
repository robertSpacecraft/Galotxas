<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Users
        $adminUser = User::create([
            'name' => 'Admin Galotxas',
            'email' => 'admin@galotxas.com',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN->value,
        ]);

        $participantUser = User::create([
            'name' => 'Paco Pilotari',
            'email' => 'jugador@galotxas.com',
            'password' => Hash::make('password'),
            'role' => UserRole::USER->value,
        ]);

        // 2. Player profile linked to Paco's user
        $realPlayer = Player::factory()->create([
            'user_id' => $participantUser->id,
            'slug' => Str::slug('Paco Pilotari'),
            'dni' => '12345678A',
        ]);

        // 3. Dummy players (each one with its own user)
        $dummyPlayers = Player::factory()->count(10)->create();

        // 4. Venues
        $venues = Venue::factory()->count(3)->create();
        $venue1 = $venues[0];
        $venue2 = $venues[1];
        $venue3 = $venues[2];

        // 5. Season
        $season = Season::factory()->create([
            'name' => 'Temporada 2026',
            'status' => 'active',
        ]);

        // 6. Singles championship
        $singlesChamp = Championship::factory()->create([
            'season_id' => $season->id,
            'name' => 'Campionat Mà a Mà',
            'type' => 'singles',
        ]);

        $singlesCat1 = Category::factory()->create([
            'championship_id' => $singlesChamp->id,
            'name' => '1ª Categoría (Singles)',
            'slug' => Str::slug('1a-categoria-singles'),
            'level' => 1,
            'gender' => 'male',
        ]);

        // 7. Doubles championship
        $doublesChamp = Championship::factory()->create([
            'season_id' => $season->id,
            'name' => 'Campionat de Dobles',
            'type' => 'doubles',
        ]);

        $doublesCat1 = Category::factory()->create([
            'championship_id' => $doublesChamp->id,
            'name' => '1ª Categoría (Dobles)',
            'slug' => Str::slug('1a-categoria-dobles'),
            'level' => 1,
            'gender' => 'male',
        ]);

        // 8. Category entries - singles
        $entrySinglesPaco = CategoryEntry::create([
            'category_id' => $singlesCat1->id,
            'entry_type' => 'player',
            'player_id' => $realPlayer->id,
            'team_id' => null,
            'status' => 'approved',
        ]);

        $entrySinglesD1 = CategoryEntry::create([
            'category_id' => $singlesCat1->id,
            'entry_type' => 'player',
            'player_id' => $dummyPlayers[0]->id,
            'team_id' => null,
            'status' => 'approved',
        ]);

        $entrySinglesD2 = CategoryEntry::create([
            'category_id' => $singlesCat1->id,
            'entry_type' => 'player',
            'player_id' => $dummyPlayers[1]->id,
            'team_id' => null,
            'status' => 'approved',
        ]);

        $entrySinglesD3 = CategoryEntry::create([
            'category_id' => $singlesCat1->id,
            'entry_type' => 'player',
            'player_id' => $dummyPlayers[2]->id,
            'team_id' => null,
            'status' => 'approved',
        ]);

        // 9. Teams for doubles
        $teamPaco = Team::factory()->create([
            'name' => 'Equip Paco i Ficti1',
        ]);
        $teamPaco->players()->attach([$realPlayer->id, $dummyPlayers[3]->id]);

        $teamB = Team::factory()->create([
            'name' => 'Equip Ficti 2 i 3',
        ]);
        $teamB->players()->attach([$dummyPlayers[4]->id, $dummyPlayers[5]->id]);

        // 10. Category entries - doubles
        $entryDoublesPaco = CategoryEntry::create([
            'category_id' => $doublesCat1->id,
            'entry_type' => 'team',
            'player_id' => null,
            'team_id' => $teamPaco->id,
            'status' => 'approved',
        ]);

        $entryDoublesB = CategoryEntry::create([
            'category_id' => $doublesCat1->id,
            'entry_type' => 'team',
            'player_id' => null,
            'team_id' => $teamB->id,
            'status' => 'approved',
        ]);

        // 11. Singles rounds
        $sRound1 = Round::create([
            'category_id' => $singlesCat1->id,
            'name' => 'Jornada 1',
            'order' => 1,
            'type' => 'league',
        ]);

        $sRound2 = Round::create([
            'category_id' => $singlesCat1->id,
            'name' => 'Jornada 2',
            'order' => 2,
            'type' => 'league',
        ]);

        $sRound3 = Round::create([
            'category_id' => $singlesCat1->id,
            'name' => 'Jornada 3',
            'order' => 3,
            'type' => 'league',
        ]);

        // 12. Singles matches
        GameMatch::create([
            'round_id' => $sRound1->id,
            'home_entry_id' => $entrySinglesPaco->id,
            'away_entry_id' => $entrySinglesD1->id,
            'venue_id' => $venue1->id,
            'scheduled_date' => Carbon::now()->subDays(10),
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 6,
            'submitted_by' => $participantUser->id,
            'validated_by' => $adminUser->id,
        ]);

        GameMatch::create([
            'round_id' => $sRound1->id,
            'home_entry_id' => $entrySinglesD2->id,
            'away_entry_id' => $entrySinglesD3->id,
            'venue_id' => $venue2->id,
            'scheduled_date' => Carbon::now()->subDays(10),
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 9,
            'submitted_by' => $adminUser->id,
            'validated_by' => $adminUser->id,
        ]);

        GameMatch::create([
            'round_id' => $sRound2->id,
            'home_entry_id' => $entrySinglesPaco->id,
            'away_entry_id' => $entrySinglesD2->id,
            'venue_id' => $venue3->id,
            'scheduled_date' => Carbon::now()->subDays(3),
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 2,
            'submitted_by' => $participantUser->id,
        ]);

        GameMatch::create([
            'round_id' => $sRound3->id,
            'home_entry_id' => $entrySinglesPaco->id,
            'away_entry_id' => $entrySinglesD3->id,
            'venue_id' => $venue1->id,
            'scheduled_date' => Carbon::now()->addDays(4),
            'status' => 'scheduled',
        ]);

        // 13. Doubles rounds
        $dRound1 = Round::create([
            'category_id' => $doublesCat1->id,
            'name' => 'Jornada 1',
            'order' => 1,
            'type' => 'league',
        ]);

        $dRound2 = Round::create([
            'category_id' => $doublesCat1->id,
            'name' => 'Jornada 2',
            'order' => 2,
            'type' => 'league',
        ]);

        // 14. Doubles matches
        GameMatch::create([
            'round_id' => $dRound1->id,
            'home_entry_id' => $entryDoublesPaco->id,
            'away_entry_id' => $entryDoublesB->id,
            'venue_id' => $venue2->id,
            'scheduled_date' => Carbon::now()->subDays(7),
            'status' => 'validated',
            'home_score' => 12,
            'away_score' => 8,
            'submitted_by' => $participantUser->id,
            'validated_by' => $adminUser->id,
        ]);

        GameMatch::create([
            'round_id' => $dRound2->id,
            'home_entry_id' => $entryDoublesB->id,
            'away_entry_id' => $entryDoublesPaco->id,
            'venue_id' => $venue3->id,
            'scheduled_date' => Carbon::now()->addDays(5),
            'status' => 'scheduled',
        ]);
    }
}
