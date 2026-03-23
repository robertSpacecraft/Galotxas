<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeasonResource;
use App\Models\Season;
use Illuminate\Http\JsonResponse;

class SeasonController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $seasons = Season::with(['championships.categories'])
            ->orderBy('start_date', 'desc')
            ->get();

        return $this->successResponse(
            SeasonResource::collection($seasons)
        );
    }
}
