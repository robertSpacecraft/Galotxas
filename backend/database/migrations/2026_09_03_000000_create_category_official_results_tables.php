<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'category_official_results',
        'category_official_league_rows',
        'category_official_cup_winners',
        'category_official_result_match_snapshots',
    ];

    private const MIGRATION_TABLE_MARKER = 'galotxas:6f3b:official-results';

    /** @var list<string> */
    private array $tablesStartedByThisRun = [];

    public function up(): void
    {
        $existingTables = collect(self::TABLES)
            ->filter(fn (string $table): bool => Schema::hasTable($table))
            ->values();

        if ($existingTables->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'No se puede crear la persistencia de resultados oficiales: ya existen tablas incompatibles (%s).',
                $existingTables->implode(', ')
            ));
        }

        $this->tablesStartedByThisRun = [];

        try {
            $this->tablesStartedByThisRun[] = 'category_official_results';
            $this->createOfficialResultsTable();
            $this->tablesStartedByThisRun[] = 'category_official_league_rows';
            $this->createLeagueRowsTable();
            $this->tablesStartedByThisRun[] = 'category_official_cup_winners';
            $this->createCupWinnersTable();
            $this->tablesStartedByThisRun[] = 'category_official_result_match_snapshots';
            $this->createMatchSnapshotsTable();
            $this->addCheckConstraints();
        } catch (Throwable $exception) {
            foreach (array_reverse($this->tablesStartedByThisRun) as $table) {
                if ($this->hasMigrationTableMarker($table)) {
                    Schema::drop($table);
                }
            }

            throw $exception;
        } finally {
            $this->tablesStartedByThisRun = [];
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_official_result_match_snapshots');
        Schema::dropIfExists('category_official_cup_winners');
        Schema::dropIfExists('category_official_league_rows');
        Schema::dropIfExists('category_official_results');
    }

    private function createOfficialResultsTable(): void
    {
        Schema::create('category_official_results', function (Blueprint $table) {
            $table->comment(self::MIGRATION_TABLE_MARKER);
            $table->id();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();
            $table->enum('competition_part', ['league', 'cup']);
            $table->unsignedInteger('version');
            $table->enum('status', ['official', 'reopened']);
            $table->timestamp('officialized_at');
            $table->foreignId('officialized_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('officialized_by_name_snapshot');
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('reopened_by_name_snapshot')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->char('source_digest', 64);
            $table->unsignedTinyInteger('current_slot')
                ->storedAs("IF(`status` = 'official', 1, NULL)");
            $table->timestamps();

            $table->unique(
                ['category_id', 'competition_part', 'version'],
                'category_official_results_version_unique'
            );
            $table->unique(
                ['category_id', 'competition_part', 'current_slot'],
                'category_official_results_current_unique'
            );
        });
    }

    private function createLeagueRowsTable(): void
    {
        Schema::create('category_official_league_rows', function (Blueprint $table) {
            $table->comment(self::MIGRATION_TABLE_MARKER);
            $table->id();
            $table->unsignedBigInteger('official_result_id');
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('source_entry_id');
            $table->unsignedBigInteger('source_player_id')->nullable();
            $table->unsignedBigInteger('source_team_id')->nullable();
            $table->enum('entry_type', ['player', 'team']);
            $table->string('identity_projection', 32)->nullable();
            $table->string('display_name_snapshot');
            $table->string('public_display_name')->nullable();
            $table->timestamp('public_anonymized_at')->nullable();
            $table->unsignedInteger('played');
            $table->unsignedInteger('wins');
            $table->unsignedInteger('losses');
            $table->unsignedInteger('points');
            $table->unsignedInteger('games_for');
            $table->unsignedInteger('games_against');
            $table->integer('games_diff');
            $table->timestamps();

            $table->foreign(
                'official_result_id',
                'official_league_rows_result_foreign'
            )->references('id')->on('category_official_results')->cascadeOnDelete();
            $table->unique(
                ['official_result_id', 'position'],
                'official_league_rows_position_unique'
            );
            $table->unique(
                ['official_result_id', 'source_entry_id'],
                'official_league_rows_entry_unique'
            );
        });
    }

    private function createCupWinnersTable(): void
    {
        Schema::create('category_official_cup_winners', function (Blueprint $table) {
            $table->comment(self::MIGRATION_TABLE_MARKER);
            $table->id();
            $table->unsignedBigInteger('official_result_id');
            $table->unsignedBigInteger('source_entry_id');
            $table->unsignedBigInteger('source_player_id')->nullable();
            $table->unsignedBigInteger('source_team_id')->nullable();
            $table->enum('entry_type', ['player', 'team']);
            $table->unsignedBigInteger('source_final_match_id');
            $table->string('identity_projection', 32)->nullable();
            $table->string('display_name_snapshot');
            $table->string('public_display_name')->nullable();
            $table->timestamp('public_anonymized_at')->nullable();
            $table->timestamps();

            $table->foreign(
                'official_result_id',
                'official_cup_winners_result_foreign'
            )->references('id')->on('category_official_results')->cascadeOnDelete();
            $table->unique('official_result_id', 'official_cup_winners_result_unique');
        });
    }

    private function createMatchSnapshotsTable(): void
    {
        Schema::create(
            'category_official_result_match_snapshots',
            function (Blueprint $table) {
                $table->comment(self::MIGRATION_TABLE_MARKER);
                $table->id();
                $table->unsignedBigInteger('official_result_id');
                $table->unsignedBigInteger('source_game_match_id');
                $table->unsignedBigInteger('source_round_id')->nullable();
                $table->string('stage', 32)->nullable();
                $table->unsignedBigInteger('home_entry_id');
                $table->unsignedBigInteger('away_entry_id');
                $table->unsignedInteger('home_score');
                $table->unsignedInteger('away_score');
                $table->unsignedBigInteger('winner_entry_id');
                $table->timestamps();

                $table->foreign(
                    'official_result_id',
                    'official_match_snapshots_result_foreign'
                )->references('id')->on('category_official_results')->cascadeOnDelete();
                $table->unique(
                    ['official_result_id', 'source_game_match_id'],
                    'official_match_snapshots_match_unique'
                );
            }
        );
    }

    private function hasMigrationTableMarker(string $table): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $metadata = DB::selectOne(<<<'SQL'
            SELECT TABLE_COMMENT
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            SQL, [$table]);

        return $metadata?->TABLE_COMMENT === self::MIGRATION_TABLE_MARKER;
    }

    private function addCheckConstraints(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `category_official_results`
                ADD CONSTRAINT `official_results_version_check`
                    CHECK (`version` > 0),
                ADD CONSTRAINT `official_results_actor_name_check`
                    CHECK (CHAR_LENGTH(TRIM(`officialized_by_name_snapshot`)) > 0),
                ADD CONSTRAINT `official_results_digest_check`
                    CHECK (
                        CHAR_LENGTH(`source_digest`) = 64
                        AND BINARY `source_digest` REGEXP '^[0-9a-f]{64}$'
                    ),
                ADD CONSTRAINT `official_results_reopen_metadata_check`
                    CHECK (
                        (
                            `status` = 'official'
                            AND `reopened_at` IS NULL
                            AND `reopened_by_name_snapshot` IS NULL
                            AND `reopen_reason` IS NULL
                        )
                        OR
                        (
                            `status` = 'reopened'
                            AND `reopened_at` IS NOT NULL
                            AND `reopened_by_name_snapshot` IS NOT NULL
                            AND CHAR_LENGTH(TRIM(`reopened_by_name_snapshot`)) > 0
                            AND `reopen_reason` IS NOT NULL
                            AND CHAR_LENGTH(TRIM(`reopen_reason`)) > 0
                        )
                    )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `category_official_league_rows`
                ADD CONSTRAINT `official_league_rows_position_check`
                    CHECK (`position` > 0),
                ADD CONSTRAINT `official_league_rows_entry_source_check`
                    CHECK (
                        (
                            `entry_type` = 'player'
                            AND `source_player_id` IS NOT NULL
                            AND `source_team_id` IS NULL
                        )
                        OR
                        (
                            `entry_type` = 'team'
                            AND `source_player_id` IS NULL
                            AND `source_team_id` IS NOT NULL
                        )
                    ),
                ADD CONSTRAINT `official_league_rows_name_check`
                    CHECK (CHAR_LENGTH(TRIM(`display_name_snapshot`)) > 0)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `category_official_cup_winners`
                ADD CONSTRAINT `official_cup_winners_entry_source_check`
                    CHECK (
                        (
                            `entry_type` = 'player'
                            AND `source_player_id` IS NOT NULL
                            AND `source_team_id` IS NULL
                        )
                        OR
                        (
                            `entry_type` = 'team'
                            AND `source_player_id` IS NULL
                            AND `source_team_id` IS NOT NULL
                        )
                    ),
                ADD CONSTRAINT `official_cup_winners_name_check`
                    CHECK (CHAR_LENGTH(TRIM(`display_name_snapshot`)) > 0)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `category_official_result_match_snapshots`
                ADD CONSTRAINT `official_match_snapshots_entries_check`
                    CHECK (`home_entry_id` <> `away_entry_id`),
                ADD CONSTRAINT `official_match_snapshots_score_check`
                    CHECK (`home_score` <> `away_score`),
                ADD CONSTRAINT `official_match_snapshots_winner_check`
                    CHECK (`winner_entry_id` IN (`home_entry_id`, `away_entry_id`))
            SQL);
    }
};
