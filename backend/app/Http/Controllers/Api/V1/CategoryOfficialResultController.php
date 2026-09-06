<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicCategoryOfficialResultsResource;
use App\Models\Category;
use App\Services\PublicCategoryOfficialResultsService;
use Illuminate\Http\JsonResponse;

class CategoryOfficialResultController extends Controller
{
    use ApiResponse;

    public function __invoke(
        Category $category,
        PublicCategoryOfficialResultsService $service,
    ): JsonResponse {
        abort_unless($category->isEffectivelyPublic(), 404);

        try {
            $results = $service->get($category);
        } catch (OfficialResultSourceIntegrityException $exception) {
            report($exception);

            return $this->errorResponse(
                'No se ha podido obtener el resultado oficial.',
                status: 500,
            );
        }

        return $this->successResponse(
            new PublicCategoryOfficialResultsResource($results),
        );
    }
}
