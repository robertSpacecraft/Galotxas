<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProfilePhotoRequest;
use App\Http\Resources\ProfilePhotoResource;
use App\Services\Media\Exceptions\InvalidMediaImage;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\ProfilePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfilePhotoController extends Controller
{
    use ApiResponse;

    public function store(
        StoreProfilePhotoRequest $request,
        ProfilePhotoService $service
    ): JsonResponse {
        try {
            $user = $service->store($request->user(), $request->file('photo'));
        } catch (InvalidMediaImage $exception) {
            throw ValidationException::withMessages([
                'photo' => $exception->getMessage(),
            ]);
        } catch (MediaStorageException) {
            return $this->errorResponse(
                'La foto de perfil no está disponible temporalmente.',
                status: 503
            );
        }

        return $this->successResponse([
            'profile_photo' => ProfilePhotoResource::forUser($user),
        ], 'Foto de perfil actualizada correctamente.');
    }

    public function destroy(Request $request, ProfilePhotoService $service): JsonResponse
    {
        $service->remove($request->user());

        return $this->successResponse([
            'profile_photo' => null,
        ], 'Foto de perfil eliminada correctamente.');
    }
}
