<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSponsorResource;
use App\Models\Sponsor;
use Illuminate\Http\JsonResponse;

class SponsorController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $sponsors = Sponsor::query()
            ->effectivelyVisible()
            ->ordered()
            ->get();

        return $this->successResponse(
            PublicSponsorResource::collection($sponsors)
        )->header('Cache-Control', 'no-store');
    }
}
