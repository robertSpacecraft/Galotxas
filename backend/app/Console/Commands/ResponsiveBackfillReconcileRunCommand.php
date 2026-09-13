<?php

namespace App\Console\Commands;

use App\Services\Media\Backfill\Reconciliation\ReconciliationCoordinator;
use App\Services\Media\Backfill\Reconciliation\ReconciliationInvocation;
use App\Services\Media\Backfill\Reconciliation\ReconciliationOutcome;
use App\Services\Media\Backfill\Reconciliation\ReconciliationReport;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ResponsiveBackfillReconcileRunCommand extends Command
{
    private const INVALID_ARGUMENTS = 2;

    private const MAINTENANCE_REQUIRED = 3;

    private const LOCK_BUSY = 4;

    private const LOCK_ACQUIRE_FAILED = 5;

    private const SAFE_REFUSAL = 6;

    private const BLOCKED_OR_UNCERTAIN = 7;

    protected $signature = 'media:responsive-backfill-reconcile-run
        {--run= : UUID canónico exacto del run durable}
        {--execute : Confirma la ejecución mutante; no omite ningún gate de seguridad}';

    protected $description = 'Ejecuta la reconciliación mutante de un run exacto mediante los gates de seguridad C2';

    #[\Override]
    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ConsoleException) {
            $output->writeln('<error>Opciones no válidas. Use únicamente --run=<uuid> --execute.</error>');

            return self::INVALID_ARGUMENTS;
        }
    }

    public function handle(ReconciliationCoordinator $coordinator): int
    {
        if (! $this->input->hasParameterOption('--execute', true) || $this->option('execute') !== true) {
            $this->error('La ejecución mutante exige --execute explícito.');
            $this->line('Inspección read-only disponible: media:responsive-backfill-reconcile --run=<uuid>');

            return self::INVALID_ARGUMENTS;
        }

        try {
            if (! $this->input->hasParameterOption('--run', true)) {
                throw new InvalidArgumentException;
            }
            $invocation = new ReconciliationInvocation($this->canonicalUuid($this->option('run')));
        } catch (InvalidArgumentException) {
            $this->error('Debe indicar --run con un UUID canónico en minúsculas.');

            return self::INVALID_ARGUMENTS;
        }

        $this->line('Modo: RECONCILIATION MUTATING / EXACT RUN');

        try {
            $report = $coordinator->run($invocation);
        } catch (Throwable) {
            $this->error('La reconciliación falló de forma no clasificable; el run seleccionado requiere revisión.');
            $this->line('Process exit: '.self::BLOCKED_OR_UNCERTAIN);

            return self::BLOCKED_OR_UNCERTAIN;
        }

        $exitCode = $this->exitCode($report);
        $this->renderReport($report, $exitCode);
        $this->renderOutcome($report->outcome, $exitCode);

        return $exitCode;
    }

    private function canonicalUuid(mixed $value): string
    {
        if (! is_string($value)
            || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $value) !== 1) {
            throw new InvalidArgumentException;
        }

        return $value;
    }

    private function exitCode(ReconciliationReport $report): int
    {
        return match ($report->outcome) {
            ReconciliationOutcome::Completed,
            ReconciliationOutcome::AlreadyClosed,
            ReconciliationOutcome::NoReconciliationRequired => self::SUCCESS,
            ReconciliationOutcome::MaintenanceRequired => self::MAINTENANCE_REQUIRED,
            ReconciliationOutcome::LockBusy => self::LOCK_BUSY,
            ReconciliationOutcome::LockAcquireFailed => self::LOCK_ACQUIRE_FAILED,
            ReconciliationOutcome::Blocked => self::BLOCKED_OR_UNCERTAIN,
            ReconciliationOutcome::SafetyFailure => $report->attemptId !== null
                || $report->durableProgressOccurred === true
                    ? self::BLOCKED_OR_UNCERTAIN
                    : self::SAFE_REFUSAL,
        };
    }

    private function renderReport(ReconciliationReport $report, int $exitCode): void
    {
        $this->line('Run: '.$report->runId);
        $this->line('Outcome: '.$report->outcome->value);
        $this->line('No-op: '.($report->noOp ? 'yes' : 'no'));
        $this->line('Attempt: '.($report->attemptId ?? '-'));
        $this->line('Items traversed: '.$report->itemsTraversed);
        $this->line('Items skipped: '.$report->itemsSkipped);
        $this->line('Items resolved no-effect: '.$report->itemsResolvedNoEffect);
        $this->line('Items resolved forward: '.$report->itemsResolvedForward);
        $this->line('First blocker: '.($report->firstBlocker?->value ?? '-'));
        $this->line('Run closure appended: '.($report->runClosureAppended ? 'yes' : 'no'));
        $this->line('Final run state: '.($report->finalRunState?->value ?? '-'));
        $this->line('Global recovery barrier: '.($report->globalRecoveryBarrier?->value ?? '-'));
        $this->line('Durable progress: '.$this->nullableBoolean($report->durableProgressOccurred));
        $this->line('Process exit: '.$exitCode);
    }

    private function renderOutcome(ReconciliationOutcome $outcome, int $exitCode): void
    {
        match ($outcome) {
            ReconciliationOutcome::Completed => $this->info('El run seleccionado se reconcilió correctamente.'),
            ReconciliationOutcome::AlreadyClosed => $this->info('El run seleccionado ya tenía un cierre reconciliado válido.'),
            ReconciliationOutcome::NoReconciliationRequired => $this->info('El run seleccionado no requiere reconciliación.'),
            ReconciliationOutcome::MaintenanceRequired => $this->error('Se requiere mantenimiento de Laravel; el comando no lo activa automáticamente.'),
            ReconciliationOutcome::LockBusy => $this->error('Reconciliación no iniciada: el lock está ocupado.'),
            ReconciliationOutcome::LockAcquireFailed => $this->error('Reconciliación no iniciada: no se pudo adquirir el lock.'),
            ReconciliationOutcome::Blocked => $this->error('El run seleccionado quedó bloqueado de forma fail-closed.'),
            ReconciliationOutcome::SafetyFailure => $this->error($exitCode === self::SAFE_REFUSAL
                ? 'Reconciliación rechazada de forma segura antes de progreso durable.'
                : 'El run seleccionado quedó bloqueado o incierto tras poder iniciarse un intento durable.'),
        };
    }

    private function nullableBoolean(?bool $value): string
    {
        return $value === null ? 'unknown' : ($value ? 'yes' : 'no');
    }
}
