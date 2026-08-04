<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListContactRequest;
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
            'counts' => $counts,
            'filters' => $filters,
        ]);
    }

    public function show(ContactRequest $contactRequest)
    {
        return view('admin.contact-requests.show', compact('contactRequest'));
    }

    public function markAsRead(
        UpdateContactRequestStatus $request,
        ContactRequest $contactRequest,
        ContactRequestService $service
    ) {
        $request->validated();
        $service->markAsRead($contactRequest);

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
        $service->close($contactRequest);

        return redirect()
            ->route('admin.contact-requests.show', $contactRequest)
            ->with('success', 'La solicitud se ha cerrado conservando su contenido original.');
    }
}
