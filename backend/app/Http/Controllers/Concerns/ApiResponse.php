<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * @param mixed $data
     * @param array<string, mixed> $meta
     */
    protected function successResponse(
        mixed $data = null,
        ?string $message = null,
        array $meta = [],
        int $status = 200
    ): JsonResponse {
        $payload = [
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param array<string, mixed> $errors
     */
    protected function errorResponse(
        string $message,
        array $errors = [],
        int $status = 422
    ): JsonResponse {
        $payload = [
            'message' => $message,
            'data' => null,
        ];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
