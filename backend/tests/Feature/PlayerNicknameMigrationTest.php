<?php

namespace Tests\Feature;

use App\Models\Player;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PlayerNicknameMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_index_is_nullable_and_uses_the_actual_mariadb_collation(): void
    {
        Player::factory()->count(2)->create(['nickname' => null]);
        Player::factory()->create(['nickname' => 'Álias']);

        $index = collect(DB::select("SHOW INDEX FROM players WHERE Key_name = 'players_nickname_unique'"));
        $this->assertCount(1, $index);
        $this->assertSame('nickname', $index->first()->Column_name);
        $this->assertSame(0, (int) $index->first()->Non_unique);

        $this->expectException(QueryException::class);
        Player::factory()->create(['nickname' => 'alias   ']);
    }

    public function test_migration_fails_closed_when_legacy_collisions_exist(): void
    {
        Schema::table('players', function ($table): void {
            $table->dropUnique('players_nickname_unique');
        });
        Player::factory()->create(['nickname' => 'Duplicado']);
        Player::factory()->create(['nickname' => 'duplicado']);

        $migration = require database_path('migrations/2026_09_21_000000_enforce_unique_player_nicknames.php');

        $this->expectException(RuntimeException::class);
        $migration->up();
    }

    public function test_migration_fails_closed_for_non_null_blank_nicknames(): void
    {
        Schema::table('players', function ($table): void {
            $table->dropUnique('players_nickname_unique');
        });
        Player::factory()->create(['nickname' => "\u{00A0}\t\u{00A0}"]);

        $migration = require database_path('migrations/2026_09_21_000000_enforce_unique_player_nicknames.php');

        $this->expectException(RuntimeException::class);
        $migration->up();
    }

    public function test_migration_fails_closed_for_collisions_after_whitespace_normalization(): void
    {
        Schema::table('players', function ($table): void {
            $table->dropUnique('players_nickname_unique');
        });
        Player::factory()->create(['nickname' => 'Alias  Deportivo']);
        Player::factory()->create(['nickname' => 'Alias Deportivo']);

        $migration = require database_path('migrations/2026_09_21_000000_enforce_unique_player_nicknames.php');

        $this->expectException(RuntimeException::class);
        $migration->up();
    }

    public function test_migration_fails_closed_for_a_single_noncanonical_nickname_without_rewriting_it(): void
    {
        Schema::table('players', function ($table): void {
            $table->dropUnique('players_nickname_unique');
        });
        $player = Player::factory()->create(['nickname' => 'Alias  Deportivo']);

        $migration = require database_path('migrations/2026_09_21_000000_enforce_unique_player_nicknames.php');

        try {
            $migration->up();
            $this->fail('La migración debía rechazar el apodo no canónico.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'No se puede aplicar la unicidad de apodos: existen 1 apodos no canónicos.',
                $exception->getMessage()
            );
        }

        $this->assertSame('Alias  Deportivo', $player->fresh()->nickname);
    }
}
