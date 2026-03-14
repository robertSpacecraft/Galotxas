<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Championship;

class ChampionshipController extends Controller
{
    public function index()
    {
        $championships = Championship::with('season')->orderByDesc('id')->get();

        return view('admin.championships.index', compact('championships'));
    }
}
