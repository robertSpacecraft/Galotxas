<?php

namespace App\Services\Media\Backfill;

use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\RunState;

final readonly class ApplyReport
{
    public bool $reconciliationRequired;

    /**
     * @param  array<string, int>  $classificationCounts
     * @param  array<string, int>  $resultCounts
     */
    public function __construct(
        public ApplyOutcome $outcome,
        public ManagedMediaDomain $domain,
        public int $afterId,
        public ?int $upperBound,
        public int $limit,
        public int $observedCount,
        public array $classificationCounts,
        public array $resultCounts,
        public ?int $checkpoint,
        public ?RunState $runState,
        public ?int $continuationAfterId = null,
    ) {
        $this->reconciliationRequired = $outcome === ApplyOutcome::ReconciliationRequired;

        if ($afterId < 0 || ($upperBound !== null && $upperBound < 0)
            || $limit < 1 || $limit > 1000 || $observedCount < 0
            || ($checkpoint !== null && $checkpoint < 0)
            || ($continuationAfterId !== null && $continuationAfterId < 0)) {
            throw new \InvalidArgumentException('El informe APPLY no es válido.');
        }
        foreach ($classificationCounts as $classification => $count) {
            if (PreflightClassification::tryFrom($classification) === null || ! is_int($count) || $count < 0) {
                throw new \InvalidArgumentException('El informe APPLY no es válido.');
            }
        }
        foreach ($resultCounts as $result => $count) {
            if (ApplyResult::tryFrom($result) === null || ! is_int($count) || $count < 0) {
                throw new \InvalidArgumentException('El informe APPLY no es válido.');
            }
        }
        if ($continuationAfterId !== null
            && ($outcome !== ApplyOutcome::Success || $runState !== RunState::Completed || $checkpoint !== $continuationAfterId)) {
            throw new \InvalidArgumentException('El informe APPLY no es válido.');
        }
    }

    public function withOutcome(ApplyOutcome $outcome): self
    {
        return new self(
            $outcome,
            $this->domain,
            $this->afterId,
            $this->upperBound,
            $this->limit,
            $this->observedCount,
            $this->classificationCounts,
            $this->resultCounts,
            $this->checkpoint,
            $this->runState,
        );
    }
}
