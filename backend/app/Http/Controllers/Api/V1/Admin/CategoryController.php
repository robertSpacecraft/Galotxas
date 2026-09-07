<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OfficialResultMutationImpact;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Requests\Api\Admin\StoreCategoryRequest;
use App\Http\Resources\AdminCategoryResource;
use App\Models\Category;
use App\Models\Championship;
use App\Services\CategoryMutationService;
use App\Services\OfficialResultLockService;
use App\Services\OfficialResultMutationGuard;
use App\Services\OfficialResultProtectedDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        Request $request,
        Category $category,
        OfficialResultMutationGuard $mutationGuard,
        OfficialResultLockService $locks,
    ) {
        $validated = $request->validate([
            'entry_type' => 'required|in:player,team',
            'player_id' => 'nullable|exists:players,id',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        return DB::transaction(function () use ($category, $validated, $mutationGuard, $locks) {
            $categoryLock = $mutationGuard->lockAndGuard(
                $category,
                OfficialResultMutationImpact::PARTICIPANTS
            );
            $locks->lockRoundsAndMatches([$categoryLock->category->id]);
            $locks->lockEntriesAndTeams([$categoryLock->category->id]);

            return $categoryLock->category->entries()->create($validated);
        });
    }
}
