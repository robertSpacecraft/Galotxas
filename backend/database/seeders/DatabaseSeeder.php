<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Championship;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin principal
        User::updateOrCreate(
            ['email' => 'admin@galotxas.com'],
            [
                'name' => 'Admin',
                'lastname' => 'Galotxas',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'active' => true,
            ]
        );

        // Usuarios base
        $users = User::factory(12)->create();

        // Players asociados a parte de esos usuarios
        $players = $users->take(10)->map(function ($user) {
            return Player::factory()->create([
                'user_id' => $user->id,
            ]);
        });

        // Temporada
        $season = Season::factory()->create();

        // Campeonato individual
        $singlesChampionship = Championship::factory()->create([
            'season_id' => $season->id,
            'type' => 'singles',
            'name' => 'Campionat Individual',
        ]);

        // Campeonato dobles
        $doublesChampionship = Championship::factory()->create([
            'season_id' => $season->id,
            'type' => 'doubles',
            'name' => 'Campionat Dobles',
        ]);

        // Categorías singles
        $singlesCategories = Category::factory(2)->create([
            'championship_id' => $singlesChampionship->id,
        ]);

        // Categorías doubles
        $doublesCategories = Category::factory(2)->create([
            'championship_id' => $doublesChampionship->id,
        ]);

        // Algunas rondas de ejemplo
        foreach ($singlesCategories as $category) {
            Round::factory()->create([
                'category_id' => $category->id,
                'type' => 'league',
                'phase' => 'league',
                'stage' => 'matchday',
                'name' => 'Jornada 1',
                'order' => 1,
            ]);

            Round::factory()->create([
                'category_id' => $category->id,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'semifinal',
                'name' => 'Semifinales',
                'order' => 100,
            ]);

            Round::factory()->create([
                'category_id' => $category->id,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'third_place',
                'name' => '3º y 4º puesto',
                'order' => 101,
            ]);

            Round::factory()->create([
                'category_id' => $category->id,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'final',
                'name' => 'Final',
                'order' => 102,
            ]);
        }

        foreach ($doublesCategories as $category) {
            Round::factory()->create([
                'category_id' => $category->id,
                'type' => 'league',
                'phase' => 'league',
                'stage' => 'matchday',
                'name' => 'Jornada 1',
                'order' => 1,
            ]);

            Round::factory()->create([
                'category_id' => $category->id,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'semifinal',
                'name' => 'Semifinales',
                'order' => 100,
            ]);

            Round::factory()->create([
                'category_id' => $category->id,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'third_place',
                'name' => '3º y 4º puesto',
                'order' => 101,
            ]);

            Round::factory()->create([
                'category_id' => $category->id,
                'type' => 'cup',
                'phase' => 'cup',
                'stage' => 'final',
                'name' => 'Final',
                'order' => 102,
            ]);

            // Equipos de ejemplo solo en categorías de dobles
            $categoryPlayers = $players->shuffle()->take(4)->values();

            $teamA = Team::factory()->create([
                'category_id' => $category->id,
                'name' => 'Equipo A ' . $category->id,
            ]);

            $teamA->players()->attach($categoryPlayers[0]->id, ['role_in_team' => 'front']);
            $teamA->players()->attach($categoryPlayers[1]->id, ['role_in_team' => 'back']);

            $teamB = Team::factory()->create([
                'category_id' => $category->id,
                'name' => 'Equipo B ' . $category->id,
            ]);

            $teamB->players()->attach($categoryPlayers[2]->id, ['role_in_team' => 'front']);
            $teamB->players()->attach($categoryPlayers[3]->id, ['role_in_team' => 'back']);
        }
    }
}
