<?php

namespace App\Console\Commands;

use App\Services\Media\Backfill\Reconciliation\GlobalReconciliationReport;
use App\Services\Media\Backfill\Reconciliation\ItemReconciliationReport;
use App\Services\Media\Backfill\Reconciliation\ObjectContextReport;
use App\Services\Media\Backfill\Reconciliation\ObjectReconciliationReport;
use App\Services\Media\Backfill\Reconciliation\ReconciliationInspector;
use App\Services\Media\Backfill\Reconciliation\RunReconciliationReport;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ResponsiveBackfillReconcileCommand extends Command
{
    private const INVALID_ARGUMENTS = 2;

    private const UNTRUSTWORTHY_EVIDENCE = 7;

    private const DEFAULT_LIMIT = 100;

    protected $signature = 'media:responsive-backfill-reconcile
        {--run= : UUID exacto de ejecución}
        {--item= : ID numérico exacto de item}
        {--object= : ID numérico exacto de objeto}
        {--after-id= : Cursor exclusivo solo para objetos no resueltos del resumen}
        {--limit= : Máximo por sección o detalle de run, entre 1 y 1000}';

    protected $description = 'Inspecciona evidencia de reconciliación responsive; D2-A es exclusivamente read-only';

    #[\Override]
    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ConsoleException) {
            $output->writeln('<error>Opciones no válidas. D2-A es exclusivamente read-only.</error>');

            return self::INVALID_ARGUMENTS;
        }
    }

    public function handle(ReconciliationInspector $inspector): int
    {
        try {
            [$selector, $value, $afterId, $limit] = $this->inspectionArguments();
        } catch (InvalidArgumentException) {
            $this->error('Opciones no válidas para D2-A read-only. Use como máximo uno de --run, --item o --object.');
            $this->renderNoMutationFooter();

            return self::INVALID_ARGUMENTS;
        }

        try {
            $this->line('Modo: RECONCILIATION READ-ONLY');
            $untrustworthy = match ($selector) {
                'run' => $this->renderRun($inspector->inspectRun($value, $limit)),
                'item' => $this->renderItem($inspector->inspectItem($value)),
                'object' => $this->renderObjectContext($inspector->inspectObjectContext($value)),
                default => $this->renderGlobal($inspector->inspectGlobal($afterId, $limit)),
            };
            $this->renderNoMutationFooter();

            return $untrustworthy ? self::UNTRUSTWORTHY_EVIDENCE : self::SUCCESS;
        } catch (Throwable) {
            $this->error('No se pudo clasificar la evidencia de forma fiable. No se realizó ninguna mutación.');
            $this->renderNoMutationFooter();

            return self::UNTRUSTWORTHY_EVIDENCE;
        }
    }

    /** @return array{?string, string|int|null, int, int} */
    private function inspectionArguments(): array
    {
        $provided = [];
        foreach (['run', 'item', 'object'] as $name) {
            if ($this->input->hasParameterOption('--'.$name, true)) {
                $provided[] = $name;
            }
        }
        if (count($provided) > 1) {
            throw new InvalidArgumentException;
        }
        $selector = $provided[0] ?? null;
        if ($selector !== null && $this->input->hasParameterOption('--after-id', true)) {
            throw new InvalidArgumentException;
        }
        if (in_array($selector, ['item', 'object'], true)
            && $this->input->hasParameterOption('--limit', true)) {
            throw new InvalidArgumentException;
        }
        $value = match ($selector) {
            'run' => $this->canonicalUuid($this->option('run')),
            'item', 'object' => $this->strictInteger($this->option($selector), 1, PHP_INT_MAX),
            default => null,
        };
        $afterId = $this->input->hasParameterOption('--after-id', true)
            ? $this->strictInteger($this->option('after-id'), 0, PHP_INT_MAX)
            : 0;
        $limit = $this->input->hasParameterOption('--limit', true)
            ? $this->strictInteger($this->option('limit'), 1, 1000)
            : self::DEFAULT_LIMIT;

        return [$selector, $value, $afterId, $limit];
    }

    private function canonicalUuid(mixed $value): string
    {
        if (! is_string($value)
            || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $value) !== 1) {
            throw new InvalidArgumentException;
        }

        return $value;
    }

    private function strictInteger(mixed $value, int $minimum, int $maximum): int
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (! is_string($value) || preg_match('/\A[0-9]+\z/D', $value) !== 1) {
            throw new InvalidArgumentException;
        }
        $normalized = ltrim($value, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $maximumString = (string) $maximum;
        if (strlen($normalized) > strlen($maximumString)
            || (strlen($normalized) === strlen($maximumString) && strcmp($normalized, $maximumString) > 0)) {
            throw new InvalidArgumentException;
        }
        $parsed = (int) $normalized;
        if ($parsed < $minimum) {
            throw new InvalidArgumentException;
        }

        return $parsed;
    }

    private function renderGlobal(GlobalReconciliationReport $report): bool
    {
        $this->line('Recovery barrier global (observación actual): '.$report->barrier->value);
        $this->line('Ejecuciones activas mostradas: '.count($report->activeRuns));
        foreach ($report->activeRuns as $run) {
            $this->renderRunSummary($run);
        }
        $this->line('Items sin terminar mostrados: '.count($report->unfinishedItems));
        foreach ($report->unfinishedItems as $item) {
            $this->renderItemSummary($item);
        }
        $this->line('Objetos no resueltos mostrados: '.count($report->unresolvedObjects));
        foreach ($report->unresolvedObjects as $object) {
            $this->renderObject($object);
        }
        if (count($report->unresolvedObjects) === $report->limit) {
            $last = $report->unresolvedObjects[array_key_last($report->unresolvedObjects)];
            $this->line('Continuación objetos: --after-id='.$last->id.' --limit='.$report->limit);
        }

        return $report->preventsTrustworthyClassification();
    }

    private function renderRun(RunReconciliationReport $report): bool
    {
        $this->renderRunSummary($report);
        $this->line('Selección: domain='.$report->domain.' after_id='.$report->afterId
            .' limit='.$report->limit.' upper_bound='.$report->upperBound.' checkpoint='.$report->checkpoint);
        $this->line('Items detallados: '.count($report->items).'/'.$report->totalItems);
        foreach ($report->items as $item) {
            $this->renderItemSummary($item);
        }
        $this->line('Detalle de items truncado por --limit: '.($report->itemsTruncated ? 'sí' : 'no'));

        return $report->preventsTrustworthyClassification();
    }

    private function renderRunSummary(RunReconciliationReport $report): void
    {
        $this->line('Run '.$report->runId.' state='.($report->state?->value ?? 'invalid')
            .' domain='.$report->domain.' flags='.$this->enumList($report->flags)
            .' reconciliation_event='.$this->reconciliationEvent(
                $report->hasReconciliationEvent,
                $report->reconciliationEventPointerInvalid,
                $report->reconciliationEventFingerprint,
            ));
        $this->line('  items_total='.$report->totalItems.' unfinished='.$report->unfinishedItems
            .' unresolved_objects='.$report->unresolvedObjects.' ambiguous_writes='.$report->ambiguousWrites
            .' cleanup_attention='.$report->cleanupAttention
            .' storage_identity_match='.$this->nullableBoolean($report->storageIdentityMatches));
    }

    private function renderItem(ItemReconciliationReport $report): bool
    {
        $this->renderItemSummary($report);
        $this->line('Durable counts: '.$this->counts($report->durableCounts));
        $this->line('Classification counts: '.$this->counts($report->classificationCounts));
        $this->line('Manifest observation: '.($report->manifest?->observation->value ?? 'not_planned'));
        foreach ($report->objects as $object) {
            $this->renderObject($object);
        }

        return $report->preventsTrustworthyClassification();
    }

    private function renderItemSummary(ItemReconciliationReport $report): void
    {
        $this->line('Item '.$report->id.' run='.$report->runId.' domain='.$report->domain
            .' entity='.$report->entityId.' phase='.($report->phase?->value ?? 'invalid')
            .' result='.($report->applyResult?->value ?? '-').' preflight='.$report->preflightClassification
            .' classification='.$report->classification->value
            .' functional_storage_set_exact='.($report->functionalStorageSetExact ? 'yes' : 'no')
            .' durable_reconciliation='.($report->reconciliationResultInvalid
                ? 'invalid'
                : ($report->reconciliationResult?->value ?? 'unresolved'))
            .' reconciliation_event='.$this->reconciliationEvent(
                $report->hasReconciliationEvent,
                $report->reconciliationResultInvalid,
                $report->reconciliationEventFingerprint,
            )
            .' action='.$report->recommendedAction());
    }

    private function renderObjectContext(ObjectContextReport $report): bool
    {
        $this->line('Context: run='.$report->runId.' run_state='.($report->runState?->value ?? 'invalid')
            .' item='.$report->itemId.' item_phase='.($report->itemPhase?->value ?? 'invalid')
            .' domain='.$report->domain.' entity='.$report->entityId);
        $this->renderObject($report->object);

        return $report->preventsTrustworthyClassification();
    }

    private function renderObject(ObjectReconciliationReport $report): void
    {
        $this->line('Object '.$report->id.' item='.$report->itemId.' key_sha256='.substr($report->keyFingerprint, 0, 16)
            .' kind='.($report->kind?->value ?? 'invalid').' write='.($report->writeState?->value ?? 'invalid')
            .' create='.($report->createState?->value ?? '-').' cleanup='.($report->cleanupState?->value ?? 'invalid')
            .' observation='.$report->observation->value.' attribution='.$report->attribution->value
            .' classification='.$report->classification->value.' has_etag='.($report->hasEtag ? 'yes' : 'no')
            .' has_version_identity='.($report->hasVersionIdentity ? 'yes' : 'no')
            .' durable_reconciliation='.($report->reconciliationResolutionInvalid
                ? 'invalid'
                : ($report->reconciliationResolution?->value ?? 'unresolved'))
            .' reconciliation_event='.$this->reconciliationEvent(
                $report->hasReconciliationEvent,
                $report->reconciliationResolutionInvalid,
                $report->reconciliationEventFingerprint,
            )
            .' action='.$report->recommendedAction());
    }

    /** @param array<string, int> $counts */
    private function counts(array $counts): string
    {
        if ($counts === []) {
            return '-';
        }
        ksort($counts);

        return implode(', ', array_map(
            static fn (string $key, int $count): string => $key.'='.$count,
            array_keys($counts),
            array_values($counts),
        ));
    }

    private function enumList(array $values): string
    {
        return $values === [] ? '-' : implode(',', array_map(static fn ($value): string => $value->value, $values));
    }

    private function nullableBoolean(?bool $value): string
    {
        return $value === null ? 'unavailable' : ($value ? 'yes' : 'no');
    }

    private function reconciliationEvent(bool $present, bool $invalid, ?string $fingerprint): string
    {
        if ($invalid) {
            return 'invalid';
        }
        if (! $present) {
            return 'none';
        }

        return $fingerprint === null ? 'invalid' : 'sha256:'.$fingerprint;
    }

    private function renderNoMutationFooter(): void
    {
        $this->line('Mutaciones journal: 0');
        $this->line('Escrituras/borrados storage: 0');
        $this->line('Recovery barrier modificado: no');
    }
}
