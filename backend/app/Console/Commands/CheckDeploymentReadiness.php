<?php

namespace App\Console\Commands;

use App\Services\DeploymentReadinessService;
use Illuminate\Console\Command;

class CheckDeploymentReadiness extends Command
{
    protected $signature = 'deploy:check {--allow-live-features : Valida dependencias de funciones activadas}';

    protected $description = 'Comprueba de forma segura y sólo lectura la configuración de despliegue';

    public function handle(DeploymentReadinessService $readiness): int
    {
        $checks = $readiness->check((bool) $this->option('allow-live-features'));

        $this->table(
            ['Comprobación', 'Resultado', 'Contrato'],
            array_map(
                static fn (array $check): array => [
                    $check['name'],
                    $check['passed'] ? 'OK' : 'BLOQUEO',
                    $check['detail'],
                ],
                $checks
            )
        );

        if (collect($checks)->contains(fn (array $check): bool => ! $check['passed'])) {
            $this->error('Preflight bloqueado. No se ha modificado configuración, datos ni migraciones.');

            return self::FAILURE;
        }

        $this->info('Preflight backend válido. No se ha realizado ningún despliegue.');

        return self::SUCCESS;
    }
}
