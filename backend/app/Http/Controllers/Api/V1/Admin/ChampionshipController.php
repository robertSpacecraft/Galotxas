<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Championship;
use Illuminate\Http\Request;

class ChampionshipController extends Controller
{
    public function index() { return Championship::all(); }
    public function store(Request $request) { return Championship::create($request->all()); }
    public function show(Championship $championship) { return $championship; }
    public function update(Request $request, Championship $championship) { $championship->update($request->all()); return $championship; }
    public function destroy(Championship $championship) { $championship->delete(); return response()->noContent(); }
}
