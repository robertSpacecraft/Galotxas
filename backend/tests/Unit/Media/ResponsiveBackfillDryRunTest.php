<?php

namespace Tests\Unit\Media;

use App\Services\Media\Backfill\DryRunClassificationPolicy;
use App\Services\Media\Backfill\InspectionReason;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReference;
use App\Services\Media\Backfill\ManagedMediaReferenceRegistry;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\PreflightResult;
use App\Services\Media\Backfill\ResponsiveBackfillDryRun;
use App\Services\Media\Backfill\ResponsiveBackfillPreflight;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResponsiveBackfillDryRunTest extends TestCase
{
    public function test_traversal_uses_canonical_domain_order_and_sparse_primary_keys(): void
    {
        $registry = new InMemoryManagedMediaReferenceRegistry([
            ManagedMediaDomain::Category->value => [$this->reference(ManagedMediaDomain::Category, 20)],
            ManagedMediaDomain::Avatar->value => [
                $this->reference(ManagedMediaDomain::Avatar, 9),
                $this->reference(ManagedMediaDomain::Avatar, 2),
            ],
            ManagedMediaDomain::News->value => [$this->reference(ManagedMediaDomain::News, 7)],
        ]);
        $preflight = new StubResponsiveBackfillPreflight;

        $report = (new ResponsiveBackfillDryRun($registry, $preflight))->run([
            ManagedMediaDomain::Category,
            ManagedMediaDomain::News,
            ManagedMediaDomain::Avatar,
        ]);

        $this->assertSame(['avatar', 'news', 'category'], array_column($report->domains, 'value'));
        $this->assertSame(['avatar#2', 'avatar#9', 'news#7', 'category#20'], $preflight->observed);
        $this->assertSame(4, $report->observedCount);
        $this->assertFalse($report->truncated);
    }

    public function test_captured_upper_bound_excludes_a_later_insert(): void
    {
        $registry = new InMemoryManagedMediaReferenceRegistry([
            'avatar' => [$this->reference(ManagedMediaDomain::Avatar, 3)],
        ]);
        $registry->afterUpperBound = function (ManagedMediaDomain $domain, InMemoryManagedMediaReferenceRegistry $registry): void {
            $registry->rows[$domain->value][] = $this->reference($domain, 99);
        };
        $preflight = new StubResponsiveBackfillPreflight;

        $report = (new ResponsiveBackfillDryRun($registry, $preflight))->run([ManagedMediaDomain::Avatar]);

        $this->assertSame(['avatar' => 3], $report->upperBounds);
        $this->assertSame(['avatar#3'], $preflight->observed);
    }

    public function test_global_limit_counts_references_instead_of_candidates(): void
    {
        $registry = new InMemoryManagedMediaReferenceRegistry([
            'avatar' => [$this->reference(ManagedMediaDomain::Avatar, 1)],
            'news' => [
                $this->reference(ManagedMediaDomain::News, 10),
                $this->reference(ManagedMediaDomain::News, 11),
            ],
        ]);
        $preflight = new StubResponsiveBackfillPreflight([
            'avatar#1' => PreflightClassification::LegacyBackfillable,
            'news#10' => PreflightClassification::ResponsiveOk,
        ]);

        $report = (new ResponsiveBackfillDryRun($registry, $preflight))->run([
            ManagedMediaDomain::News,
            ManagedMediaDomain::Avatar,
        ], limit: 2);

        $this->assertSame(['avatar#1', 'news#10'], $preflight->observed);
        $this->assertSame(2, $report->observedCount);
        $this->assertSame(1, $report->candidateCount);
        $this->assertTrue($report->truncated);
        $this->assertSame(ManagedMediaDomain::News, $report->lastObservedDomain);
        $this->assertSame(10, $report->lastObservedId);
    }

    public function test_limit_equal_to_the_complete_range_is_not_reported_as_truncated(): void
    {
        $registry = new InMemoryManagedMediaReferenceRegistry([
            'avatar' => [$this->reference(ManagedMediaDomain::Avatar, 4)],
        ]);

        $report = (new ResponsiveBackfillDryRun($registry, new StubResponsiveBackfillPreflight))
            ->run([ManagedMediaDomain::Avatar], limit: 1);

        $this->assertSame(1, $report->observedCount);
        $this->assertFalse($report->truncated);
    }

    #[DataProvider('classificationPolicy')]
    public function test_every_classification_has_an_explicit_dry_run_policy(
        PreflightClassification $classification,
        bool $candidate,
        bool $blocking,
    ): void {
        $this->assertSame($candidate, DryRunClassificationPolicy::isCandidate($classification));
        $this->assertSame($blocking, DryRunClassificationPolicy::isBlocking($classification));
    }

    public static function classificationPolicy(): iterable
    {
        yield 'responsive_ok' => [PreflightClassification::ResponsiveOk, false, false];
        yield 'excluded_null' => [PreflightClassification::ExcludedNull, false, false];
        yield 'excluded_deleted' => [PreflightClassification::ExcludedDeleted, false, false];
        yield 'legacy_backfillable' => [PreflightClassification::LegacyBackfillable, true, false];
        yield 'invalid_reference' => [PreflightClassification::InvalidReference, false, true];
        yield 'reference_conflict' => [PreflightClassification::ReferenceConflict, false, true];
        yield 'master_missing' => [PreflightClassification::MasterMissing, false, true];
        yield 'master_unprocessable' => [PreflightClassification::MasterUnprocessable, false, true];
        yield 'manifest_invalid' => [PreflightClassification::ManifestInvalid, false, true];
        yield 'responsive_incomplete' => [PreflightClassification::ResponsiveIncomplete, false, true];
        yield 'partial_collision' => [PreflightClassification::PartialCollision, false, true];
        yield 'metadata_mismatch' => [PreflightClassification::MetadataMismatch, false, true];
        yield 'inspection_failed' => [PreflightClassification::InspectionFailed, false, true];
    }

    public function test_report_contains_no_preflight_or_prepared_payloads(): void
    {
        $reference = $this->reference(ManagedMediaDomain::Avatar, 1);
        $registry = new InMemoryManagedMediaReferenceRegistry(['avatar' => [$reference]]);
        $preflight = new StubResponsiveBackfillPreflight([
            'avatar#1' => PreflightClassification::InvalidReference,
        ]);

        $report = (new ResponsiveBackfillDryRun($registry, $preflight))->run([ManagedMediaDomain::Avatar]);
        $serialized = serialize($report);

        $this->assertStringNotContainsString(PreflightResult::class, $serialized);
        $this->assertStringNotContainsString('PreparedResponsiveDerivatives', $serialized);
        $this->assertSame(['invalid_reference'], array_map(
            static fn ($anomaly): string => $anomaly->classification->value,
            $report->anomalies,
        ));
    }

    public function test_anomaly_details_are_bounded_while_totals_remain_exact(): void
    {
        $references = array_map(
            fn (int $id): ManagedMediaReference => $this->reference(ManagedMediaDomain::Avatar, $id),
            range(1, 150),
        );
        $registry = new InMemoryManagedMediaReferenceRegistry(['avatar' => $references]);
        $classifications = array_fill_keys(
            array_map(static fn (int $id): string => 'avatar#'.$id, range(1, 150)),
            PreflightClassification::InvalidReference,
        );

        $report = (new ResponsiveBackfillDryRun(
            $registry,
            new StubResponsiveBackfillPreflight($classifications),
        ))->run([ManagedMediaDomain::Avatar]);

        $this->assertSame(150, $report->observedCount);
        $this->assertSame(150, $report->blockerCount);
        $this->assertSame(150, $report->classificationCounts['invalid_reference']);
        $this->assertCount(100, $report->anomalies);
        $this->assertSame(50, $report->omittedAnomalyCount);
    }

    private function reference(ManagedMediaDomain $domain, int $id): ManagedMediaReference
    {
        return new ManagedMediaReference($domain, $id, null);
    }
}

