<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicNewsArticleResource;
use App\Http\Resources\PublicNewsArticleSummaryResource;
use App\Models\NewsArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsArticleController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $articles = NewsArticle::query()
            ->effectivelyPublished()
            ->newestFirst()
            ->paginate(12);

        return $this->successResponse(
            PublicNewsArticleSummaryResource::collection($articles->items()),
            meta: [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
                'has_more' => $articles->hasMorePages(),
            ]
        )->header('Cache-Control', 'no-store');
    }

    public function show(string $slug): JsonResponse
    {
        $article = NewsArticle::query()
            ->effectivelyPublished()
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->successResponse(
            new PublicNewsArticleResource($article)
        )->header('Cache-Control', 'no-store');
    }
}
