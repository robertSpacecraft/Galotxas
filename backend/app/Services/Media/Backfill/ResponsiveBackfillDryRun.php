<?php

namespace App\Services\Media\Backfill;

use InvalidArgumentException;

class ResponsiveBackfillDryRun
{
    private const ANOMALY_DETAIL_LIMIT = 100;

    public function __construct(
        private readonly ManagedMediaReferenceRegistry $registry,
        private readonly ResponsiveBackfillPreflight $preflight,
    ) {}

    /** @param list<ManagedMediaDomain> $domains */
    public function run(array $domains, int $afterId = 0, ?int $limit = null): DryRunReport
    {
        $domains = $this->canonicalDomains($domains);
        if ($domains === [] || $afterId < 0 || ($limit !== null && ($limit < 1 || $limit > 1000))
            || ($afterId > 0 && count($domains) !== 1)) {
            throw new InvalidArgumentException('Las opciones del dry-run no son válidas.');
        }

        $upperBounds = [];
        foreach ($domains as $domain) {
            $upperBounds[$domain->value] = $this->registry->upperBound($domain);
        }

        $counts = array_fill_keys(array_map(
            static fn (PreflightClassification $classification): string => $classification->value,
            PreflightClassification::cases(),
        ), 0);
        $observed = 0;
        $candidates = 0;
        $blockers = 0;
        $anomalies = [];
        $omittedAnomalies = 0;
        $truncated = false;
        $lastDomain = null;
        $lastId = null;

        foreach ($domains as $domainIndex => $domain) {
            $cursor = count($domains) === 1 ? $afterId : 0;
            $upperBound = $upperBounds[$domain->value];
            if ($upperBound <= $cursor) {
                continue;
            }

            while ($limit === null || $observed < $limit) {
                $batch = $this->registry->batch($domain, $cursor, 1, $upperBound);
                if ($batch === []) {
                    break;
                }

                $reference = $batch[0];
                $cursor = $reference->id;
                $lastDomain = $domain;
                $lastId = $cursor;
                $observed++;

                $result = $this->preflight->inspect($reference);
                $counts[$result->classification->value]++;
                if (DryRunClassificationPolicy::isCandidate($result->classification)) {
                    $candidates++;
                }
                if (DryRunClassificationPolicy::isBlocking($result->classification)) {
                    $blockers++;
                    if (count($anomalies) < self::ANOMALY_DETAIL_LIMIT) {
                        $anomalies[] = new DryRunAnomaly(
                            $domain,
                            $reference->id,
                            $result->classification,
                            array_values(array_unique(array_map(
                                static fn (InspectionReason $reason): string => $reason->value,
                                $result->reasons,
                            ))),
                        );
                    } else {
                        $omittedAnomalies++;
                    }
                }

                unset($result, $reference, $batch);

                if ($limit !== null && $observed === $limit) {
                    $truncated = $this->hasRemainingReferences($domains, $upperBounds, $domainIndex, $cursor);
                    break 2;
                }
            }
        }

        return new DryRunReport(
            $domains,
            $upperBounds,
            $observed,
            $counts,
            $candidates,
            $blockers,
            $anomalies,
            $omittedAnomalies,
            $truncated,
            $lastDomain,
            $lastId,
        );
    }

    /**
     * @param  list<ManagedMediaDomain>  $domains
     * @param  array<string, int>  $upperBounds
     */
    private function hasRemainingReferences(array $domains, array $upperBounds, int $domainIndex, int $cursor): bool
    {
        foreach ($domains as $index => $domain) {
            if ($index < $domainIndex) {
                continue;
            }
            $afterId = $index === $domainIndex ? $cursor : 0;
            if ($this->registry->hasReferences($domain, $afterId, $upperBounds[$domain->value])) {
                return true;
            }
        }

        return false;
    }

    /** @param list<ManagedMediaDomain> $domains
     * @return list<ManagedMediaDomain>
     */
    private function canonicalDomains(array $domains): array
    {
        $selected = [];
        foreach ($domains as $domain) {
            if (! $domain instanceof ManagedMediaDomain || isset($selected[$domain->value])) {
                throw new InvalidArgumentException('Los dominios del dry-run no son válidos.');
            }
            $selected[$domain->value] = true;
        }

        return array_values(array_filter(
            ManagedMediaDomain::cases(),
            static fn (ManagedMediaDomain $domain): bool => isset($selected[$domain->value]),
        ));
    }
}
