<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const SAMPLE_SIZE = 10;

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->assertNoAmbiguousRounds('Preflight');

            $legacyCount = $this->legacyRounds()->count();
            Log::info('Normalización de metadatos de rondas: legacy detectadas en preflight.', [
                'legacy_count' => $legacyCount,
            ]);

            $updatedCount = $this->legacyRounds()->update([
                'phase' => 'league',
                'stage' => 'matchday',
            ]);

            if ($updatedCount !== $legacyCount) {
                throw new RuntimeException(sprintf(
                    'Normalización de metadatos de rondas abortada: se detectaron %d legacy y se actualizaron %d filas. '
                    .'La transacción se revierte.',
                    $legacyCount,
                    $updatedCount,
                ));
            }

            $remainingCount = $this->legacyRounds()->count();
            if ($remainingCount !== 0) {
                throw new RuntimeException(sprintf(
                    'Postflight de metadatos de rondas: quedan %d filas league/NULL/NULL. La transacción se revierte.',
                    $remainingCount,
                ));
            }

            $this->assertNoAmbiguousRounds('Postflight');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La normalización de metadatos de rondas es una migración forward-only.');
    }

    private function legacyRounds(): Builder
    {
        return DB::table('rounds')
            ->whereRaw("BINARY `type` <=> 'league'")
            ->whereNull('phase')
            ->whereNull('stage');
    }

    private function assertNoAmbiguousRounds(string $step): void
    {
        // Every comparison yields 0 or 1, including NULL operands. Binary
        // comparisons also reject case/space variants under permissive collations.
        $ambiguous = DB::table('rounds')->whereRaw(<<<'SQL'
            NOT (
                ((BINARY `type` <=> 'league') AND (BINARY `phase` <=> 'league') AND (BINARY `stage` <=> 'matchday'))
                OR ((BINARY `type` <=> 'league') AND (`phase` IS NULL) AND (`stage` IS NULL))
                OR ((BINARY `type` <=> 'cup') AND (BINARY `phase` <=> 'cup') AND (
                    (BINARY `stage` <=> 'semifinal')
                    OR (BINARY `stage` <=> 'final')
                    OR (BINARY `stage` <=> 'third_place')
                ))
            )
            SQL);
        $count = $ambiguous->count();

        if ($count !== 0) {
            throw new RuntimeException(sprintf(
                '%s de metadatos de rondas: existen %d filas ambiguas o no soportadas (ids: %s). '
                .'La migración no las repara ni infiere su clasificación; revisa los datos antes de volver a ejecutarla.',
                $step,
                $count,
                $ambiguous->orderBy('id')->limit(self::SAMPLE_SIZE)->pluck('id')->implode(', '),
            ));
        }
    }
};
