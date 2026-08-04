<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreContactRequest;
use App\Http\Resources\PublicContactRequestResource;
use App\Services\ContactRequestService;
use Illuminate\Http\JsonResponse;

class ContactRequestController extends Controller
{
    use ApiResponse;

    public function store(
        StoreContactRequest $request,
        ContactRequestService $service
    ): JsonResponse {
        if ($request->honeypotIsFilled()) {
            return $this->acceptedResponse();
        }

        $contactRequest = $service->create(
            $request->safe()->only([
                'name',
                'email',
                'subject',
                'message',
            ]),
            $request->ip()
        );

        return $this->acceptedResponse($contactRequest);
    }

    private function acceptedResponse(mixed $resource = null): JsonResponse
    {
        return $this->successResponse(
            data: new PublicContactRequestResource($resource),
            message: 'Tu mensaje se ha recibido correctamente.',
            status: 201
        );
    }
}
