<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const OCCUPYING_STATUSES = [
        'scheduled',
        'submitted',
        'under_review',
        'validated',
    ];

    private const SAMPLE_SIZE = 10;

    public function up(): void
    {
        $violations = $this->blockingViolations();

        if ($violations !== []) {
            throw new RuntimeException(sprintf(
                'No se puede aplicar la ocupación global de pistas: %s. '
                .'La migración no repara, mueve, redondea ni elimina partidos; revisa los IDs indicados y corrige los datos mediante un procedimiento autorizado antes de reintentarlo.',
                implode('; ', $violations)
            ));
        }

        $this->reportAllowedLegacyShapes();

        // The guard only depends on status because MariaDB 11.4 rejects a STORED
        // generated column that references venue_id while its FK uses ON DELETE
        // SET NULL. Nullable venue/date components already make the composite
        // UNIQUE non-conflicting for incomplete slots.
        DB::statement(<<<'SQL'
            ALTER TABLE `game_matches`
                ADD COLUMN `occupancy_guard` TINYINT UNSIGNED
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN `status` IN ('scheduled', 'submitted', 'under_review', 'validated')
                            THEN 1
                            ELSE NULL
                        END
                    ) STORED
                    AFTER `status`,
                ADD UNIQUE INDEX `game_matches_venue_occupancy_unique`
                    (`venue_id`, `scheduled_date`, `occupancy_guard`)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('La garantía global de ocupación de pistas es una migración forward-only.');
    }

    /**
     * @return list<string>
     */
    private function blockingViolations(): array
    {
        $violations = [];
        $duplicates = DB::table('game_matches')
            ->select('venue_id', 'scheduled_date')
            ->selectRaw('COUNT(*) AS row_count')
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->whereNotNull('venue_id')
            ->whereNotNull('scheduled_date')
            ->groupBy('venue_id', 'scheduled_date')
            ->havingRaw('COUNT(*) > 1');
        $duplicateGroups = DB::query()->fromSub($duplicates, 'duplicate_slots')->count();

        if ($duplicateGroups > 0) {
            $sample = DB::query()
                ->fromSub($duplicates, 'duplicate_slots')
                ->orderBy('venue_id')
                ->orderBy('scheduled_date')
                ->limit(self::SAMPLE_SIZE)
                ->get()
                ->map(function ($slot): string {
                    $ids = DB::table('game_matches')
                        ->whereIn('status', self::OCCUPYING_STATUSES)
                        ->where('venue_id', $slot->venue_id)
                        ->where('scheduled_date', $slot->scheduled_date)
                        ->orderBy('id')
                        ->limit(self::SAMPLE_SIZE)
                        ->pluck('id')
                        ->implode(',');

                    return sprintf(
                        'venue=%d fecha=%s filas=%d ids=%s',
                        $slot->venue_id,
                        $slot->scheduled_date,
                        $slot->row_count,
                        $ids
                    );
                })
                ->implode(' | ');
            $duplicateRows = DB::query()
                ->fromSub($duplicates, 'duplicate_slots')
                ->sum('row_count');

            $violations[] = sprintf(
                'existen %d grupos y %d filas ocupantes con pista/fecha duplicadas (muestra: %s)',
                $duplicateGroups,
                $duplicateRows,
                $sample
            );
        }

        $overlaps = DB::table('game_matches as first_match')
            ->join('game_matches as second_match', function ($join): void {
                $join->on('first_match.venue_id', '=', 'second_match.venue_id')
                    ->whereColumn('first_match.id', '<', 'second_match.id');
            })
            ->whereIn('first_match.status', self::OCCUPYING_STATUSES)
            ->whereIn('second_match.status', self::OCCUPYING_STATUSES)
            ->whereNotNull('first_match.venue_id')
            ->whereNotNull('first_match.scheduled_date')
            ->whereNotNull('second_match.scheduled_date')
            ->whereColumn('first_match.scheduled_date', '<>', 'second_match.scheduled_date')
            ->whereRaw('first_match.scheduled_date < DATE_ADD(second_match.scheduled_date, INTERVAL 1 HOUR)')
            ->whereRaw('DATE_ADD(first_match.scheduled_date, INTERVAL 1 HOUR) > second_match.scheduled_date')
            ->select([
                'first_match.id as first_match_id',
                'second_match.id as second_match_id',
                'first_match.venue_id',
                'first_match.scheduled_date as first_start',
                'second_match.scheduled_date as second_start',
            ]);
        $overlapCount = (clone $overlaps)->count();

        if ($overlapCount > 0) {
            $sample = (clone $overlaps)
                ->orderBy('first_match.venue_id')
                ->orderBy('first_match.scheduled_date')
                ->orderBy('first_match.id')
                ->orderBy('second_match.id')
                ->limit(self::SAMPLE_SIZE)
                ->get()
                ->map(static fn ($pair): string => sprintf(
                    'venue=%d ids=%d,%d inicios=%s,%s',
                    $pair->venue_id,
                    $pair->first_match_id,
                    $pair->second_match_id,
                    $pair->first_start,
                    $pair->second_start,
                ))
                ->implode(' | ');

            $violations[] = sprintf(
                'existen %d pares de partidos ocupantes con intervalos de una hora solapados (muestra: %s)',
                $overlapCount,
                $sample,
            );
        }

        return $violations;
    }

    private function reportAllowedLegacyShapes(): void
    {
        $nonCanonical = DB::table('game_matches')
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->whereNotNull('venue_id')
            ->whereNotNull('scheduled_date')
            ->where(function ($query): void {
                $query->whereRaw('MINUTE(`scheduled_date`) <> 0')
                    ->orWhereRaw('SECOND(`scheduled_date`) <> 0');
            });
        $nonCanonicalCount = $nonCanonical->count();

        if ($nonCanonicalCount > 0) {
            Log::warning('Preflight F1: inicios ocupantes legacy no canónicos preservados sin cambios.', [
                'count' => $nonCanonicalCount,
                'sample' => (clone $nonCanonical)
                    ->orderBy('id')
                    ->limit(self::SAMPLE_SIZE)
                    ->get(['id', 'venue_id', 'scheduled_date'])
                    ->map(static fn ($match): array => [
                        'match_id' => $match->id,
                        'venue_id' => $match->venue_id,
                        'scheduled_date' => $match->scheduled_date,
                    ])
                    ->all(),
            ]);
        }

        $partial = DB::table('game_matches')
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('venue_id')->whereNotNull('scheduled_date');
                })->orWhere(function ($query): void {
                    $query->whereNotNull('venue_id')->whereNull('scheduled_date');
                });
            });
        $partialCount = $partial->count();

        if ($partialCount > 0) {
            Log::warning('Preflight F1: los slots parciales no ocupan y se preservan sin cambios.', [
                'count' => $partialCount,
                'sample_match_ids' => (clone $partial)
                    ->orderBy('id')
                    ->limit(self::SAMPLE_SIZE)
                    ->pluck('id')
                    ->all(),
            ]);
        }

        $releasedDuplicates = DB::table('game_matches')
            ->select('venue_id', 'scheduled_date')
            ->whereIn('status', ['postponed', 'cancelled'])
            ->whereNotNull('venue_id')
            ->whereNotNull('scheduled_date')
            ->groupBy('venue_id', 'scheduled_date')
            ->havingRaw('COUNT(*) > 1');
        $releasedGroups = DB::query()->fromSub($releasedDuplicates, 'released_slots')->count();

        if ($releasedGroups > 0) {
            Log::info('Preflight F1: los slots duplicados de partidos liberados están permitidos.', [
                'duplicate_groups' => $releasedGroups,
            ]);
        }

        Log::info('Preflight F1: partidos completamente no programados preservados.', [
            'count' => DB::table('game_matches')
                ->whereNull('venue_id')
                ->whereNull('scheduled_date')
                ->count(),
        ]);
    }
};
