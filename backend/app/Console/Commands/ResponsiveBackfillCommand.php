<?php

namespace App\Console\Commands;

use App\Services\Media\Backfill\ApplyInvocation;
use App\Services\Media\Backfill\ApplyOutcome;
use App\Services\Media\Backfill\ApplyReport;
use App\Services\Media\Backfill\DryRunAnomaly;
use App\Services\Media\Backfill\DryRunReport;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\ResponsiveBackfillApply;
use App\Services\Media\Backfill\ResponsiveBackfillDryRun;
use App\Services\Media\Backfill\Safety\ApplyResult;
use Illuminate\Console\Command;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ResponsiveBackfillCommand extends Command
{
    private const INVALID_OR_BLOCKED = 2;

    private const MAINTENANCE_REQUIRED = 3;

    private const LOCK_BUSY = 4;

    private const LOCK_ACQUIRE_FAILED = 5;

    private const SAFE_FAILURE = 6;

    private const RECONCILIATION_REQUIRED = 7;

    protected $signature = 'media:responsive-backfill
        {--domain=* : Dominios a inspeccionar; repetible}
        {--after-id= : ID exclusivo inicial; exige un único dominio}
        {--limit= : Máximo global de referencias observadas, entre 1 y 1000}
        {--apply : Ejecuta el backfill responsive controlado}';

    protected $description = 'Inspecciona o ejecuta de forma controlada el backfill responsive';

    #[\Override]
    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ConsoleException $error) {
            if (! $input->hasParameterOption(['--apply', '--resume'], true)) {
                throw $error;
            }

            $output->writeln('<error>Opciones APPLY no válidas. Revise --domain, --after-id y --limit; --resume no está permitido.</error>');

            return self::INVALID_OR_BLOCKED;
        }
    }

    public function handle(ResponsiveBackfillDryRun $runner, ResponsiveBackfillApply $apply): int
    {
        if ($this->input->hasParameterOption('--apply', true)) {
            return $this->handleApply($apply);
        }

        return $this->handleDryRun($runner);
    }

    private function handleDryRun(ResponsiveBackfillDryRun $runner): int
    {
        try {
            $domains = $this->domains();
            $afterProvided = $this->input->hasParameterOption('--after-id', true);
            $afterId = $afterProvided ? $this->strictInteger($this->option('after-id'), 0, PHP_INT_MAX) : 0;
            $limit = $this->input->hasParameterOption('--limit', true)
                ? $this->strictInteger($this->option('limit'), 1, 1000)
                : null;
            if ($afterProvided && count($domains) !== 1) {
                throw new \InvalidArgumentException;
            }
        } catch (\InvalidArgumentException) {
            $this->error('Opciones no válidas. Revise --domain, --after-id y --limit.');
            $this->line('Escrituras en storage: 0');

            return self::INVALID_OR_BLOCKED;
        }

        try {
            $report = $runner->run($domains, $afterId, $limit);
        } catch (Throwable) {
            $this->error('No se pudo completar la inspección read-only.');
            $this->line('Escrituras en storage: 0');

            return self::FAILURE;
        }

        $this->renderReport($report);

        return $report->hasBlockers() ? self::INVALID_OR_BLOCKED : self::SUCCESS;
    }

    private function handleApply(ResponsiveBackfillApply $apply): int
    {
        try {
            if ($this->option('apply') !== true
                || ! $this->input->hasParameterOption('--domain', true)
                || ! $this->input->hasParameterOption('--limit', true)) {
                throw new \InvalidArgumentException;
            }

            $domains = $this->domains();
            if (count($domains) !== 1) {
                throw new \InvalidArgumentException;
            }

            $afterId = $this->input->hasParameterOption('--after-id', true)
                ? $this->strictInteger($this->option('after-id'), 0, PHP_INT_MAX)
                : 0;
            $limit = $this->strictInteger($this->option('limit'), 1, 1000);
            $invocation = new ApplyInvocation($domains[0], $afterId, $limit);
        } catch (\InvalidArgumentException) {
            $this->error('Opciones APPLY no válidas. Revise --domain, --after-id y --limit; --resume no está permitido.');

            return self::INVALID_OR_BLOCKED;
        }

        $this->warn(
            'ADVERTENCIA: el comando verifica el mantenimiento de Laravel, pero no puede comprobar '
            .'que workers u otros escritores externos estén detenidos. El operador debe detenerlos antes de APPLY.',
        );

        try {
            $report = $apply->run($invocation);
        } catch (Throwable) {
            $this->error('APPLY no pudo completarse de forma segura. Se requiere revisión y reconciliación antes de continuar.');

            return self::RECONCILIATION_REQUIRED;
        }

        $this->renderApplyReport($report);
        $this->renderApplyOutcome($report->outcome);

        return $this->applyExitCode($report->outcome);
    }

    /** @return list<ManagedMediaDomain> */
    private function domains(): array
    {
        $values = $this->option('domain');
        if (! is_array($values)) {
            throw new \InvalidArgumentException;
        }
        if ($values === []) {
            return ManagedMediaDomain::cases();
        }

        $selected = [];
        foreach ($values as $value) {
            if (! is_string($value) || ($domain = ManagedMediaDomain::tryFrom($value)) === null
                || isset($selected[$domain->value])) {
                throw new \InvalidArgumentException;
            }
            $selected[$domain->value] = true;
        }

        return array_values(array_filter(
            ManagedMediaDomain::cases(),
            static fn (ManagedMediaDomain $domain): bool => isset($selected[$domain->value]),
        ));
    }

    private function strictInteger(mixed $value, int $minimum, int $maximum): int
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (! is_string($value) || preg_match('/\A[0-9]+\z/D', $value) !== 1) {
            throw new \InvalidArgumentException;
        }
        $normalized = ltrim($value, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $maximumString = (string) $maximum;
        if (strlen($normalized) > strlen($maximumString)
            || (strlen($normalized) === strlen($maximumString) && strcmp($normalized, $maximumString) > 0)) {
            throw new \InvalidArgumentException;
        }
        $parsed = (int) $normalized;
        if ($parsed < $minimum) {
            throw new \InvalidArgumentException;
        }

        return $parsed;
    }

    private function renderReport(DryRunReport $report): void
    {
        $this->line('Modo: DRY-RUN');
        $this->line('Dominios: '.implode(', ', array_map(
            static fn (ManagedMediaDomain $domain): string => $domain->value,
            $report->domains,
        )));
        $this->line('Límites superiores: '.implode(', ', array_map(
            static fn (string $domain, int $id): string => $domain.'='.$id,
            array_keys($report->upperBounds),
            array_values($report->upperBounds),
        )));
        $this->line('Referencias observadas: '.$report->observedCount);
        $this->line('Clasificaciones: '.implode(', ', array_map(
            static fn (PreflightClassification $classification): string => $classification->value.'='.$report->classificationCounts[$classification->value],
            PreflightClassification::cases(),
        )));
        $this->line('Candidatas: '.$report->candidateCount);
        $this->line('Bloqueos: '.$report->blockerCount);

        foreach ($report->anomalies as $anomaly) {
            $this->line($this->anomalyLine($anomaly));
        }
        if ($report->omittedAnomalyCount > 0) {
            $this->line('Anomalías omitidas del detalle: '.$report->omittedAnomalyCount);
        }

        $this->line('Truncado por --limit: '.($report->truncated ? 'sí' : 'no'));
        if ($report->lastObservedDomain !== null && $report->lastObservedId !== null) {
            $this->line('Última referencia: '.$report->lastObservedDomain->value.'#'.$report->lastObservedId);
        }
        if ($report->truncated && count($report->domains) === 1) {
            $this->line('Continuación: --domain='.$report->lastObservedDomain->value.' --after-id='.$report->lastObservedId);
        } elseif ($report->truncated) {
            $this->line('Continuación: ejecutar por dominio; el dry-run multidominio no tiene cursor compuesto.');
        }
        $this->line('Escrituras en storage: 0');
    }

    private function renderApplyReport(ApplyReport $report): void
    {
        $this->line('Modo: APPLY');
        $this->line('Dominio: '.$report->domain->value);
        $this->line('After-id: '.$report->afterId);
        $this->line('Límite superior: '.($report->upperBound ?? '-'));
        $this->line('Límite: '.$report->limit);
        $this->line('Referencias observadas: '.$report->observedCount);
        $this->line('Clasificaciones: '.$this->applyClassificationCounts($report));
        $this->line('Resultados: '.$this->applyResultCounts($report));
        $this->line('Checkpoint: '.($report->checkpoint ?? '-'));
        $this->line('Estado del run: '.($report->runState?->value ?? '-'));
        $this->line('Reconciliación requerida: '.($report->reconciliationRequired ? 'sí' : 'no'));
        $this->line('Resultado: '.$report->outcome->value);

        if ($report->continuationAfterId !== null) {
            $this->line('Nueva invocación sugerida: --apply --domain='.$report->domain->value
                .' --after-id='.$report->continuationAfterId.' --limit='.$report->limit);
        }
    }

    private function applyClassificationCounts(ApplyReport $report): string
    {
        $counts = [];
        foreach (PreflightClassification::cases() as $classification) {
            if (array_key_exists($classification->value, $report->classificationCounts)) {
                $counts[] = $classification->value.'='.$report->classificationCounts[$classification->value];
            }
        }

        return $counts === [] ? '-' : implode(', ', $counts);
    }

    private function applyResultCounts(ApplyReport $report): string
    {
        $counts = [];
        foreach (ApplyResult::cases() as $result) {
            if (array_key_exists($result->value, $report->resultCounts)) {
                $counts[] = $result->value.'='.$report->resultCounts[$result->value];
            }
        }

        return $counts === [] ? '-' : implode(', ', $counts);
    }

    private function renderApplyOutcome(ApplyOutcome $outcome): void
    {
        match ($outcome) {
            ApplyOutcome::Success => $this->info('APPLY completado.'),
            ApplyOutcome::FirstPassBlocked => $this->error('APPLY bloqueado por la primera pasada.'),
            ApplyOutcome::MaintenanceRequired => $this->error('APPLY requiere el mantenimiento de Laravel.'),
            ApplyOutcome::LockBusy => $this->error('APPLY no iniciado: el lock está ocupado.'),
            ApplyOutcome::LockAcquireFailed => $this->error('APPLY no iniciado: no se pudo adquirir el lock.'),
            ApplyOutcome::SafeFailure => $this->error('APPLY terminó con un fallo conocido y seguro.'),
            ApplyOutcome::ReconciliationRequired => $this->error('APPLY no resuelto: se requiere D2/reconciliación.'),
        };
    }

    private function applyExitCode(ApplyOutcome $outcome): int
    {
        return match ($outcome) {
            ApplyOutcome::Success => self::SUCCESS,
            ApplyOutcome::FirstPassBlocked => self::INVALID_OR_BLOCKED,
            ApplyOutcome::MaintenanceRequired => self::MAINTENANCE_REQUIRED,
            ApplyOutcome::LockBusy => self::LOCK_BUSY,
            ApplyOutcome::LockAcquireFailed => self::LOCK_ACQUIRE_FAILED,
            ApplyOutcome::SafeFailure => self::SAFE_FAILURE,
            ApplyOutcome::ReconciliationRequired => self::RECONCILIATION_REQUIRED,
        };
    }

    private function anomalyLine(DryRunAnomaly $anomaly): string
    {
        $reasons = $anomaly->reasonCodes === [] ? '-' : implode(',', $anomaly->reasonCodes);

        return 'Anomalía: '.$anomaly->domain->value.'#'.$anomaly->entityId.' '
            .$anomaly->classification->value.' ['.$reasons.']';
    }
}
