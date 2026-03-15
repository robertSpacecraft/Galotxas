<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index() { return Category::all(); }
    public function store(Request $request) { return Category::create($request->all()); }
    public function show(Category $category) { return $category; }
    public function update(Request $request, Category $category) { $category->update($request->all()); return $category; }
    public function destroy(Category $category) { $category->delete(); return response()->noContent(); }

    public function storeEntry(Request $request, Category $category) {
        $validated = $request->validate([
            'entry_type' => 'required|in:player,team',
            'player_id' => 'nullable|exists:players,id',
            'team_id' => 'nullable|exists:teams,id',
        ]);
        return $category->entries()->create($validated);
    }
}
