<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('championship.season')->orderByDesc('id')->get();

        return view('admin.categories.index', compact('categories'));
    }
}
