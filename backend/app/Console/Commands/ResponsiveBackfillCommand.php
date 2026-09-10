<?php

namespace App\Console\Commands;

use App\Services\Media\Backfill\DryRunAnomaly;
use App\Services\Media\Backfill\DryRunReport;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\ResponsiveBackfillDryRun;
use Illuminate\Console\Command;
use Throwable;

class ResponsiveBackfillCommand extends Command
{
    private const INVALID_OR_BLOCKED = 2;

    protected $signature = 'media:responsive-backfill
        {--domain=* : Dominios a inspeccionar; repetible}
        {--after-id= : ID exclusivo inicial; exige un único dominio}
        {--limit= : Máximo global de referencias observadas, entre 1 y 1000}';

    protected $description = 'Inspecciona en modo dry-run las referencias aptas para backfill responsive';

    public function handle(ResponsiveBackfillDryRun $runner): int
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

    private function anomalyLine(DryRunAnomaly $anomaly): string
    {
        $reasons = $anomaly->reasonCodes === [] ? '-' : implode(',', $anomaly->reasonCodes);

        return 'Anomalía: '.$anomaly->domain->value.'#'.$anomaly->entityId.' '
            .$anomaly->classification->value.' ['.$reasons.']';
    }
}
