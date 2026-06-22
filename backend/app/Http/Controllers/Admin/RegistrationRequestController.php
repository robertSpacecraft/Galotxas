<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ChampionshipRegistrationRequest;
use App\Services\ResolveApprovedUnassignedRequestsService;

class RegistrationRequestController extends Controller
{
    public function index(ResolveApprovedUnassignedRequestsService $unassignedService)
    {
        $pendingRequests = ChampionshipRegistrationRequest::query()
            ->with([
                'user',
                'player.user',
                'championship',
                'suggestedCategory',
            ])
            ->where('status', ChampionshipRegistrationRequestStatus::PENDING->value)
            ->latest()
            ->limit(20)
            ->get();

        $approvedUnassignedRequests = $unassignedService->resolve();

        return view('admin.registration-requests.index', compact(
            'pendingRequests',
            'approvedUnassignedRequests'
        ));
    }
}
