<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Requests\Api\Admin\StoreCategoryRequest;
use App\Http\Resources\AdminCategoryResource;
use App\Models\Category;
use App\Models\Championship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

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

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $championship = Championship::query()->findOrFail($validated['championship_id']);

        $category = new Category([
            'championship_id' => $championship->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'level' => $validated['level'] ?? null,
            'gender' => $validated['gender'],
            'status' => $validated['status'],
        ]);
        $category->is_public = (bool) $validated['is_public'];
        $category->save();
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

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $validated = $request->validated();

        $category->fill([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'level' => $validated['level'] ?? null,
            'gender' => $validated['gender'],
            'status' => $validated['status'],
        ]);
        $category->is_public = (bool) $validated['is_public'];
        $category->save();
        $category->load('championship.season');

        return $this->successResponse(
            new AdminCategoryResource($category),
            'Categoría actualizada correctamente.'
        );
    }

    public function destroy(Category $category): Response
    {
        $category->delete();

        return response()->noContent();
    }

    public function storeEntry(Request $request, Category $category)
    {
        $validated = $request->validate([
            'entry_type' => 'required|in:player,team',
            'player_id' => 'nullable|exists:players,id',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        return $category->entries()->create($validated);
    }
}
