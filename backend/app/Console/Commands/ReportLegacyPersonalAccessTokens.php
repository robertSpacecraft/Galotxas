<?php

namespace App\Console\Commands;

use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportLegacyPersonalAccessTokens extends Command
{
    protected $signature = 'auth:legacy-pat-report
        {--since= : Corte ISO 8601 con zona horaria, p. ej. 2026-10-03T14:54:46Z}';

    protected $description = 'Informa, sólo con agregados y sin modificar datos, del estado y uso reciente de los personal access tokens';

    private const AGE_BUCKETS = [
        'last_used_at hace ≤ 24 h' => [null, 1],
        'last_used_at hace > 24 h y ≤ 7 d' => [1, 7],
        'last_used_at hace > 7 d y ≤ 30 d' => [7, 30],
        'last_used_at hace > 30 d' => [30, null],
    ];

    public function handle(): int
    {
        $since = null;

        if ($this->option('since') !== null) {
            $since = $this->parseSince((string) $this->option('since'));

            if ($since === null) {
                $this->error('Valor --since no válido. Usa ISO 8601 con zona horaria, p. ej. 2026-10-03T14:54:46Z. No se ha consultado ningún dato.');

                return self::FAILURE;
            }
        }

        $now = Carbon::now('UTC');

        $this->line('Informe de personal access tokens (sólo lectura, sólo agregados).');
        $this->line('Generado: '.$now->format('Y-m-d\TH:i:s\Z'));
        $this->line('Corte --since: '.($since ? $since->format('Y-m-d\TH:i:s\Z') : 'no indicado'));

        $totals = $this->base()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(p.last_used_at IS NULL), 0) AS never_used')
            ->selectRaw('COALESCE(SUM(p.last_used_at IS NOT NULL), 0) AS used')
            ->first();

        $rows = [
            ['Tokens con tokenable User', (int) $totals->total],
            ['last_used_at = null', (int) $totals->never_used],
            ['last_used_at != null', (int) $totals->used],
        ];

        if ($since !== null) {
            $sinceSql = $since->format('Y-m-d H:i:s');
            $sinceCounts = $this->base()
                ->selectRaw('COALESCE(SUM(p.created_at >= ?), 0) AS created_since', [$sinceSql])
                ->selectRaw('COALESCE(SUM(p.last_used_at >= ?), 0) AS used_since', [$sinceSql])
                ->first();

            $rows[] = ['Creados desde el corte', (int) $sinceCounts->created_since];
            $rows[] = ['Usados desde el corte', (int) $sinceCounts->used_since];
        }

        $this->table(['Métrica', 'Tokens'], $rows);

        $this->table(['Antigüedad de last_used_at', 'Tokens'], $this->ageBuckets($now));

        $this->table(['Estado del usuario', 'Tokens'], $this->grouped(
            "CASE WHEN u.id IS NULL THEN 'sin usuario' WHEN u.active = 1 THEN 'activo' ELSE 'inactivo' END"
        ));

        $this->table(['Rol del usuario', 'Tokens'], $this->grouped(
            "CASE WHEN u.id IS NULL THEN 'sin usuario' ELSE u.role END"
        ));

        $this->table(['Nombre del token', 'Tokens'], $this->grouped('p.name'));

        $this->info('Informe completado. No se ha modificado ningún dato.');

        return self::SUCCESS;
    }

    private function base(): Builder
    {
        return DB::table('personal_access_tokens AS p')
            ->leftJoin('users AS u', 'u.id', '=', 'p.tokenable_id')
            ->where('p.tokenable_type', User::class);
    }

    /**
     * @return list<array{string, int}>
     */
    private function ageBuckets(Carbon $now): array
    {
        $rows = [];

        foreach (self::AGE_BUCKETS as $label => [$from, $to]) {
            $query = $this->base()->whereNotNull('p.last_used_at');

            if ($from !== null) {
                $query->where('p.last_used_at', '<', $now->copy()->subDays($from)->format('Y-m-d H:i:s'));
            }

            if ($to !== null) {
                $query->where('p.last_used_at', '>=', $now->copy()->subDays($to)->format('Y-m-d H:i:s'));
            }

            $rows[] = [$label, $query->count()];
        }

        $rows[] = ['last_used_at = null', $this->base()->whereNull('p.last_used_at')->count()];

        return $rows;
    }

    /**
     * @return list<array{string, int}>
     */
    private function grouped(string $expression): array
    {
        return $this->base()
            ->selectRaw("{$expression} AS bucket, COUNT(*) AS aggregate")
            ->groupByRaw($expression)
            ->orderByRaw($expression)
            ->get()
            ->map(static fn (object $row): array => [(string) $row->bucket, (int) $row->aggregate])
            ->all();
    }

    private function parseSince(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $value)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return Carbon::instance($parsed)->setTimezone(new DateTimeZone('UTC'));
    }
}
