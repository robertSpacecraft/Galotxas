<?php

namespace Tests\Concerns;

use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReference;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\PreflightResult;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\SafetyError;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use App\Services\Media\Backfill\Safety\TargetObject;
use App\Services\Media\ExistingMasterPreparer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Unit\Media\ResponsiveImageFixtures;

trait BackfillSafetyFixtures
{
    use ResponsiveImageFixtures;

    private string $safetyRoot;

    private const SAFETY_UUID = '550e8400-e29b-41d4-a716-446655440000';

    private function setupSafetyStorage(): void
    {
        $this->safetyRoot = sys_get_temp_dir().'/galotxas-backfill-test-'.Str::uuid();
        mkdir($this->safetyRoot, 0700);
        config()->set('media.disk', 'media_local');
        config()->set('filesystems.disks.media_local.root', $this->safetyRoot);
        Storage::forgetDisk('media_local');
    }

    private function cleanupSafetyStorage(): void
    {
        if (isset($this->safetyRoot)) {
            File::deleteDirectory($this->safetyRoot);
        }
    }

    private function identity(): StorageIdentity
    {
        return StorageIdentity::current(app('db'));
    }

    private function variant(): array
    {
        $bytes = $this->fixtureBytes(320, 160, 'webp');

        return [new TargetObject('variants/v1/news/'.self::SAFETY_UUID.'/w320.webp', ObjectKind::Variant,
            hash('sha256', $bytes), strlen($bytes), 'image/webp'), $bytes];
    }

    private function preflight(int $entityId = 123): PreflightResult
    {
        $domain = ManagedMediaDomain::News;
        $key = 'news/'.self::SAFETY_UUID.'.jpg';
        $prepared = app(ExistingMasterPreparer::class)->prepare($key, $this->fixtureBytes(400, 200, 'jpeg'), $domain->profile(), $domain->policy());

        return new PreflightResult(new ManagedMediaReference($domain, $entityId, $key), PreflightClassification::LegacyBackfillable, prepared: $prepared);
    }

    private function plannedObject(): array
    {
        $journal = app(ApplyJournal::class);
        $run = $journal->createApplyRun($this->identity());
        $preflight = $this->preflight();
        $item = $journal->snapshot($run, $preflight);
        $bytes = $preflight->prepared->variants[0]->bytes;
        $descriptor = $preflight->prepared->manifest->variants[0];
        $target = new TargetObject($descriptor->key, ObjectKind::Variant, hash('sha256', $bytes), strlen($bytes), $descriptor->mimeType);
        $object = $journal->planObject($item, $target);
        $journal->markRevalidated($item);

        return [$journal, $run, $item, $object, $target, $bytes];
    }

    private function assertSafetyError(SafetyError $reason, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a typed safety failure.');
        } catch (BackfillSafetyException $error) {
            $this->assertSame($reason, $error->reason);
            $this->assertSame($reason->value, $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }
}
