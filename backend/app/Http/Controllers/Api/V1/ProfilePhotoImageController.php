<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Media\Exceptions\MediaObjectNotFound;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaDeliveryService;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaPurpose;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoImageController extends Controller
{
    public function __invoke(
        Request $request,
        MediaObjectKeyGenerator $keys,
        MediaDeliveryService $delivery
    ): Response|StreamedResponse {
        $key = $request->user()->profile_photo_path;

        abort_unless(
            is_string($key) && $keys->isValidForPurpose($key, MediaPurpose::Avatar),
            404
        );

        try {
            $response = $delivery->deliverPrivate($key);
        } catch (MediaObjectNotFound) {
            abort(404);
        } catch (MediaStorageException) {
            abort(503, 'La foto de perfil no está disponible temporalmente.');
        }

        $response->headers->set('Vary', 'Authorization');

        return $response;
    }
}
