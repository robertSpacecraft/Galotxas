<?php

namespace Tests\Unit\Media;

use App\Services\Media\Backfill\Reconciliation\ExactObjectObservation;
use App\Services\Media\Backfill\Reconciliation\ExactObjectObserver;
use App\Services\Media\Backfill\Reconciliation\ObjectAttribution;
use App\Services\Media\Backfill\Reconciliation\ObjectClassification;
use App\Services\Media\Backfill\Reconciliation\ObjectEvidenceClassifier;
use App\Services\Media\Backfill\Reconciliation\ObjectObservation;
use App\Services\Media\Backfill\Reconciliation\ReconciliationBackendMode;
use App\Services\Media\Backfill\Reconciliation\StorageObservationCapability;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use App\Services\Media\ManifestImage;
use Illuminate\Filesystem\FilesystemAdapter;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\TestCase;

class BackfillReconciliationClassifierTest extends TestCase
{
    use ResponsiveImageFixtures;

    #[DataProvider('classificationCases')]
    public function test_combines_durable_attribution_cleanup_and_point_in_time_observation(
        string $write,
        ?string $create,
        string $cleanup,
        ObjectObservation $observation,
        ObjectAttribution $attribution,
        ObjectClassification $classification,
    ): void {
        $report = (new ObjectEvidenceClassifier)->report(
            $this->row($write, $create, $cleanup),
            $observation,
        );

        $this->assertSame($attribution, $report->attribution);
        $this->assertSame($classification, $report->classification);
        $this->assertSame($observation, $report->observation);
    }

    public static function classificationCases(): array
    {
        $cases = [
            'planned' => ['planned', null, 'not_required', ObjectObservation::AbsentNow, ObjectAttribution::NotDispatched, ObjectClassification::ResolvedByJournal],
            'collision' => ['rejected', 'rejected', 'not_required', ObjectObservation::DifferentContentPresent, ObjectAttribution::RejectedCollision, ObjectClassification::Collision],
            'failed' => ['rejected', 'failed', 'not_required', ObjectObservation::AbsentNow, ObjectAttribution::FailedWithoutWrite, ObjectClassification::KnownFailedWithoutWrite],
        ];
        foreach (['intent' => null, 'unknown' => 'unknown'] as $write => $create) {
            foreach ([
                'absent' => [ObjectObservation::AbsentNow, ObjectClassification::AmbiguousAbsentNow],
                'exact' => [ObjectObservation::ExpectedContentPresent, ObjectClassification::AmbiguousExpectedPresent],
                'different' => [ObjectObservation::DifferentContentPresent, ObjectClassification::AmbiguousDifferentPresent],
                'unreadable' => [ObjectObservation::Unreadable, ObjectClassification::AmbiguousUnreadable],
            ] as $name => [$observation, $classification]) {
                $cases[$write.'_'.$name] = [$write, $create, 'not_required', $observation, ObjectAttribution::AttemptAmbiguous, $classification];
            }
        }
        foreach ([
            'absent' => [ObjectObservation::AbsentNow, ObjectClassification::CreatedMissingNow],
            'exact' => [ObjectObservation::ExpectedContentPresent, ObjectClassification::CreatedExpectedPresent],
            'different' => [ObjectObservation::DifferentContentPresent, ObjectClassification::CreatedDifferentPresent],
            'unreadable' => [ObjectObservation::Unreadable, ObjectClassification::CreatedUnreadable],
        ] as $name => [$observation, $classification]) {
            $cases['created_'.$name] = ['created', 'created', 'not_required', $observation, ObjectAttribution::CreatedReceipt, $classification];
        }
        foreach ([
            'pending' => [ObjectClassification::CleanupPendingAbsentNow, ObjectClassification::CleanupPendingPresent],
            'failed' => [ObjectClassification::CleanupFailedAbsentNow, ObjectClassification::CleanupFailedPresent],
            'unknown' => [ObjectClassification::CleanupUnknownAbsentNow, ObjectClassification::CleanupUnknownPresent],
        ] as $cleanup => [$absent, $present]) {
            $cases['cleanup_'.$cleanup.'_absent'] = ['created', 'created', $cleanup, ObjectObservation::AbsentNow, ObjectAttribution::CreatedReceipt, $absent];
            $cases['cleanup_'.$cleanup.'_exact'] = ['created', 'created', $cleanup, ObjectObservation::ExpectedContentPresent, ObjectAttribution::CreatedReceipt, $present];
            $cases['cleanup_'.$cleanup.'_different'] = ['created', 'created', $cleanup, ObjectObservation::DifferentContentPresent, ObjectAttribution::CreatedReceipt, $present];
            $cases['cleanup_'.$cleanup.'_unreadable'] = ['created', 'created', $cleanup, ObjectObservation::Unreadable, ObjectAttribution::CreatedReceipt, ObjectClassification::CleanupUnreadable];
        }

        return $cases;
    }

