<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $normalizedNickname = "NULLIF(TRIM(REGEXP_REPLACE(`nickname`, '[[:space:]]+', ' ')), '')";

        $blankNicknames = DB::table('players')
            ->whereNotNull('nickname')
            ->whereRaw("{$normalizedNickname} IS NULL")
            ->count();

        if ($blankNicknames > 0) {
            throw new RuntimeException(sprintf(
                'No se puede aplicar la unicidad de apodos: existen %d apodos no nulos vacíos.',
                $blankNicknames
            ));
        }

        $noncanonicalNicknames = DB::table('players')
            ->whereNotNull('nickname')
            ->whereRaw("BINARY `nickname` <> BINARY ({$normalizedNickname})")
            ->count();

        if ($noncanonicalNicknames > 0) {
            throw new RuntimeException(sprintf(
                'No se puede aplicar la unicidad de apodos: existen %d apodos no canónicos.',
                $noncanonicalNicknames
            ));
        }

        $collisionGroups = DB::query()
            ->fromSub(
                DB::table('players')
                    ->select('nickname')
                    ->whereNotNull('nickname')
                    ->groupBy('nickname')
                    ->havingRaw('COUNT(*) > 1'),
                'nickname_collisions'
            )
            ->count();

        if ($collisionGroups > 0) {
            throw new RuntimeException(sprintf(
                'No se puede aplicar la unicidad de apodos: existen %d grupos en conflicto bajo la collation vigente.',
                $collisionGroups
            ));
        }

        $normalizedCollisionGroups = DB::query()
            ->fromSub(
                DB::table('players')
                    ->selectRaw("{$normalizedNickname} AS normalized_nickname")
                    ->whereNotNull('nickname'),
                'normalized_nicknames'
            )
            ->whereNotNull('normalized_nickname')
            ->groupBy('normalized_nickname')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($normalizedCollisionGroups > 0) {
            throw new RuntimeException(sprintf(
                'No se puede aplicar la unicidad de apodos: existen %d grupos en conflicto tras normalizar espacios.',
                $normalizedCollisionGroups
            ));
        }

        Schema::table('players', function ($table): void {
            $table->unique('nickname', 'players_nickname_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La garantía de unicidad de apodos es una migración forward-only.');
    }
};
