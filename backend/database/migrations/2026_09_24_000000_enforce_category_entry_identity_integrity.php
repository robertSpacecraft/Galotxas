<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SAMPLE_SIZE = 10;

    public function up(): void
    {
        $violations = $this->violations();

        if ($violations !== []) {
            throw new RuntimeException(sprintf(
                'No se puede aplicar la integridad de identidad de participantes: %s. '
                .'La migración no repara, fusiona ni elimina filas; corrige los datos manualmente y vuelve a ejecutarla.',
                implode('; ', $violations)
            ));
        }

        // One ALTER keeps the identity CHECK and both UNIQUE indexes indivisible:
        // MariaDB validates the existing rows and applies nothing if any of them fails.
        // UNIQUE allows repeated NULLs, so with the XOR CHECK every row takes part in
        // exactly one of the two indexes (player identities or team identities).
        DB::statement(<<<'SQL'
            ALTER TABLE `category_entries`
                ADD CONSTRAINT `category_entries_identity_check`
                    CHECK (
                        (
                            `entry_type` = 'player'
                            AND `player_id` IS NOT NULL
                            AND `team_id` IS NULL
                        )
                        OR
                        (
                            `entry_type` = 'team'
                            AND `team_id` IS NOT NULL
                            AND `player_id` IS NULL
                        )
                    ),
                ADD UNIQUE INDEX `category_entries_category_player_unique` (`category_id`, `player_id`),
                ADD UNIQUE INDEX `category_entries_category_team_unique` (`category_id`, `team_id`)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('La integridad de identidad de participantes es una migración forward-only.');
    }

    /**
     * @return list<string>
     */
    private function violations(): array
    {
        $violations = [];

        $shapes = [
            'entradas con jugador y equipo a la vez' => fn ($query) => $query
                ->whereNotNull('player_id')
                ->whereNotNull('team_id'),
            'entradas sin jugador ni equipo' => fn ($query) => $query
                ->whereNull('player_id')
                ->whereNull('team_id'),
            'entradas de tipo player con equipo en lugar de jugador' => fn ($query) => $query
                ->where('entry_type', 'player')
                ->whereNull('player_id')
                ->whereNotNull('team_id'),
            'entradas de tipo team con jugador en lugar de equipo' => fn ($query) => $query
                ->where('entry_type', 'team')
                ->whereNull('team_id')
                ->whereNotNull('player_id'),
        ];

        foreach ($shapes as $label => $constraint) {
            $count = $constraint(DB::table('category_entries'))->count();

            if ($count > 0) {
                $violations[] = sprintf(
                    'existen %d %s (ids: %s)',
                    $count,
                    $label,
                    $constraint(DB::table('category_entries'))
                        ->orderBy('id')
                        ->limit(self::SAMPLE_SIZE)
                        ->pluck('id')
                        ->implode(', ')
                );
            }
        }

        foreach (['player_id' => 'jugador', 'team_id' => 'equipo'] as $column => $label) {
            $duplicates = DB::table('category_entries')
                ->select('category_id', $column)
                ->selectRaw('MIN(id) AS first_id')
                ->whereNotNull($column)
                ->groupBy('category_id', $column)
                ->havingRaw('COUNT(*) > 1');

            $groups = DB::query()->fromSub($duplicates, 'duplicate_identities')->count();

            if ($groups > 0) {
                $violations[] = sprintf(
                    'existen %d grupos de %s repetido en una misma categoría (primer id de cada grupo: %s)',
                    $groups,
                    $label,
                    DB::query()
                        ->fromSub($duplicates, 'duplicate_identities')
                        ->orderBy('first_id')
                        ->limit(self::SAMPLE_SIZE)
                        ->pluck('first_id')
                        ->implode(', ')
                );
            }
        }

        return $violations;
    }
};
