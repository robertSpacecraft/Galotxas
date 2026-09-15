<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Round;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class AdminGameMatchUpdateTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('invalidScheduledDates')]
    public function test_invalid_scheduled_date_is_rejected_without_mutating_the_match(
        string $scheduledDate,
        string $expectedMessage,
    ): void {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $match->update([
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $match->home_entry_id,
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
                'status' => 'validated',
                'home_score' => 10,
                'away_score' => 6,
            ])
            ->assertRedirect($categoryUrl)
            ->assertSessionHasErrors(['scheduled_date' => $expectedMessage])
            ->assertSessionMissing('success');

        foreach (session('errors')->get('scheduled_date') as $message) {
            $this->assertFalse(
                str_starts_with($message, 'validation.'),
                'A raw validation translation key reached the admin session.'
            );
        }

        $match->refresh();

        $this->assertSame('2026-09-15 18:00:00', $match->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame($originalVenueId, $match->venue_id);
        $this->assertSame('submitted', $match->status->value);
        $this->assertSame(10, $match->home_score);
        $this->assertSame(7, $match->away_score);
        $this->assertSame($match->home_entry_id, $match->winner_entry_id);
    }

    public static function invalidScheduledDates(): array
    {
        return [
            'required date' => [
                '',
                'La fecha del partido es obligatoria.',
            ],
            'five-digit year reproduced by the audit' => [
                '20226-01-01',
                'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.',
            ],
            'first five-digit year' => [
                '10000-01-01',
                'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.',
            ],
            'nonexistent calendar date' => [
                '2026-02-30',
                'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.',
            ],
            'noncanonical representation' => [
                '2026-2-03',
                'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.',
            ],
            'staging below-minimum example' => [
                '0008-01-01',
                'La fecha del partido no puede ser anterior al 01/01/1000.',
            ],
            'date below the technical range' => [
                '0999-12-31',
                'La fecha del partido no puede ser anterior al 01/01/1000.',
            ],
            'first date after the functional future boundary' => [
                '2028-09-16',
                'La fecha del partido no puede ser posterior a dos años desde hoy.',
            ],
            'staging far-future example' => [
                '2135-01-01',
                'La fecha del partido no puede ser posterior a dos años desde hoy.',
            ],
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
            'historical date from staging acceptance' => ['2001-01-01', '12:30'],
            'lower technical boundary' => ['1000-01-01', '00:00'],
            'functional future boundary' => ['2028-09-15', '23:59'],
        ];
    }

    public function test_category_match_forms_expose_date_bounds_and_historical_warning_hooks(): void
    {
        [$leagueMatch] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $category = $leagueMatch->round->category;
        $cupRound = Round::factory()->create([
            'category_id' => $category->id,
            'name' => 'Semifinales',
            'order' => 1,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'semifinal',
        ]);

        GameMatch::factory()->create([
            'round_id' => $cupRound->id,
            'venue_id' => $leagueMatch->venue_id,
            'home_entry_id' => $leagueMatch->home_entry_id,
            'away_entry_id' => $leagueMatch->away_entry_id,
            'scheduled_date' => '2001-01-01 12:30:00',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.categories.show', $category))
            ->assertOk()
            ->assertSee("document.querySelectorAll('[data-admin-match-date]')", false);
        $html = $response->getContent();
        $warning = 'Aviso: la fecha indicada es muy antigua. Comprueba que es correcta si estás registrando un partido o campeonato histórico.';

        $this->assertSame(2, substr_count($html, 'data-admin-match-date="true"'));
        $this->assertSame(2, substr_count($html, 'min="1000-01-01"'));
        $this->assertSame(2, substr_count($html, 'max="2028-09-15"'));
        $this->assertSame(2, substr_count($html, 'data-historical-before="2021-09-15"'));
        $this->assertSame(2, substr_count($html, 'data-admin-historical-date-warning="true"'));
        $this->assertSame(2, substr_count($html, $warning));
    }
}
