<?php

namespace Tests\Concerns;

use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveManifest;
use App\Services\Media\ResponsiveMediaKeys;
use App\Services\Media\ResponsiveMediaStorage;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;

trait InteractsWithResponsiveMedia
{
    private function assertResponsiveSet(string $master, ?ResponsiveImageProfile $profile = null): ResponsiveManifest
    {
        $manifest = app(ResponsiveMediaStorage::class)->readManifest($master);
        $this->assertNotNull($manifest);
        if ($profile !== null) {
            $this->assertSame($profile, $manifest->profile);
        }
        $disk = Storage::disk('media_local');
        foreach ([$manifest->master, ...$manifest->variants] as $image) {
            $disk->assertExists($image->key);
            $header = getimagesizefromstring($disk->get($image->key));
            $this->assertSame([$image->width, $image->height, $image->mimeType], [$header[0], $header[1], $header['mime']]);
            $this->assertSame('private', $disk->visibility($image->key));
        }

        return $manifest;
    }

    private function assertOnlyResponsiveSet(string $master): void
    {
        $manifest = $this->assertResponsiveSet($master);
        $keys = [$master, ...array_column($manifest->variants, 'key')];
        $keys[] = app(ResponsiveMediaKeys::class)->manifest($master, $manifest->version);
        $this->assertEqualsCanonicalizing($keys, Storage::disk('media_local')->allFiles());
    }

    /** Inject only adapter deletion failures; preparation, writes and DB boundaries stay real. */
    private function failMediaDeletion(array|callable $keys, int $failures = 1, ?callable $onFailure = null): void
    {
        $real = Storage::disk('media_local');
        $disk = Mockery::mock($real)->makePartial();
        $disk->shouldReceive('delete')->andReturnUsing(function ($key) use ($keys, $real, $onFailure) {
            if (is_array($keys) ? in_array($key, $keys, true) : $keys($key)) {
                $onFailure?->__invoke($key);
                throw new RuntimeException('secret bucket/key adapter failure');
            }

            return $real->delete($key);
        });
        Storage::set('media_local', $disk);
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->times($failures)
            ->with('Responsive media operation failed.', ['operation' => 'delete_set']);
        $this->app->instance(LoggerInterface::class, $logger);
    }
}
