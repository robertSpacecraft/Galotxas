<?php

namespace Tests\Unit\Media;

use App\Services\Media\Backfill\Safety\ApplyMaintenanceGuard;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\ExclusiveObjectCreator;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\SafetyError;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use App\Services\Media\Backfill\Safety\TargetObject;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;

class BackfillSafetyPrimitivesTest extends TestCase
{
    use BackfillSafetyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSafetyStorage();
    }

    protected function tearDown(): void
    {
        $this->cleanupSafetyStorage();
        parent::tearDown();
    }

    #[DataProvider('maintenanceCases')]
    public function test_maintenance_checks_actual_application_without_toggling(bool $testing, bool $down, bool $allowed): void
    {
        $application = Mockery::mock(Application::class);
        $application->shouldReceive('environment')->once()->with('testing')->andReturn($testing);
        if (! $testing) {
            $application->shouldReceive('isDownForMaintenance')->once()->andReturn($down);
        }
        $guard = new ApplyMaintenanceGuard($application);
        if ($allowed) {
            $guard->assertAllowed();
            $this->assertTrue(true);
        } else {
            $this->assertSafetyError(SafetyError::MaintenanceRequired, fn () => $guard->assertAllowed());
        }
    }

    public static function maintenanceCases(): array
    {
        return [[true, false, true], [false, false, false], [false, true, true]];
    }

    public function test_identity_is_stable_changes_with_environment_and_storage_and_excludes_secrets(): void
    {
        $db = ['driver' => 'mariadb', 'host' => 'DB.EXAMPLE', 'port' => 3306, 'database' => 'media'];
        $storage = ['driver' => 's3', 'bucket' => 'private-media', 'region' => 'region', 'endpoint' => 'https://s3.example/'];
        $hash = StorageIdentity::fromConfiguration($db, 'media_s3', $storage, 'testing')->hash;
        $db['password'] = 'secret';
        $db['username'] = 'another-user';
        $db['host'] = 'db.example';
        $db['port'] = '3306';
        $storage['key'] = 'access-key';
        $storage['secret'] = 'secret';
        $storage['token'] = 'bearer';
        $storage['endpoint'] = 'https://user:password@s3.example/?token=secret#secret';
        $this->assertSame($hash, StorageIdentity::fromConfiguration($db, 'media_s3', $storage, 'testing')->hash);
        foreach (['host', 'port', 'database'] as $field) {
            $changed = $db;
            $changed[$field] = 'different';
            $this->assertNotSame($hash, StorageIdentity::fromConfiguration($changed, 'media_s3', $storage, 'testing')->hash);
        }
        foreach (['bucket', 'region', 'endpoint', 'use_path_style_endpoint'] as $field) {
            $changed = $storage;
            $changed[$field] = $field === 'endpoint' ? 'https://elsewhere.example' : ($field === 'use_path_style_endpoint' ? true : 'changed');
            $this->assertNotSame($hash, StorageIdentity::fromConfiguration($db, 'media_s3', $changed, 'testing')->hash);
        }
        $this->assertNotSame($hash, StorageIdentity::fromConfiguration($db, 'media_s3', $storage, 'production')->hash);
        $this->assertLessThan(64, strlen($this->identity()->lockName()));
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $this->identity()->hash);
        $this->assertSame($this->identity()->hash, $this->identity()->hash);
    }

    public function test_database_url_credentials_do_not_enter_identity(): void
    {
        $db = ['driver' => 'mariadb', 'url' => 'mariadb://user:secret@db.example:3306/journal'];
        $storage = ['driver' => 'local', 'root' => $this->safetyRoot];
        $a = StorageIdentity::fromConfiguration($db, 'media_local', $storage, 'testing');
        $db['url'] = 'mariadb://other:different@db.example:3306/journal';
        $this->assertSame($a->hash, StorageIdentity::fromConfiguration($db, 'media_local', $storage, 'testing')->hash);
    }

    public function test_local_exclusive_create_preserves_original_and_private_permissions(): void
    {
        [$target, $bytes] = $this->variant();
        $writer = app(ExclusiveObjectCreator::class);
        $this->assertSame(CreateState::Created, $writer->create($target, $bytes)->state);
        $otherBytes = $this->fixtureBytes(320, 150, 'webp');
        $other = new TargetObject($target->key, $target->kind, hash('sha256', $otherBytes), strlen($otherBytes), $target->mimeType);
        $this->assertSame(CreateState::Rejected, $writer->create($other, $otherBytes)->state);
        $path = $this->safetyRoot.'/'.$target->key;
        $this->assertSame($bytes, file_get_contents($path));
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertFileDoesNotExist($this->safetyRoot.'/news/'.self::SAFETY_UUID.'.jpg');
    }

    public function test_partial_local_failure_leaves_exact_owned_path_as_unknown_without_deleting(): void
    {
        [$target, $bytes] = $this->variant();
        $writer = new class(app(FilesystemManager::class)) extends ExclusiveObjectCreator
        {
            protected function writeChunk($stream, string $bytes): int|false
            {
                fwrite($stream, substr($bytes, 0, 7));

                return false;
            }
        };
        $this->assertSame(CreateState::Unknown, $writer->create($target, $bytes)->state);
        $this->assertSame(substr($bytes, 0, 7), file_get_contents($this->safetyRoot.'/'.$target->key));
        $this->assertSame(CreateState::Rejected, app(ExclusiveObjectCreator::class)->create($target, $bytes)->state);
    }

    public function test_local_symlink_parent_is_rejected_and_leaf_symlink_is_collision(): void
    {
        [$target, $bytes] = $this->variant();
        mkdir($this->safetyRoot.'/outside');
        symlink($this->safetyRoot.'/outside', $this->safetyRoot.'/variants');
        $this->assertSame(CreateState::Failed, app(ExclusiveObjectCreator::class)->create($target, $bytes)->state);
        $this->assertSame([], Storage::disk('media_local')->allFiles('outside'));
        unlink($this->safetyRoot.'/variants');
        mkdir($this->safetyRoot.'/'.dirname($target->key), 0700, true);
        file_put_contents($this->safetyRoot.'/original', 'original');
        symlink($this->safetyRoot.'/original', $this->safetyRoot.'/'.$target->key);
        $this->assertSame(CreateState::Rejected, app(ExclusiveObjectCreator::class)->create($target, $bytes)->state);
        $this->assertSame('original', file_get_contents($this->safetyRoot.'/original'));
    }

    #[DataProvider('invalidTargets')]
    public function test_target_allowlist_rejects_master_arbitrary_paths_and_invalid_widths(string $key, ObjectKind $kind): void
    {
        $this->assertSafetyError(SafetyError::InvalidInput,
            fn () => new TargetObject($key, $kind, str_repeat('a', 64), 100, 'image/webp'));
        $this->assertSame([], scandir($this->safetyRoot) === ['.', '..'] ? [] : ['unexpected']);
    }

    public static function invalidTargets(): array
    {
        $prefix = 'variants/v1/news/550e8400-e29b-41d4-a716-446655440000/';

        return [
            ['news/550e8400-e29b-41d4-a716-446655440000.jpg', ObjectKind::Variant],
            ['../escape', ObjectKind::Variant], [$prefix.'w321.webp', ObjectKind::Variant],
            [$prefix.'w0320.webp', ObjectKind::Variant], [$prefix.'w320.jpg', ObjectKind::Variant],
            [$prefix.'w320.webp/extra', ObjectKind::Variant], [$prefix.'manifest.json', ObjectKind::Variant],
            [strtoupper($prefix).'w320.webp', ObjectKind::Variant],
        ];
    }

    public function test_hash_size_mime_and_real_bytes_fail_before_any_storage_call(): void
    {
        [$target, $bytes] = $this->variant();
        $filesystems = Mockery::mock(FilesystemManager::class);
        $filesystems->shouldNotReceive('disk');
        $writer = new ExclusiveObjectCreator($filesystems);
        $this->assertSafetyError(SafetyError::InvalidInput, fn () => $writer->create($target, $bytes.'x'));
        $wrongHash = new TargetObject($target->key, $target->kind, str_repeat('a', 64), strlen($bytes), $target->mimeType);
        $this->assertSafetyError(SafetyError::InvalidInput, fn () => $writer->create($wrongHash, $bytes));
        $this->assertSafetyError(SafetyError::InvalidInput, fn () => new TargetObject($target->key, $target->kind, $target->sha256, $target->size, 'image/png'));
        $fake = new TargetObject($target->key, $target->kind, hash('sha256', 'fake'), 4, $target->mimeType);
        $this->assertSafetyError(SafetyError::InvalidInput, fn () => $writer->create($fake, 'fake'));
    }

    #[DataProvider('s3Outcomes')]
    public function test_real_sdk_sends_conditional_http_request_once_with_exact_mapping(int $status, ?string $code, CreateState $expected): void
    {
        [$target, $bytes] = $this->variant();
        $requests = [];
        $config = [
            'driver' => 's3', 'version' => 'latest', 'region' => 'us-east-1', 'bucket' => 'private-test-bucket',
            'endpoint' => 'https://s3.invalid', 'use_path_style_endpoint' => true, 'visibility' => 'private',
            'key' => 'test-only', 'secret' => 'test-only', 'retries' => 3,
            'http_handler' => function ($request) use (&$requests, $status, $code) {
                $requests[] = $request;
                if ($status === 0) {
                    return Create::rejectionFor(new ConnectException('private URL must not escape', $request));
                }

                $response = new Response($status, ['ETag' => '"opaque-etag"', 'x-amz-version-id' => 'version-1'],
                    $code ? '<Error><Code>'.$code.'</Code></Error>' : '');

                return $status >= 300
                    ? Create::rejectionFor(['exception' => new \RuntimeException('simulated HTTP error'), 'response' => $response])
                    : Create::promiseFor($response);
            },
        ];
        config()->set('media.disk', 'media_s3');
        config()->set('filesystems.disks.media_s3', $config);
        Storage::set('media_s3', Storage::build($config));
        $receipt = app(ExclusiveObjectCreator::class)->create($target, $bytes);
        $this->assertSame($expected, $receipt->state);
        $this->assertCount(1, $requests);
        $request = $requests[0];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('*', $request->getHeaderLine('If-None-Match'));
        $this->assertSame('/private-test-bucket/'.$target->key, $request->getUri()->getPath());
        $this->assertSame('s3.invalid', $request->getUri()->getHost());
        $this->assertSame($bytes, (string) $request->getBody());
        $this->assertSame($target->mimeType, $request->getHeaderLine('Content-Type'));
        $this->assertFalse($request->hasHeader('x-amz-acl'));
        $this->assertSame($expected === CreateState::Created ? '"opaque-etag"' : null, $receipt->etag);
        $this->assertSame($expected === CreateState::Created ? 'version-1' : null, $receipt->versionId);
    }

    public static function s3Outcomes(): array
    {
        return [[200, null, CreateState::Created], [412, 'PreconditionFailed', CreateState::Rejected],
            [409, 'ConditionalRequestConflict', CreateState::Unknown], [0, null, CreateState::Unknown],
            [501, 'NotImplemented', CreateState::Unknown]];
    }

    public function test_manifest_exclusive_creation_validates_json_and_uses_same_exact_namespace(): void
    {
        $preflight = $this->preflight();
        $bytes = $preflight->prepared->manifest->toJson();
        $key = 'variants/v1/news/'.self::SAFETY_UUID.'/manifest.json';
        $target = new TargetObject($key, ObjectKind::Manifest, hash('sha256', $bytes), strlen($bytes), 'application/json');
        $writer = app(ExclusiveObjectCreator::class);
        $this->assertSame(CreateState::Created, $writer->create($target, $bytes)->state);
        $this->assertSame(CreateState::Rejected, $writer->create($target, $bytes)->state);
        $this->assertSame($bytes, file_get_contents($this->safetyRoot.'/'.$key));
        $invalid = new TargetObject($key, ObjectKind::Manifest, hash('sha256', '{}'), 2, 'application/json');
        $this->assertSafetyError(SafetyError::InvalidInput, fn () => $writer->create($invalid, '{}'));
    }

    public function test_stale_cached_storage_configuration_cannot_redirect_writes(): void
    {
        [$target, $bytes] = $this->variant();
        Storage::disk('media_local');
        mkdir($this->safetyRoot.'/other');
        config()->set('filesystems.disks.media_local.root', $this->safetyRoot.'/other');
        $this->assertSame(CreateState::Failed, app(ExclusiveObjectCreator::class)->create($target, $bytes)->state);
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }

    public function test_s3_prefix_is_rejected_without_dispatching_any_sdk_request(): void
    {
        [$target, $bytes] = $this->variant();
        $requests = [];
        $config = ['driver' => 's3', 'version' => 'latest', 'region' => 'us-east-1', 'bucket' => 'private-test-bucket',
            'endpoint' => 'https://s3.invalid', 'visibility' => 'private', 'key' => 'test-only', 'secret' => 'test-only',
            'root' => 'unexpected-prefix', 'http_handler' => function ($request) use (&$requests) {
                $requests[] = $request;

                return Create::promiseFor(new Response(200));
            }];
        config()->set('media.disk', 'media_s3');
        config()->set('filesystems.disks.media_s3', $config);
        Storage::set('media_s3', Storage::build($config));
        $this->assertSame(CreateState::Failed, app(ExclusiveObjectCreator::class)->create($target, $bytes)->state);
        $this->assertSame([], $requests);
    }

    public function test_unsupported_disk_and_s3_adapter_fail_without_fallback(): void
    {
        [$target, $bytes] = $this->variant();
        config()->set('media.disk', 'unsupported');
        $this->assertSame(CreateState::Failed, app(ExclusiveObjectCreator::class)->create($target, $bytes)->state);
        config()->set('media.disk', 'media_s3');
        Storage::set('media_s3', Storage::disk('media_local'));
        $this->assertSame(CreateState::Failed, app(ExclusiveObjectCreator::class)->create($target, $bytes)->state);
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }
}
