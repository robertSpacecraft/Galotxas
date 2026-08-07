<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactNotificationStatus;
use App\Enums\ContactRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnonymizeContactRequest;
use App\Http\Requests\Admin\ListContactRequest;
use App\Http\Requests\Admin\PlaceContactRetentionHold;
use App\Http\Requests\Admin\UpdateContactRequestStatus;
use App\Models\ContactRequest;
use App\Services\ContactRequestService;

class ContactRequestController extends Controller
{
    public function index(ListContactRequest $request)
    {
        $filters = $request->validated();
        $contactRequests = ContactRequest::query()
            ->when(
                isset($filters['status']),
                fn ($query) => $query->withStatus($filters['status'])
            )
            ->when(
                isset($filters['notification_status']),
                fn ($query) => $query->withNotificationStatus($filters['notification_status'])
            )
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        $counts = array_fill_keys(ContactRequestStatus::values(), 0);
        ContactRequest::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->get()
            ->each(function (ContactRequest $contactRequest) use (&$counts): void {
                $counts[$contactRequest->status->value] = (int) $contactRequest->aggregate;
            });

        return view('admin.contact-requests.index', [
            'contactRequests' => $contactRequests,
            'statuses' => ContactRequestStatus::cases(),
            'notificationStatuses' => ContactNotificationStatus::cases(),
            'counts' => $counts,
            'filters' => $filters,
        ]);
    }

    public function show(ContactRequest $contactRequest)
    {
        $contactRequest->load('events.actor');

        return view('admin.contact-requests.show', compact('contactRequest'));
    }

    public function markAsRead(
        UpdateContactRequestStatus $request,
        ContactRequest $contactRequest,
        ContactRequestService $service
    ) {
        $request->validated();
        $service->markAsRead($contactRequest, $request->user());

        return redirect()
            ->route('admin.contact-requests.show', $contactRequest)
            ->with('success', 'La solicitud se ha marcado como leída.');
    }

    public function close(
        UpdateContactRequestStatus $request,
        ContactRequest $contactRequest,
        ContactRequestService $service
    ) {
        $request->validated();
        $service->close($contactRequest, $request->user());

        return redirect()
            ->route('admin.contact-requests.show', $contactRequest)
            ->with('success', 'La solicitud se ha cerrado conservando su contenido original.');
    }

    public function retryNotification(
        UpdateContactRequestStatus $request,
        ContactRequest $contactRequest,
        ContactRequestService $service
    ) {
        $request->validated();
        $service->retryNotification($contactRequest, $request->user());

        return redirect()
            ->route('admin.contact-requests.show', $contactRequest)
            ->with('success', 'El reintento de notificación ha finalizado.');
    }

    public function placeRetentionHold(
        PlaceContactRetentionHold $request,
        ContactRequest $contactRequest,
        ContactRequestService $service
    ) {
        $validated = $request->validated();
        $service->placeRetentionHold(
            $contactRequest,
            $request->user(),
            $validated['retention_hold_reason']
        );

        return redirect()
            ->route('admin.contact-requests.show', $contactRequest)
            ->with('success', 'La eliminación queda suspendida temporalmente.');
    }

    public function releaseRetentionHold(
        UpdateContactRequestStatus $request,
        ContactRequest $contactRequest,
        ContactRequestService $service
    ) {
        $request->validated();
        $service->releaseRetentionHold($contactRequest, $request->user());

        return redirect()
            ->route('admin.contact-requests.show', $contactRequest)
            ->with('success', 'La suspensión de eliminación se ha liberado.');
    }

    public function anonymize(
        AnonymizeContactRequest $request,
        ContactRequest $contactRequest,
        ContactRequestService $service
    ) {
        $request->validated();
        $service->anonymize($contactRequest, $request->user());

        return redirect()
            ->route('admin.contact-requests.show', $contactRequest)
            ->with('success', 'Los datos personales de la solicitud se han anonimizado.');
    }
}
