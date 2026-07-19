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
        $seasons = Season::query()
            ->effectivelyPublic()
            ->with([
                'championships' => fn ($query) => $query
                    ->effectivelyPublic()
                    ->with([
                        'categories' => fn ($categoryQuery) => $categoryQuery->effectivelyPublic(),
                    ]),
            ])
            ->orderBy('start_date', 'desc')
            ->get();

        return $this->successResponse(
            SeasonResource::collection($seasons)
        );
    }
}
