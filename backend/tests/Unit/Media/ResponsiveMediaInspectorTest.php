<?php

namespace Tests\Unit\Media;

use App\Services\Media\Backfill\InspectionReason;
use App\Services\Media\Backfill\ManifestInspectionState;
use App\Services\Media\Backfill\ObjectInspectionState;
use App\Services\Media\ExistingMasterPreparer;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\ResponsiveImageProfile;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\InspectsMemoryMedia;
use Tests\TestCase;

class ResponsiveMediaInspectorTest extends TestCase
{
    use InspectsMemoryMedia;
    use ResponsiveImageFixtures;

    private const MASTER = 'banners/550e8400-e29b-41d4-a716-446655440000.png';

    private const MANIFEST = 'variants/v1/banners/550e8400-e29b-41d4-a716-446655440000/manifest.json';

    public function test_missing_and_valid_manifest_without_cache_or_writes(): void
    {
        $this->assertSame(ManifestInspectionState::Missing, $this->memoryInspector([])->inspectManifest(self::MASTER,
            ResponsiveImageProfile::Banner, ImagePreparationPolicy::Photo)->state);
        $set = app(ExistingMasterPreparer::class)->prepare(self::MASTER, $this->fixtureBytes(400, 200), ResponsiveImageProfile::Banner, ImagePreparationPolicy::Photo);
        $inspector = $this->memoryInspector([self::MANIFEST => $set->manifest->toJson()]);
        $this->assertSame(ManifestInspectionState::Valid, $inspector->inspectManifest(self::MASTER,
            ResponsiveImageProfile::Banner, ImagePreparationPolicy::Photo)->state);
        $this->assertSame(ObjectInspectionState::Missing, $inspector->inspectMaster(self::MASTER, ResponsiveImageProfile::Banner)->state);
    }

    #[DataProvider('failures')]
    public function test_manifest_failures_are_distinct_and_stream_is_closed(string $case, ManifestInspectionState $state, InspectionReason $reason): void
    {
        $stream = null;
        $objects = [self::MANIFEST => $case === 'large' ? str_repeat('x', 16385) : ($case === 'schema' ? '{}' : '{')];
        $inspector = $this->memoryInspector($objects, function ($disk) use ($case, &$stream) {
            if ($case === 'transport') {
                $disk->shouldReceive('fileExists')->with(self::MANIFEST)->andThrow(new RuntimeException('SECRET URL'));
            } elseif ($case === 'denied') {
                $disk->shouldReceive('fileExists')->with(self::MANIFEST)->andThrow(new RequestException('SECRET', new Request('HEAD', 'https://example.invalid'), new Response(403)));
            } elseif ($case === 'timeout') {
                $disk->shouldReceive('readStream')->with(self::MANIFEST)->andThrow(new ConnectException('SECRET', new Request('GET', 'https://example.invalid'), null, ['errno' => 28]));
            } elseif ($case === 'truncated') {
                $stream = $this->memoryStream('{}');
                $disk->shouldReceive('size')->with(self::MANIFEST)->andReturn(100);
                $disk->shouldReceive('readStream')->with(self::MANIFEST)->andReturn($stream);
            } elseif ($case === 'false_stream') {
                $disk->shouldReceive('readStream')->with(self::MANIFEST)->andReturn(false);
            }
        });
        $result = $inspector->inspectManifest(self::MASTER, ResponsiveImageProfile::Banner, ImagePreparationPolicy::Photo);
        $this->assertSame($state, $result->state);
        $this->assertSame($reason, $result->reason);
        if ($stream !== null) {
            $this->assertFalse(is_resource($stream));
        }
    }

    public static function failures(): iterable
    {
        yield ['json', ManifestInspectionState::Invalid, InspectionReason::InvalidJson];
        yield ['schema', ManifestInspectionState::Invalid, InspectionReason::SchemaViolation];
        yield ['large', ManifestInspectionState::Invalid, InspectionReason::SchemaViolation];
        yield ['transport', ManifestInspectionState::InspectionFailed, InspectionReason::TransportError];
        yield ['denied', ManifestInspectionState::InspectionFailed, InspectionReason::AccessDenied];
        yield ['timeout', ManifestInspectionState::InspectionFailed, InspectionReason::Timeout];
        yield ['truncated', ManifestInspectionState::InspectionFailed, InspectionReason::TruncatedRead];
        yield ['false_stream', ManifestInspectionState::InspectionFailed, InspectionReason::TransportError];
    }

    public function test_targets_are_exact_bounded_and_include_alternate_formats_and_large_widths(): void
    {
        $prefix = dirname(self::MANIFEST);
        $results = $this->memoryInspector([$prefix.'/w1280.png' => 'residue', $prefix.'/w640.png' => 'alternate'])
            ->inspectTargets(self::MASTER, ResponsiveImageProfile::Banner);
        $this->assertCount(8, $results);
        $this->assertSame(ObjectInspectionState::Present, $results[$prefix.'/w1280.png']->state);
        $this->assertSame(ObjectInspectionState::Present, $results[$prefix.'/w640.png']->state);
        $this->assertSame(ObjectInspectionState::Missing, $results[$prefix.'/w640.webp']->state);
    }

    public function test_master_stream_is_bounded_at_limit_plus_one_and_closed(): void
    {
        config()->set('media.stored_master_max_bytes', 100);
        $stream = $this->memoryStream(str_repeat('x', 1000));
        $inspector = $this->memoryInspector([self::MASTER => str_repeat('x', 1000)],
            fn ($disk) => $disk->shouldReceive('readStream')->with(self::MASTER)->once()->andReturn($stream));
        $result = $inspector->inspectMaster(self::MASTER, ResponsiveImageProfile::Banner);
        $this->assertSame(ObjectInspectionState::Present, $result->state);
        $this->assertSame(101, strlen($result->bytes));
        $this->assertFalse(is_resource($stream));
    }

    public function test_context_policy_mismatch_is_not_a_transport_failure(): void
    {
        $set = app(ExistingMasterPreparer::class)->prepare(self::MASTER, $this->fixtureBytes(400, 200), ResponsiveImageProfile::Banner, ImagePreparationPolicy::Photo);
        $result = $this->memoryInspector([self::MANIFEST => $set->manifest->toJson()])->inspectManifest(self::MASTER,
            ResponsiveImageProfile::Banner, ImagePreparationPolicy::Graphic);
        $this->assertSame(ManifestInspectionState::Invalid, $result->state);
        $this->assertSame(InspectionReason::IdentityMismatch, $result->reason);
    }
}
