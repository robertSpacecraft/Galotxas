<?php

namespace Tests\Concerns;

use App\Services\Media\Backfill\ResponsiveMediaInspector;
use App\Services\Media\ResponsiveMediaKeys;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Mockery;

trait InspectsMemoryMedia
{
    /** No filesystem fake: every object exists exclusively in this in-memory map. */
    private function memoryInspector(array $objects, ?callable $configure = null): ResponsiveMediaInspector
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldNotReceive('exists', 'allFiles', 'files', 'directories', 'put', 'writeStream', 'delete', 'setVisibility');
        $disk->shouldReceive('fileExists')->byDefault()->andReturnUsing(fn ($key) => array_key_exists($key, $objects));
        $disk->shouldReceive('size')->byDefault()->andReturnUsing(fn ($key) => strlen($objects[$key]));
        $disk->shouldReceive('readStream')->byDefault()->andReturnUsing(fn ($key) => $this->memoryStream($objects[$key]));
        if ($configure !== null) {
            $configure($disk);
        }
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->with(config('media.disk'))->andReturn($disk);

        return new ResponsiveMediaInspector($manager, app(ResponsiveMediaKeys::class));
    }

    private function memoryStream(string $bytes)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $bytes);
        rewind($stream);

        return $stream;
    }
}
