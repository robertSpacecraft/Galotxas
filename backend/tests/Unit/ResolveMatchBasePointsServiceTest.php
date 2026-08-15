<?php

namespace Tests\Unit;

use App\Services\Ranking\ResolveMatchBasePointsService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ResolveMatchBasePointsServiceTest extends TestCase
{
    public function test_it_resolves_singles_and_doubles_results_symmetrically(): void
    {
        $service = new ResolveMatchBasePointsService;
        $cases = [
            [10, 7, 3, 0],
            [10, 8, 2, 1],
            [10, 9, 2, 1],
            [12, 7, 3, 0],
            [12, 8, 2, 1],
            [12, 11, 2, 1],
            [7, 10, 0, 3],
            [8, 10, 1, 2],
            [9, 10, 1, 2],
            [7, 12, 0, 3],
            [8, 12, 1, 2],
            [11, 12, 1, 2],
        ];

        foreach ($cases as [$homeScore, $awayScore, $homePoints, $awayPoints]) {
            $result = $service->resolve($homeScore, $awayScore);

            $this->assertSame($homePoints, $result['home_points']);
            $this->assertSame($awayPoints, $result['away_points']);
            $this->assertSame(3, $result['home_points'] + $result['away_points']);
        }
    }

    public function test_it_rejects_tied_scores_explicitly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No se pueden repartir puntos para un partido empatado.');

        (new ResolveMatchBasePointsService)->resolve(8, 8);
    }
}
