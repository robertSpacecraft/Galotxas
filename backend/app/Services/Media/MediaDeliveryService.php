<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\MediaObjectNotFound;
use App\Services\Media\Exceptions\MediaStorageException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaDeliveryService
{
    public function __construct(
        private readonly MediaStorageService $storage,
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
}
