<?php

namespace Tests\Unit;

use App\Services\CompetitionScheduleDateTimeFormatter;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CompetitionScheduleDateTimeFormatterTest extends TestCase
{
    public static function scheduleDates(): array
    {
        return [
            'CEST' => ['2026-10-23 17:00:00', '2026-10-23T17:00:00+02:00'],
            'CET after transition' => ['2026-10-30 17:00:00', '2026-10-30T17:00:00+01:00'],
            'CET November' => ['2026-11-06 18:00:00', '2026-11-06T18:00:00+01:00'],
        ];
    }

    #[DataProvider('scheduleDates')]
    public function test_it_reinterprets_wall_clock_without_mutating_input(string $stored, string $expected): void
    {
        foreach ([Carbon::class, CarbonImmutable::class] as $type) {
            $value = $type::parse($stored, 'UTC');

            $this->assertSame($expected, CompetitionScheduleDateTimeFormatter::format($value));
            $this->assertSame($stored, $value->format('Y-m-d H:i:s'));
            $this->assertSame('UTC', $value->timezoneName);
        }
    }

    public function test_it_preserves_all_civil_components_including_seconds(): void
    {
        $value = Carbon::parse('2026-10-23 23:47:32', 'UTC');

        $this->assertSame('2026-10-23T23:47:32+02:00', CompetitionScheduleDateTimeFormatter::format($value));
        $this->assertSame('2026-10-23 23:47:32', $value->format('Y-m-d H:i:s'));
    }

    public function test_it_returns_null_for_unscheduled_matches(): void
    {
        $this->assertNull(CompetitionScheduleDateTimeFormatter::format(null));
    }
}
