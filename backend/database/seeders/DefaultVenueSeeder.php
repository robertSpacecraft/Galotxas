<?php

namespace Database\Seeders;

use App\Models\Venue;
use Illuminate\Database\Seeder;

class DefaultVenueSeeder extends Seeder
{
    /** @var array<int, string> */
    private array $venueNames = [
        'Pista 1',
        'Pista 2',
        'Pista 3',
        'Pista 4',
        'Pista 5',
    ];

    public function run(): void
    {
        foreach ($this->venueNames as $venueName) {
            Venue::query()->firstOrCreate(['name' => $venueName]);
        }
    }
}
