<?php

namespace Tests\Feature;

use App\Models\Venue;
use Database\Seeders\DefaultVenueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultVenueSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_minimum_default_venues(): void
    {
        $this->seed(DefaultVenueSeeder::class);

        $this->assertSame(5, Venue::query()->count());

        foreach ($this->expectedNames() as $name) {
            $this->assertDatabaseHas('venues', ['name' => $name]);
        }
    }

    public function test_it_does_not_overwrite_or_duplicate_existing_venues(): void
    {
        $existingVenue = Venue::factory()->create([
            'name' => 'Pista 1',
            'location' => 'Ubicación propia',
            'description' => 'Descripción propia.',
        ]);

        $this->seed(DefaultVenueSeeder::class);
        $this->seed(DefaultVenueSeeder::class);

        $existingVenue->refresh();

        $this->assertSame('Ubicación propia', $existingVenue->location);
        $this->assertSame('Descripción propia.', $existingVenue->description);
        $this->assertSame(5, Venue::query()->count());
        $this->assertSame(1, Venue::query()->where('name', 'Pista 1')->count());
    }

    /**
     * @return array<int, string>
     */
    private function expectedNames(): array
    {
        return [
            'Pista 1',
            'Pista 2',
            'Pista 3',
            'Pista 4',
            'Pista 5',
        ];
    }
}
