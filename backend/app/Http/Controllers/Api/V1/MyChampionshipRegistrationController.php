<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChampionshipRegistrationRequestResource;
use App\Models\ChampionshipRegistrationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class MyChampionshipRegistrationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $registrations = ChampionshipRegistrationRequest::query()
            ->with([
                'championship.season',
                'user',
                'player.user',
                'suggestedCategory',
            ])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return $this->successResponse(
            ChampionshipRegistrationRequestResource::collection($registrations)
        );
    }

}
