<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicContactConfigResource;
use Illuminate\Http\JsonResponse;

class ContactConfigController extends Controller
{
    use ApiResponse;

    public function __invoke(): JsonResponse
    {
        return $this->successResponse(
            new PublicContactConfigResource([
                'enabled' => (bool) config('contact.form_enabled'),
            ])
        );
    }
}
