<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryGender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Championship;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Championship $championship)
    {
        $categories = $championship->categories()->orderBy('level')->get();

        return view('admin.categories.index', compact('championship', 'categories'));
    }

    public function create(Championship $championship)
    {
        $genderOptions = CategoryGender::options();
        $levelOptions = range(1, 10);

        return view('admin.categories.create', compact('championship', 'genderOptions', 'levelOptions'));
    }

    public function store(StoreCategoryRequest $request, Championship $championship)
    {
        $validated = $request->validated();

        Category::create([
            'championship_id' => $championship->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'level' => $validated['level'],
            'gender' => $validated['gender'],
        ]);

        return redirect()
            ->route('admin.championships.categories', $championship)
            ->with('success', 'Categoría creada');
    }

    public function edit(Category $category)
    {
        $genderOptions = CategoryGender::options();
        $levelOptions = range(1, 10);

        return view('admin.categories.edit', compact('category', 'genderOptions', 'levelOptions'));
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $validated = $request->validated();

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'level' => $validated['level'],
            'gender' => $validated['gender'],
        ]);

        return redirect()
            ->route('admin.championships.categories', $category->championship)
            ->with('success', 'Categoría actualizada');
    }

    public function destroy(Category $category)
    {
        $championship = $category->championship;

        $category->delete();

        return redirect()
            ->route('admin.championships.categories', $championship)
            ->with('success', 'Categoría eliminada');
    }
}
