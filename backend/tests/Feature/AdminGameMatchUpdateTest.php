<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class AdminGameMatchUpdateTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use RefreshDatabase;

    #[DataProvider('invalidScheduledDates')]
    public function test_invalid_scheduled_date_is_rejected_without_mutating_the_match(
        string $scheduledDate,
    ): void {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $originalVenueId = $match->venue_id;
        $admin = User::factory()->admin()->create();
        $newVenue = Venue::factory()->create();
        $category = $match->round->category;
        $categoryUrl = route('admin.categories.show', $category);

        $this->actingAs($admin)
            ->from($categoryUrl)
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => $scheduledDate,
                'scheduled_time' => '19:30',
                'venue_id' => $newVenue->id,
                'status' => 'postponed',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertRedirect($categoryUrl)
            ->assertSessionHasErrors(['scheduled_date'])
            ->assertSessionMissing('success');

        $match->refresh();

        $this->assertSame('2026-09-15 18:00:00', $match->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame($originalVenueId, $match->venue_id);
        $this->assertSame('scheduled', $match->status->value);
        $this->assertNull($match->home_score);
        $this->assertNull($match->away_score);
    }

    public static function invalidScheduledDates(): array
    {
        return [
            'five-digit year reproduced by the audit' => ['20226-01-01'],
            'first date above the supported range' => ['10000-01-01'],
            'nonexistent calendar date' => ['2026-02-30'],
            'noncanonical representation' => ['2026-2-03'],
            'date below the supported range' => ['0999-12-31'],
        ];
    }

    #[DataProvider('validScheduledDates')]
    public function test_valid_scheduled_date_updates_the_match_and_venue(
        string $scheduledDate,
        string $scheduledTime,
    ): void {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $admin = User::factory()->admin()->create();
        $newVenue = Venue::factory()->create();
        $category = $match->round->category;

        $this->actingAs($admin)
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => $scheduledDate,
                'scheduled_time' => $scheduledTime,
                'venue_id' => $newVenue->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Partido actualizado correctamente.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'scheduled_date' => $scheduledDate.' '.$scheduledTime.':00',
            'venue_id' => $newVenue->id,
            'status' => 'scheduled',
        ]);
    }

    public static function validScheduledDates(): array
    {
        return [
            'normal date' => ['2026-10-10', '19:15'],
            'lower supported boundary' => ['1000-01-01', '00:00'],
            'upper supported boundary' => ['9999-12-31', '23:59'],
        ];
    }
}
