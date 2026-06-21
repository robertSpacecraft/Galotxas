<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ChampionshipRegistrationRequest;

class DashboardController extends Controller
{
    public function index()
    {
        $pendingRequests = ChampionshipRegistrationRequest::query()
            ->with([
                'user',
                'player.user',
                'championship',
                'suggestedCategory'
            ])
            ->where('status', ChampionshipRegistrationRequestStatus::PENDING->value)
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.dashboard', compact('pendingRequests'));
    }
}
