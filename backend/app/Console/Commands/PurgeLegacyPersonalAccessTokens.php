<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PurgeLegacyPersonalAccessTokens extends Command
{
    public const CONFIRMATION = 'PURGE-LEGACY-PATS';

    protected $signature = 'auth:purge-legacy-pats
        {--confirm= : Confirmación exacta PURGE-LEGACY-PATS; sin ella es un simulacro}
        {--chunk=1000 : Filas por lote de borrado (1-10000)}';

    protected $description = 'Elimina los personal access tokens de User una vez desactivados la emisión y la aceptación Bearer (simulacro por defecto)';

    public function handle(): int
    {
        $chunk = $this->chunkSize();

        if ($chunk === null) {
            $this->error('Valor --chunk no válido: debe ser un entero entre 1 y 10000. No se ha consultado ni modificado ningún dato.');

            return self::FAILURE;
        }

        $confirm = $this->option('confirm');

        if ($confirm !== null && $confirm !== self::CONFIRMATION) {
            $this->error('Confirmación incorrecta. Debe ser exactamente '.self::CONFIRMATION.'. No se ha consultado ni modificado ningún dato.');

            return self::FAILURE;
        }

        if (! $this->configurationIsSafe()) {
            return self::FAILURE;
        }

        $targets = $this->targets()->count();
        $untouched = DB::table('personal_access_tokens')->where('tokenable_type', '!=', User::class)->count();

        if ($confirm === null) {
            $this->line('MODO: SIMULACRO (DRY RUN). No se elimina ni se modifica nada.');
            $this->line("Tokens de User que se eliminarían: {$targets}");
            $this->line("Tokens de otros tokenable_type (no se tocan): {$untouched}");
            $this->line('Para eliminar de verdad: --confirm='.self::CONFIRMATION);

            return self::SUCCESS;
        }

        $this->warn('MODO: ELIMINACIÓN. Se borran los tokens de User por lotes de '.$chunk.'.');

        $deleted = 0;
        $batches = 0;

        try {
            while (true) {
                $ids = $this->targets()->orderBy('id')->limit($chunk)->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $removed = $this->targets()->whereIn('id', $ids)->delete();

                if ($removed === 0) {
                    throw new RuntimeException('Un lote seleccionado no eliminó ninguna fila.');
                }

                $deleted += $removed;
                $batches++;
            }
        } catch (Throwable $exception) {
            $this->error('ELIMINACIÓN INTERRUMPIDA. Cada lote es atómico, pero la operación completa no es transaccional.');
            $this->line("Tokens eliminados antes del fallo: {$deleted} en {$batches} lotes.");
            $this->line('Tokens de User restantes: '.$this->targets()->count().'. Es seguro volver a ejecutar el comando.');
            $this->line('Causa: '.class_basename($exception));

            return self::FAILURE;
        }

        $this->info('ELIMINACIÓN COMPLETADA.');
        $this->line("Tokens de User eliminados: {$deleted} en {$batches} lotes (objetivo inicial: {$targets}).");
        $this->line('Tokens de User restantes: '.$this->targets()->count());
        $this->line("Tokens de otros tokenable_type (intactos): {$untouched}");

        return self::SUCCESS;
    }

    private function targets(): Builder
    {
        return DB::table('personal_access_tokens')->where('tokenable_type', User::class);
    }

    private function chunkSize(): ?int
    {
        $value = $this->option('chunk');

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        if (! preg_match('/^\d+$/', (string) $value)) {
            return null;
        }

        $size = (int) $value;

        return $size >= 1 && $size <= 10000 ? $size : null;
    }

    private function configurationIsSafe(): bool
    {
        $issuance = config('legacy_bearer.issuance_enabled');
        $acceptance = config('legacy_bearer.acceptance_enabled');

        if (! is_bool($issuance) || ! is_bool($acceptance)) {
            $this->error('Configuración Bearer ambigua: LEGACY_BEARER_ISSUANCE_ENABLED y LEGACY_BEARER_ACCEPTANCE_ENABLED deben ser booleanos. No se ha consultado ni modificado ningún dato.');

            return false;
        }

        if ($issuance || $acceptance) {
            $this->error('Rechazado: la emisión y la aceptación Bearer deben estar ambas desactivadas (false/false). '
                .'Estado actual: emisión='.($issuance ? 'true' : 'false').', aceptación='.($acceptance ? 'true' : 'false')
                .'. No se ha consultado ni modificado ningún dato.');

            return false;
        }

        return true;
    }
}
