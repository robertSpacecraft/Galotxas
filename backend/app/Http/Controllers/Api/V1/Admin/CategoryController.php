<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Requests\Api\Admin\StoreCategoryEntryRequest;
use App\Http\Requests\Api\Admin\StoreCategoryRequest;
use App\Http\Resources\AdminCategoryResource;
use App\Models\Category;
use App\Models\Championship;
use App\Services\CategoryEntryService;
use App\Services\CategoryMutationService;
use App\Services\OfficialResultProtectedDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->with('championship.season')
            ->orderBy('id')
            ->get();

        return $this->successResponse(AdminCategoryResource::collection($categories));
    }

    public function store(StoreCategoryRequest $request, CategoryMutationService $mutations): JsonResponse
    {
        $validated = $request->validated();
        $championship = Championship::query()->findOrFail($validated['championship_id']);

        $category = $mutations->create($championship, $validated);
        $category->load('championship.season');

        return $this->successResponse(
            new AdminCategoryResource($category),
            'Categoría creada correctamente.',
            status: 201
        );
    }

    public function show(Category $category): JsonResponse
    {
        $category->load('championship.season');

        return $this->successResponse(new AdminCategoryResource($category));
    }

    public function update(UpdateCategoryRequest $request, Category $category, CategoryMutationService $mutations): JsonResponse
    {
        $validated = $request->validated();

        $category = $mutations->update($category, $validated);
        $category->load('championship.season');

        return $this->successResponse(
            new AdminCategoryResource($category),
            'Categoría actualizada correctamente.'
        );
    }

    public function destroy(
        Category $category,
        OfficialResultProtectedDeletionService $deletions,
    ): Response {
        $deletions->deleteCategory($category);

        return response()->noContent();
    }

    public function storeEntry(
        StoreCategoryEntryRequest $request,
        Category $category,
        CategoryEntryService $entries,
    ) {
        $validated = $request->validated();

        return DB::transaction(function () use ($category, $validated, $entries) {
            $lock = $entries->lockForParticipantMutation($category);

            return $entries->create(
                $lock,
                $validated['entry_type'],
                isset($validated['player_id']) ? (int) $validated['player_id'] : null,
                isset($validated['team_id']) ? (int) $validated['team_id'] : null,
            );
        });
    }
}
