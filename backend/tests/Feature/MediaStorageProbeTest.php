<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MediaStorageProbeTest extends TestCase
{
    public function test_probe_verifies_media_local_and_removes_its_temporary_object(): void
    {
        $disk = Storage::persistentFake('media_local');
        $disk->deleteDirectory('probes');
        config()->set('media.disk', 'media_local');

        $this->artisan('media:probe')
            ->expectsOutputToContain("Disco multimedia 'media_local' comprobado")
            ->assertSuccessful();

        $this->assertSame([], $disk->allFiles('probes'));
    }

    public function test_probe_fails_safely_without_leaking_storage_details_or_leaving_a_file(): void
    {
        config()->set('media.disk', 'media_local');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')
            ->once()
            ->andThrow(new RuntimeException('MEDIA_SECRET_ACCESS_KEY=do-not-leak'));
        $disk->shouldReceive('exists')->once()->andReturnFalse();
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->once()->with('media_local')->andReturn($disk);
        $this->app->instance(FilesystemManager::class, $manager);

        $this->artisan('media:probe')
            ->expectsOutput('La comprobación del almacenamiento multimedia ha fallado.')
            ->doesntExpectOutputToContain('do-not-leak')
            ->assertFailed();
    }

    public function test_temporary_url_capability_is_an_explicit_optional_probe_and_still_cleans_up(): void
    {
        $disk = Storage::persistentFake('media_local');
        $disk->deleteDirectory('probes');
        config()->set('media.disk', 'media_local');

        $this->artisan('media:probe', ['--temporary-url' => true])
            ->expectsOutput('La comprobación del almacenamiento multimedia ha fallado.')
            ->assertFailed();

        $this->assertSame([], $disk->allFiles('probes'));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(storage_path('framework/testing/disks'));

        parent::tearDown();
    }
}
