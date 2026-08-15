<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Throwable;

class ProbeMediaStorage extends Command
{
    protected $signature = 'media:probe {--temporary-url : Comprueba también la capacidad de URL temporal}';

    protected $description = 'Comprueba de forma segura el disco multimedia configurado y limpia el objeto de prueba';

    public function handle(FilesystemManager $filesystems): int
    {
        $diskName = trim((string) config('media.disk'));
        $key = 'probes/'.Str::uuid().'.txt';
        $disk = null;
        $failed = false;

        try {
            if ($diskName === '') {
                throw new \RuntimeException('Media disk is not configured.');
            }

            $disk = $filesystems->disk($diskName);
            $bytes = "galotxas-media-probe\n";

            if (! $disk->put($key, $bytes, ['visibility' => 'private'])) {
                throw new \RuntimeException('Media probe could not be written.');
            }

            if (! $disk->exists($key) || $disk->size($key) !== strlen($bytes)) {
                throw new \RuntimeException('Media probe could not be verified.');
            }

            if ((bool) $this->option('temporary-url')) {
                $this->assertTemporaryUrlCapability($disk, $key);
            }
        } catch (Throwable) {
            $failed = true;
        } finally {
            if ($disk instanceof FilesystemAdapter) {
                try {
                    if ($disk->exists($key) && ! $disk->delete($key)) {
                        $failed = true;
                    }
                } catch (Throwable) {
                    $failed = true;
                }
            }
        }

        if ($failed) {
            $this->error('La comprobación del almacenamiento multimedia ha fallado.');

            return self::FAILURE;
        }

        $this->info("Disco multimedia '{$diskName}' comprobado y limpiado correctamente.");

        return self::SUCCESS;
    }

    private function assertTemporaryUrlCapability(FilesystemAdapter $disk, string $key): void
    {
        if (! $disk->providesTemporaryUrls()) {
            throw new \RuntimeException('Temporary URLs are not supported by this disk.');
        }

        $url = $disk->temporaryUrl(
            $key,
            now()->addSeconds(max(1, (int) config('media.temporary_url_ttl_seconds'))),
        );

        if ($url === '') {
            throw new \RuntimeException('Temporary URL could not be generated.');
        }
    }
}
