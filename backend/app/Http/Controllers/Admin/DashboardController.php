<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Enums\GameMatchStatus;
use App\Http\Controllers\Controller;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\GameMatch;
use App\Services\ResolveApprovedUnassignedRequestsService;

class DashboardController extends Controller
{
    public function index(ResolveApprovedUnassignedRequestsService $unassignedService)
    {
        $pendingRequestsCount = ChampionshipRegistrationRequest::query()
            ->where('status', ChampionshipRegistrationRequestStatus::PENDING->value)
            ->count();

        $approvedUnassignedRequestsCount = $unassignedService->count();

        $pendingMatchConflictsCount = GameMatch::query()
            ->where('status', GameMatchStatus::UNDER_REVIEW->value)
            ->count();

        return view('admin.dashboard', compact(
            'pendingRequestsCount',
            'approvedUnassignedRequestsCount',
            'pendingMatchConflictsCount'
        ));
    }
}
