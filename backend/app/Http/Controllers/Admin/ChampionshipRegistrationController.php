<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Services\ChampionshipRegistrationRequestService;
use App\Enums\ChampionshipRegistrationPaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChampionshipRegistrationController extends Controller
{
    public function approve(
        Championship $championship,
        ChampionshipRegistrationRequest $registrationRequest,
        ChampionshipRegistrationRequestService $service
    ) {
        if ((int) $registrationRequest->championship_id !== (int) $championship->id) {
            abort(404);
        }

        $service->approve($registrationRequest);

        return back()->with('success', 'Solicitud aprobada correctamente.');
    }

    public function reject(
        Championship $championship,
        ChampionshipRegistrationRequest $registrationRequest,
        ChampionshipRegistrationRequestService $service
    ) {
        if ((int) $registrationRequest->championship_id !== (int) $championship->id) {
            abort(404);
        }

        $service->reject($registrationRequest);

        return back()->with('success', 'Solicitud rechazada correctamente.');
    }

    public function approveAllPending(
        Championship $championship,
        ChampionshipRegistrationRequestService $service
    ) {
        $pendingRequests = ChampionshipRegistrationRequest::query()
            ->where('championship_id', $championship->id)
            ->where('status', 'pending')
            ->get();

        foreach ($pendingRequests as $registrationRequest) {
            $service->approve($registrationRequest);
        }

        return back()->with('success', 'Se han aprobado todas las solicitudes pendientes.');
    }

    public function updatePaymentStatus(
        Request $request,
        Championship $championship,
        ChampionshipRegistrationRequest $registrationRequest
    ) {
        if ((int) $registrationRequest->championship_id !== (int) $championship->id) {
            abort(404);
        }

        $validated = $request->validate([
            'payment_status' => [
                'required',
                'string',
                Rule::in(array_map(
                    fn (ChampionshipRegistrationPaymentStatus $case) => $case->value,
                    ChampionshipRegistrationPaymentStatus::cases()
                )),
            ],
        ]);

        $registrationRequest->update([
            'payment_status' => $validated['payment_status'],
        ]);

        return back()->with('success', 'Estado de pago actualizado correctamente.');
    }
}
