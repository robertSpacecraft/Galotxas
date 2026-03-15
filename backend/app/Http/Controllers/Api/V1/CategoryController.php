<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\StandingsCalculatorService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Category $category)
    {
        return response()->json($category->load(['championship.season']));
    }

    public function schedule(Category $category)
    {
        $rounds = $category->rounds()->with([
            'matches.homeEntry.player', 'matches.homeEntry.team',
            'matches.awayEntry.player', 'matches.awayEntry.team',
            'matches.venue'
        ])->get();

        return response()->json($rounds);
    }

    public function standings(Category $category, StandingsCalculatorService $calculator)
    {
        $standings = $calculator->calculate($category);
        return response()->json($standings);
    }
}