final class InMemoryManagedMediaReferenceRegistry extends ManagedMediaReferenceRegistry
{
    /** @var array<string, list<ManagedMediaReference>> */
    public array $rows;

    public ?Closure $afterUpperBound = null;

    /** @param array<string, list<ManagedMediaReference>> $rows */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function upperBound(ManagedMediaDomain $domain): int
    {
        $ids = array_column($this->rows[$domain->value] ?? [], 'id');
        $upperBound = $ids === [] ? 0 : max($ids);
        if ($this->afterUpperBound !== null) {
            $callback = $this->afterUpperBound;
            $this->afterUpperBound = null;
            $callback($domain, $this);
        }

        return $upperBound;
    }

    public function batch(ManagedMediaDomain $domain, int $afterId = 0, int $limit = 100, ?int $throughId = null): array
    {
        $rows = array_values(array_filter(
            $this->rows[$domain->value] ?? [],
            static fn (ManagedMediaReference $reference): bool => $reference->id > $afterId
                && ($throughId === null || $reference->id <= $throughId),
        ));
        usort($rows, static fn (ManagedMediaReference $left, ManagedMediaReference $right): int => $left->id <=> $right->id);

        return array_slice($rows, 0, $limit);
    }

    public function hasReferences(ManagedMediaDomain $domain, int $afterId, int $throughId): bool
    {
        return $this->batch($domain, $afterId, 1, $throughId) !== [];
    }
}

final class StubResponsiveBackfillPreflight extends ResponsiveBackfillPreflight
{
    /** @var list<string> */
    public array $observed = [];

    /** @param array<string, PreflightClassification> $classifications */
    public function __construct(private readonly array $classifications = []) {}

    public function inspect(ManagedMediaReference $reference): PreflightResult
    {
        $key = $reference->domain->value.'#'.$reference->id;
        $this->observed[] = $key;
        $classification = $this->classifications[$key] ?? PreflightClassification::ExcludedNull;

        return new PreflightResult(
            $reference,
            $classification,
            DryRunClassificationPolicy::isBlocking($classification) ? [InspectionReason::InvalidReference] : [],
        );
    }
}
