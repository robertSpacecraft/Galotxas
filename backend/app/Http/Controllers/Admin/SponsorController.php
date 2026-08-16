<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSponsorRequest;
use App\Http\Requests\Admin\UpdateSponsorRequest;
use App\Models\Sponsor;
use App\Services\Media\Exceptions\InvalidMediaImage;
use App\Services\Media\Exceptions\MediaObjectNotFound;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaDeliveryService;
use App\Services\SponsorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SponsorController extends Controller
{
    public function index()
    {
        return view('admin.sponsors.index', [
            'sponsors' => Sponsor::query()->ordered()->get(),
        ]);
    }

    public function create()
    {
        return view('admin.sponsors.create', [
            'sponsor' => new Sponsor,
        ]);
    }

    public function store(StoreSponsorRequest $request, SponsorService $service)
    {
        $validated = $request->validated();
        $logo = $request->file('logo');
        unset($validated['logo']);

        try {
            $service->create($validated, $logo);
        } catch (InvalidMediaImage|MediaStorageException $exception) {
            throw ValidationException::withMessages([
                'logo' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.sponsors.index')
            ->with('success', 'Colaborador creado correctamente.');
    }

    public function edit(Sponsor $sponsor)
    {
        return view('admin.sponsors.edit', compact('sponsor'));
    }

    public function update(
        UpdateSponsorRequest $request,
        Sponsor $sponsor,
        SponsorService $service
    ) {
        $validated = $request->validated();
        $logo = $request->file('logo');
        unset($validated['logo']);

        try {
            $service->update($sponsor, $validated, $logo);
        } catch (InvalidMediaImage|MediaStorageException $exception) {
            throw ValidationException::withMessages([
                'logo' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.sponsors.index')
            ->with('success', 'Colaborador actualizado correctamente.');
    }

    public function destroy(Sponsor $sponsor, SponsorService $service)
    {
        $service->delete($sponsor);

        return redirect()
            ->route('admin.sponsors.index')
            ->with('success', 'Colaborador eliminado correctamente.');
    }

    public function logo(
        Sponsor $sponsor,
        MediaDeliveryService $delivery
    ): Response|RedirectResponse {
        try {
            return $delivery->deliver(
                $sponsor->logo_key,
                privateTemporaryUrl: true
            );
        } catch (MediaObjectNotFound) {
            abort(404);
        } catch (MediaStorageException) {
            abort(503, 'El recurso multimedia no está disponible temporalmente.');
        }
    }
}
