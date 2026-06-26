<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicCmsPageResource;
use App\Http\Resources\PublicCmsPageSummaryResource;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;

class CmsPageController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $pages = CmsPage::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse(
            PublicCmsPageSummaryResource::collection($pages)
        );
    }

    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::query()
            ->published()
            ->where('slug', $slug)
            ->with('blocks')
            ->firstOrFail();

        return $this->successResponse(
            new PublicCmsPageResource($page)
        );
    }
}
