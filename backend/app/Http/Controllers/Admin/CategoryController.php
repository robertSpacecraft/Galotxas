<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Championship;
use Illuminate\Http\Request;
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
        return view('admin.categories.create', compact('championship'));
    }

    public function store(Request $request, Championship $championship)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'level' => 'required|integer',
        ]);

        Category::create([
            'championship_id' => $championship->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'level' => $validated['level'],
        ]);

        return redirect()
            ->route('admin.championships.categories', $championship)
            ->with('success', 'Categoría creada');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'level' => 'required|integer',
        ]);

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'level' => $validated['level'],
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
