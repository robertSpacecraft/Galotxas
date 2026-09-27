<?php

namespace App\Services;

use App\Enums\ChampionshipType;
use RuntimeException;
use SplQueue;

class LeagueWeekendAllocator
{
    public function __construct(private readonly LeagueCourtPolicy $policy) {}

    /**
     * Unit-capacity min-cost flow. Reverse edges allow earlier flexible matches
     * to move when a constrained match needs their slot. Costs lexicographically
     * minimize overflow count, overflow priority sum, then slot index sum.
     * Stable input/edge/queue order breaks remaining ties deterministically.
     *
     * @return list<array>
     */
    public function allocate(array $matches, array $slots, ChampionshipType $type): array
    {
        $n = count($matches);
        $s = count($slots);
        if ($n > $s) {
            throw new RuntimeException('No hay suficientes huecos libres para programar todas las categorías.');
        }
        $source = $n + $s;
        $sink = $source + 1;
        $graph = array_fill(0, $sink + 1, []);
        $edges = [];
        $add = function (int $from, int $to, int $cost) use (&$graph, &$edges): void {
            $graph[$from][] = count($edges);
            $edges[] = ['to' => $to, 'capacity' => 1, 'cost' => $cost];
            $graph[$to][] = count($edges);
            $edges[] = ['to' => $from, 'capacity' => 0, 'cost' => -$cost];
        };
        $priorityWeight = $n * ($s + 1) + 1;
        $overflowWeight = (8 * $n + 1) * $priorityWeight;
        foreach ($matches as $i => $match) {
            $add($source, $i, 0);
            $normal = $this->policy->normalCourts($match['category'], $type);
            $priority = $this->policy->overflowPriority($match['category']);
            foreach ($slots as $j => $slot) {
                $court = $slot['court_number'];
                if (in_array($court, $normal, true)) {
                    $add($i, $n + $j, $j);
                } elseif ($court === 6 && $priority !== null) {
                    $add($i, $n + $j, $overflowWeight + $priority * $priorityWeight + $j);
                }
            }
        }
        foreach ($slots as $j => $slot) {
            $add($n + $j, $sink, 0);
        }

        for ($flow = 0; $flow < $n; $flow++) {
            $distance = array_fill(0, $sink + 1, PHP_INT_MAX);
            $previous = array_fill(0, $sink + 1, null);
            $queued = array_fill(0, $sink + 1, false);
            $distance[$source] = 0;
            $queue = new SplQueue;
            $queue->enqueue($source);
            $queued[$source] = true;
            while (! $queue->isEmpty()) {
                $from = $queue->dequeue();
                $queued[$from] = false;
                foreach ($graph[$from] as $edgeId) {
                    $edge = $edges[$edgeId];
                    $to = $edge['to'];
                    if ($edge['capacity'] > 0 && $distance[$to] > $distance[$from] + $edge['cost']) {
                        $distance[$to] = $distance[$from] + $edge['cost'];
                        $previous[$to] = $edgeId;
                        if (! $queued[$to]) {
                            $queue->enqueue($to);
                            $queued[$to] = true;
                        }
                    }
                }
            }
            if ($previous[$sink] === null) {
                throw new RuntimeException('No existe una asignación completa con las pistas libres y las restricciones de las categorías. Revisa la ocupación y la configuración de pistas.');
            }
            for ($node = $sink; $node !== $source;) {
                $edgeId = $previous[$node];
                $edges[$edgeId]['capacity']--;
                $edges[$edgeId ^ 1]['capacity']++;
                $node = $edges[$edgeId ^ 1]['to'];
            }
        }

        $assigned = [];
        foreach ($matches as $i => $match) {
            foreach ($graph[$i] as $edgeId) {
                $edge = $edges[$edgeId];
                if ($edge['to'] >= $n && $edge['to'] < $source && $edge['capacity'] === 0) {
                    $assigned[] = $match + ['slot' => $slots[$edge['to'] - $n]];
                    break;
                }
            }
        }

        return $assigned;
    }
}