    public function test_contradictory_receipt_is_inconsistent_and_receipt_identity_is_only_boolean(): void
    {
        $row = $this->row('created', 'failed', 'not_required');
        $row->etag = 'secret-etag';
        $row->version_id = 'secret-version';

        $report = (new ObjectEvidenceClassifier)->report($row, ObjectObservation::ExpectedContentPresent);

        $this->assertSame(ObjectAttribution::InconsistentJournal, $report->attribution);
        $this->assertSame(ObjectClassification::Inconsistent, $report->classification);
        $this->assertTrue($report->hasEtag);
        $this->assertTrue($report->hasVersionIdentity);
        $this->assertObjectNotHasProperty('etag', $report);
        $this->assertObjectNotHasProperty('versionId', $report);
    }

    public function test_exact_key_observer_distinguishes_absence_exact_content_and_different_content_without_listing(): void
    {
        $bytes = $this->fixtureBytes(320, 160, 'webp');
        $row = $this->targetRow($bytes);
        $descriptor = ManifestImage::fromObject((object) [
            'key' => $row->object_key,
            'width' => 320,
            'height' => 160,
            'mime_type' => 'image/webp',
            'size' => strlen($bytes),
        ]);

        $this->assertSame(ObjectObservation::AbsentNow, $this->observeWithDisk($row, $descriptor, false));
        $this->assertSame(ObjectObservation::DifferentContentPresent, $this->observeWithDisk($row, $descriptor, true, 'wrong-size'));
        $this->assertSame(ObjectObservation::ExpectedContentPresent, $this->observeWithDisk($row, $descriptor, true, $bytes));

        $sameSizeDifferent = str_repeat('x', strlen($bytes));
        $this->assertSame(ObjectObservation::DifferentContentPresent, $this->observeWithDisk($row, $descriptor, true, $sameSizeDifferent));
    }

    public function test_exact_key_observer_treats_read_failure_and_descriptor_mismatch_as_non_exact(): void
    {
        $bytes = $this->fixtureBytes(320, 160, 'webp');
        $row = $this->targetRow($bytes);
        $descriptor = ManifestImage::fromObject((object) [
            'key' => $row->object_key,
            'width' => 320,
            'height' => 159,
            'mime_type' => 'image/webp',
            'size' => strlen($bytes),
        ]);

        $this->assertSame(ObjectObservation::DifferentContentPresent, $this->observeWithDisk($row, $descriptor, true, $bytes));

        $disk = Mockery::mock(FilesystemAdapter::class);
        $capability = Mockery::mock(StorageObservationCapability::class);
        $capability->shouldReceive('currentDisk')->once()->andReturn($disk);
        $disk->shouldReceive('fileExists')->once()->with($row->object_key)->andThrow(new \RuntimeException('secret endpoint'));
        $this->assertSame(ObjectObservation::Unreadable, (new ExactObjectObserver($capability))->observe($row));
    }

    public function test_typed_exact_observation_returns_actual_facts_and_no_bytes(): void
    {
        $bytes = $this->fixtureBytes(320, 160, 'webp');
        $row = $this->targetRow($bytes);
        $descriptor = ManifestImage::fromObject((object) [
            'key' => $row->object_key,
            'width' => 320,
            'height' => 160,
            'mime_type' => 'image/webp',
            'size' => strlen($bytes),
        ]);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $capability = Mockery::mock(StorageObservationCapability::class);
        $identity = StorageIdentity::current(app('db'));
        $capability->shouldReceive('disk')->once()->with($identity, ReconciliationBackendMode::Local)->andReturn($disk);
        $disk->shouldReceive('fileExists')->once()->with($row->object_key)->andReturnTrue();
        $disk->shouldReceive('size')->once()->with($row->object_key)->andReturn(strlen($bytes));
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $bytes);
        rewind($stream);
        $disk->shouldReceive('readStream')->once()->with($row->object_key)->andReturn($stream);
        $disk->shouldNotReceive('listContents', 'files', 'allFiles', 'directories', 'write', 'put', 'delete', 'copy', 'move');

        $result = (new ExactObjectObserver($capability))->observeExact(
            $row,
            $identity,
            ReconciliationBackendMode::Local,
            $descriptor,
        );

