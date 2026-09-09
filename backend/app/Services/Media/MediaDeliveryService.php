<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\MediaObjectNotFound;
use App\Services\Media\Exceptions\MediaStorageException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MediaDeliveryService
{
    public function __construct(
        private readonly MediaStorageService $storage,
        private readonly FilesystemManager $filesystems,
    ) {}

    public function deliver(
        string $key,
        bool $privateTemporaryUrl = false
    ): Response|RedirectResponse {
        if (! $this->storage->exists($key)) {
            throw new MediaObjectNotFound('El recurso multimedia no existe.');
        }

        return match ((string) config('media.disk')) {
            'media_local' => $this->localResponse($key),
            'media_s3' => $this->temporaryRedirect($key, $privateTemporaryUrl),
            default => throw new MediaStorageException(
                'No se pudo entregar el recurso multimedia.'
            ),
        };
    }

    public function deliverPrivate(string $key): Response|StreamedResponse
    {
        if (! $this->storage->exists($key)) {
            throw new MediaObjectNotFound('El recurso multimedia no existe.');
        }

        return match ((string) config('media.disk')) {
            'media_local' => $this->localResponse($key),
            'media_s3' => $this->privateStreamResponse($key),
            default => throw new MediaStorageException(
                'No se pudo entregar el recurso multimedia.'
            ),
        };
    }

    public function deliverPublic(string $key): Response|RedirectResponse
    {
        if (! $this->storage->exists($key)) {
            throw new MediaObjectNotFound('El recurso multimedia no existe.');
        }

        return match ((string) config('media.disk')) {
            'media_local' => $this->publicLocalResponse($key),
            'media_s3' => $this->publicTemporaryRedirect($key),
            default => throw new MediaStorageException(
                'No se pudo entregar el recurso multimedia.'
            ),
        };
    }

    public function deliverPublicVariant(ManifestImage $image): Response|RedirectResponse
    {
        $disk = $this->variantDisk($image);

        return match ((string) config('media.disk')) {
            'media_local' => response('', 200, [
                'Content-Type' => $image->mimeType,
                'Cache-Control' => 'public, max-age=60',
                'X-Accel-Redirect' => '/_private-media/'.$image->key,
                'X-Content-Type-Options' => 'nosniff',
            ]),
            'media_s3' => $this->variantTemporaryRedirect($disk, $image),
            default => throw new MediaStorageException('No se pudo entregar el recurso multimedia.'),
        };
    }

    public function deliverVariant(ManifestImage $image): Response|RedirectResponse
    {
        $disk = $this->variantDisk($image);

        return match ((string) config('media.disk')) {
            'media_local' => $this->restrictedLocalVariantResponse($image),
            'media_s3' => $this->restrictedVariantTemporaryRedirect($disk, $image),
            default => throw new MediaStorageException('No se pudo entregar el recurso multimedia.'),
        };
    }

    public function deliverPrivateVariant(ManifestImage $image): Response|StreamedResponse
    {
        $disk = $this->variantDisk($image);

        if ((string) config('media.disk') === 'media_local') {
            return $this->restrictedLocalVariantResponse($image);
        }
        if ((string) config('media.disk') !== 'media_s3') {
            throw new MediaStorageException('No se pudo entregar el recurso multimedia.');
        }

        try {
            $stream = $disk->readStream($image->key);
            if (! is_resource($stream)) {
                throw new MediaStorageException('No se pudo entregar el recurso multimedia.');
            }
        } catch (MediaStorageException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MediaStorageException('No se pudo entregar el recurso multimedia.');
        }

        return response()->stream(
            static function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            200,
            [
                'Content-Type' => $image->mimeType,
                'Content-Length' => (string) $image->size,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]
        );
    }

    private function localResponse(string $key): Response
    {
        $metadata = $this->storage->metadata($key);

        return response('', 200, [
            'Content-Type' => $metadata['mime_type'],
            'Cache-Control' => 'private, no-store',
            'X-Accel-Redirect' => '/_private-media/'.$key,
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function temporaryRedirect(string $key, bool $private): RedirectResponse
    {
        return redirect()
            ->away($this->storage->temporaryUrl($key, $private))
            ->withHeaders([
                'Cache-Control' => 'private, no-store',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
    }

    private function privateStreamResponse(string $key): StreamedResponse
    {
        $metadata = $this->storage->metadata($key);
        $stream = $this->storage->readStream($key);

        return response()->stream(
            static function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            200,
            [
                'Content-Type' => $metadata['mime_type'],
                'Content-Length' => (string) $metadata['size'],
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]
        );
    }

    private function publicLocalResponse(string $key): Response
    {
        $metadata = $this->storage->metadata($key);

        return response('', 200, [
            'Content-Type' => $metadata['mime_type'],
            'Cache-Control' => 'public, max-age=60',
            'X-Accel-Redirect' => '/_private-media/'.$key,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function publicTemporaryRedirect(string $key): RedirectResponse
    {
        return redirect()
            ->away($this->storage->temporaryUrl($key, false))
            ->withHeaders([
                'Cache-Control' => 'public, max-age=60',
            ]);
    }

    private function restrictedLocalVariantResponse(ManifestImage $image): Response
    {
        return response('', 200, [
            'Content-Type' => $image->mimeType,
            'Cache-Control' => 'private, no-store',
            'X-Accel-Redirect' => '/_private-media/'.$image->key,
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function variantDisk(ManifestImage $image): FilesystemAdapter
    {
        $name = (string) config('media.disk');
        if (! in_array($name, ['media_local', 'media_s3'], true)) {
            throw new MediaStorageException('No se pudo entregar el recurso multimedia.');
        }

        try {
            $disk = $this->filesystems->disk($name);
            if (! $disk->exists($image->key)) {
                throw new MediaObjectNotFound('El recurso multimedia no existe.');
            }

            return $disk;
        } catch (MediaObjectNotFound $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MediaStorageException('No se pudo entregar el recurso multimedia.');
        }
    }

    private function variantTemporaryRedirect(FilesystemAdapter $disk, ManifestImage $image): RedirectResponse
    {
        try {
            $url = $disk->temporaryUrl(
                $image->key,
                now()->addSeconds(max(1, (int) config('media.temporary_url_ttl_seconds'))),
            );
        } catch (Throwable) {
            throw new MediaStorageException('No se pudo entregar el recurso multimedia.');
        }

        return redirect()->away($url)->withHeaders([
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    private function restrictedVariantTemporaryRedirect(
        FilesystemAdapter $disk,
        ManifestImage $image
    ): RedirectResponse {
        try {
            $url = $disk->temporaryUrl(
                $image->key,
                now()->addSeconds(max(1, (int) config('media.temporary_url_ttl_seconds'))),
            );
        } catch (Throwable) {
            throw new MediaStorageException('No se pudo entregar el recurso multimedia.');
        }

        return redirect()->away($url)->withHeaders([
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
