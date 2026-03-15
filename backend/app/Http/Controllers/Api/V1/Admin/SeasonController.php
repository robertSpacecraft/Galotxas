<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Season;
use Illuminate\Http\Request;

class SeasonController extends Controller
{
    public function index() { return Season::all(); }
    public function store(Request $request) { return Season::create($request->all()); }
    public function show(Season $season) { return $season; }
    public function update(Request $request, Season $season) { $season->update($request->all()); return $season; }
    public function destroy(Season $season) { $season->delete(); return response()->noContent(); }
}
