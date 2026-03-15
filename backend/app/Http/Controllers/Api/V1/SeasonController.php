<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Season;
use Illuminate\Http\Request;

class SeasonController extends Controller
{
    public function index()
    {
        $seasons = Season::with(['championships.categories'])->orderBy('start_date', 'desc')->get();
        return response()->json($seasons);
    }
}
