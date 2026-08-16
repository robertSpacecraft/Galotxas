<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\MediaObjectNotFound;
use App\Services\Media\Exceptions\MediaStorageException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class MediaDeliveryService
{
    public function __construct(
        private readonly MediaStorageService $storage,
    ) {}

    public function deliver(string $key, bool $private = false): Response|RedirectResponse
    {
        if (! $this->storage->exists($key)) {
            throw new MediaObjectNotFound('El recurso multimedia no existe.');
        }

        return match ((string) config('media.disk')) {
            'media_local' => $this->localResponse($key),
            'media_s3' => $this->temporaryRedirect($key, $private),
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
}