        $this->assertInstanceOf(ExactObjectObservation::class, $result);
        $this->assertSame(ObjectObservation::ExpectedContentPresent, $result->classification);
        $this->assertSame(hash('sha256', $bytes), $result->observedSha256);
        $this->assertSame(strlen($bytes), $result->observedSize);
        $this->assertSame('image/webp', $result->observedMimeType);
        $this->assertTrue($result->descriptorValidated);
        $this->assertTrue($result->structureValidated);
        $this->assertObjectNotHasProperty('bytes', $result);
    }

    public function test_typed_non_success_observations_do_not_fabricate_expected_facts(): void
    {
        $bytes = $this->fixtureBytes(320, 160, 'webp');
        $row = $this->targetRow($bytes);
        $identity = StorageIdentity::current(app('db'));
        foreach ([
            'absent' => [false, null, ObjectObservation::AbsentNow],
            'wrong-size' => [true, 'short', ObjectObservation::DifferentContentPresent],
        ] as [$exists, $actual, $expected]) {
            $disk = Mockery::mock(FilesystemAdapter::class);
            $capability = Mockery::mock(StorageObservationCapability::class);
            $capability->shouldReceive('disk')->once()->andReturn($disk);
            $disk->shouldReceive('fileExists')->once()->with($row->object_key)->andReturn($exists);
            if ($exists) {
                $disk->shouldReceive('size')->once()->with($row->object_key)->andReturn(strlen($actual));
            }
            $result = (new ExactObjectObserver($capability))->observeExact(
                $row,
                $identity,
                ReconciliationBackendMode::Local,
            );
            $this->assertSame($expected, $result->classification);
            $this->assertNull($result->observedSha256);
            $this->assertNull($result->observedSize);
            $this->assertNull($result->observedMimeType);
            $this->assertFalse($result->descriptorValidated);
            $this->assertFalse($result->structureValidated);
        }
    }

    public function test_typed_truncated_stream_is_unreadable_and_bounded(): void
    {
        $bytes = $this->fixtureBytes(320, 160, 'webp');
        $row = $this->targetRow($bytes);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $capability = Mockery::mock(StorageObservationCapability::class);
        $capability->shouldReceive('disk')->once()->andReturn($disk);
        $disk->shouldReceive('fileExists')->once()->andReturnTrue();
        $disk->shouldReceive('size')->once()->andReturn(strlen($bytes));
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($bytes, 0, -1));
        rewind($stream);
        $disk->shouldReceive('readStream')->once()->andReturn($stream);

        $result = (new ExactObjectObserver($capability))->observeExact(
            $row,
            StorageIdentity::current(app('db')),
            ReconciliationBackendMode::Local,
        );

        $this->assertSame(ObjectObservation::Unreadable, $result->classification);
        $this->assertNull($result->observedSha256);
    }

    private function observeWithDisk(
        stdClass $row,
        ManifestImage $descriptor,
        bool $exists,
        ?string $bytes = null,
    ): ObjectObservation {
        config()->set('media.disk', 'media_local');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $capability = Mockery::mock(StorageObservationCapability::class);
        $capability->shouldReceive('currentDisk')->once()->andReturn($disk);
        $disk->shouldReceive('fileExists')->once()->with($row->object_key)->andReturn($exists);
        $disk->shouldNotReceive('listContents', 'files', 'allFiles', 'directories', 'delete', 'write', 'put');
        if ($exists) {
            $disk->shouldReceive('size')->once()->with($row->object_key)->andReturn(strlen((string) $bytes));
            if (strlen((string) $bytes) === (int) $row->expected_size) {
                $stream = fopen('php://memory', 'r+');
                fwrite($stream, (string) $bytes);
                rewind($stream);
                $disk->shouldReceive('readStream')->once()->with($row->object_key)->andReturn($stream);
            } else {
                $disk->shouldNotReceive('readStream');
            }
        } else {
            $disk->shouldNotReceive('size', 'readStream');
        }

        return (new ExactObjectObserver($capability))->observe($row, $descriptor);
    }

    private function row(string $write, ?string $create, string $cleanup): stdClass
    {
        $intent = $write !== 'planned';
        $created = $write === 'created';
        $cleanupAttempted = $cleanup !== 'not_required';

        return (object) [
            'id' => 1,
            'item_id' => 2,
            'object_key' => 'variants/v1/news/550e8400-e29b-41d4-a716-446655440000/w320.webp',
            'kind' => 'variant',
            'expected_sha256' => hash('sha256', 'x'),
            'expected_size' => 1,
            'mime_type' => 'image/webp',
            'write_state' => $write,
            'create_state' => $create,
            'cleanup_state' => $cleanup,
            'etag' => null,
            'version_id' => null,
            'write_intent_at' => $intent ? '2026-01-01 00:00:00' : null,
            'write_confirmed_at' => $created ? '2026-01-01 00:00:01' : null,
            'cleanup_attempted_at' => $cleanupAttempted ? '2026-01-01 00:00:02' : null,
            'cleanup_finished_at' => $cleanup === 'deleted' ? '2026-01-01 00:00:03' : null,
        ];
    }

    private function targetRow(string $bytes): stdClass
    {
        $row = $this->row('intent', null, 'not_required');
        $row->expected_sha256 = hash('sha256', $bytes);
        $row->expected_size = strlen($bytes);

        return $row;
    }
}
