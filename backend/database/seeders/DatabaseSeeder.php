<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Player;
use App\Models\Venue;
use App\Models\Season;
use App\Models\Championship;
use App\Models\Category;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\CategoryEntry;
use App\Models\Round;
use App\Models\GameMatch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Core Users
        $adminUser = User::create([
            'name' => 'Admin Galotxas',
            'email' => 'admin@galotxas.com',
            'password' => Hash::make('password'),
            'role' => 'admin'
        ]);

        $realPlayer = Player::factory()->create([
            'name' => 'Paco Pilotari',
            'slug' => Str::slug('Paco Pilotari'),
            'dni' => '12345678A',
        ]);

        $participantUser = User::create([
            'name' => 'Paco Pilotari (User)',
            'email' => 'jugador@galotxas.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'player_id' => $realPlayer->id
        ]);

        // Create dummy players for opponents using factory
        $dummyPlayers = Player::factory()->count(10)->create();

        // 2. Create Venues using factory
        $venues = Venue::factory()->count(3)->create();
        $venue1 = $venues[0];
        $venue2 = $venues[1];
        $venue3 = $venues[2];

        // 3. Create Season & Championships
        $season = Season::factory()->create([
            'name' => 'Temporada 2026',
            'status' => 'active'
        ]);

        // Championship 1: Singles
        $singlesChamp = Championship::factory()->create([
            'season_id' => $season->id,
            'name' => 'Campionat Mà a Mà',
            'type' => 'singles'
        ]);

        $singlesCat1 = Category::factory()->create([
            'championship_id' => $singlesChamp->id,
            'name' => '1ª Categoría (Singles)',
            'slug' => Str::slug('1a-categoria-singles'),
            'level' => 1,
            'category_type' => 'open'
        ]);

        // Championship 2: Doubles
        $doublesChamp = Championship::factory()->create([
            'season_id' => $season->id,
            'name' => 'Campionat de Dobles',
            'type' => 'doubles'
        ]);

        $doublesCat1 = Category::factory()->create([
            'championship_id' => $doublesChamp->id,
            'name' => '1ª Categoría (Dobles)',
            'slug' => Str::slug('1a-categoria-dobles'),
            'level' => 1,
            'category_type' => 'open'
        ]);


        // 4. Enrollments (Category Entries)
        // Enroll Paco in Singles
        $entrySinglesPaco = CategoryEntry::create([
            'category_id' => $singlesCat1->id,
            'entry_type' => 'player',
            'player_id' => $realPlayer->id,
            'status' => 'approved'
        ]);

        // Enroll Dummies in Singles
        $entrySinglesD1 = CategoryEntry::create(['category_id' => $singlesCat1->id, 'entry_type' => 'player', 'player_id' => $dummyPlayers[0]->id, 'status' => 'approved']);
        $entrySinglesD2 = CategoryEntry::create(['category_id' => $singlesCat1->id, 'entry_type' => 'player', 'player_id' => $dummyPlayers[1]->id, 'status' => 'approved']);
        $entrySinglesD3 = CategoryEntry::create(['category_id' => $singlesCat1->id, 'entry_type' => 'player', 'player_id' => $dummyPlayers[2]->id, 'status' => 'approved']);

        // Set up Teams for Doubles
        $teamPaco = Team::factory()->create(['name' => 'Equip Paco i Ficti1']);
        $teamPaco->players()->attach([$realPlayer->id, $dummyPlayers[3]->id]);

        $teamB = Team::factory()->create(['name' => 'Equip Ficti 2 i 3']);
        $teamB->players()->attach([$dummyPlayers[4]->id, $dummyPlayers[5]->id]);

        // Enroll Teams into Doubles
        $entryDoublesPaco = CategoryEntry::create(['category_id' => $doublesCat1->id, 'entry_type' => 'team', 'team_id' => $teamPaco->id, 'status' => 'approved']);
        $entryDoublesB = CategoryEntry::create(['category_id' => $doublesCat1->id, 'entry_type' => 'team', 'team_id' => $teamB->id, 'status' => 'approved']);

        // 5. Structure Competition Rounds and Matches

        // === SINGLES COMPETITION (Victory at 10) ===
        $sRound1 = Round::create(['category_id' => $singlesCat1->id, 'name' => 'Jornada 1', 'order' => 1, 'type' => 'league']);
        $sRound2 = Round::create(['category_id' => $singlesCat1->id, 'name' => 'Jornada 2', 'order' => 2, 'type' => 'league']);
        $sRound3 = Round::create(['category_id' => $singlesCat1->id, 'name' => 'Jornada 3', 'order' => 3, 'type' => 'league']);

        // Match 1: Validated (Paco wins 10-6)
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
            'validated_by' => $adminUser->id
        ]);
        
        // Match 2: Validated (D2 wins 10-9)
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
            'validated_by' => $adminUser->id
        ]);

        // Match 3: Submitted waiting for Admin Validation (Paco submitted 10-2 win)
        GameMatch::create([
            'round_id' => $sRound2->id,
            'home_entry_id' => $entrySinglesPaco->id,
            'away_entry_id' => $entrySinglesD2->id,
            'venue_id' => $venue3->id,
            'scheduled_date' => Carbon::now()->subDays(3),
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 2,
            'submitted_by' => $participantUser->id
        ]);

        // Match 4: Scheduled (Upcoming)
        GameMatch::create([
            'round_id' => $sRound3->id,
            'home_entry_id' => $entrySinglesPaco->id,
            'away_entry_id' => $entrySinglesD3->id,
            'venue_id' => $venue1->id,
            'scheduled_date' => Carbon::now()->addDays(4),
            'status' => 'scheduled'
        ]);


        // === DOUBLES COMPETITION (Victory at 12) ===
        $dRound1 = Round::create(['category_id' => $doublesCat1->id, 'name' => 'Jornada 1', 'order' => 1, 'type' => 'league']);
        $dRound2 = Round::create(['category_id' => $doublesCat1->id, 'name' => 'Jornada 2', 'order' => 2, 'type' => 'league']);

        // Match 1: Validated (Team Paco wins 12-8)
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
            'validated_by' => $adminUser->id
        ]);

        // Match 2: Scheduled (Upcoming return match)
        GameMatch::create([
            'round_id' => $dRound2->id,
            'home_entry_id' => $entryDoublesB->id,
            'away_entry_id' => $entryDoublesPaco->id,
            'venue_id' => $venue3->id,
            'scheduled_date' => Carbon::now()->addDays(5),
            'status' => 'scheduled'
        ]);

    }
}
