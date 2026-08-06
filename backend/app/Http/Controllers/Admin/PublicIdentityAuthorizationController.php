<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PublicIdentityAuthorizationMode;
use App\Enums\PublicIdentityAuthorizationState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LinkPublicIdentityAuthorizationPlayerRequest;
use App\Http\Requests\Admin\ListPublicIdentityAuthorizationRequest;
use App\Http\Requests\Admin\RecordPublicIdentityMinorAssentRequest;
use App\Http\Requests\Admin\ResendPublicIdentityAuthorizationRequest;
use App\Http\Requests\Admin\ReviewPublicIdentityAuthorizationRequest;
use App\Models\Player;
use App\Models\PublicIdentityAuthorization;
use App\Services\PublicIdentityAuthorizationNotificationService;
use App\Services\PublicIdentityAuthorizationService;
use App\Services\PublicIdentityNoticeService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PublicIdentityAuthorizationController extends Controller
{
    public function index(ListPublicIdentityAuthorizationRequest $request)
    {
        $filters = $request->validated();
        $today = CarbonImmutable::today();
        $authorizations = PublicIdentityAuthorization::query()
            ->with(['schoolEnrollment.program', 'player.user'])
            ->when(isset($filters['state']), fn ($query) => $query->where('state', $filters['state']))
            ->when(isset($filters['mode']), fn ($query) => $query->where('mode', $filters['mode']))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('requested_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('requested_at', '<=', $filters['to']))
            ->when(($filters['age_group'] ?? null) === 'unlinked', fn ($query) => $query->whereNull('player_id'))
            ->when(($filters['age_group'] ?? null) === 'under_14', fn ($query) => $query->whereHas(
                'schoolEnrollment',
                fn ($enrollment) => $enrollment->whereDate('participant_birth_date', '>', $today->subYears(14))
            ))
            ->when(($filters['age_group'] ?? null) === '14_to_17', fn ($query) => $query->whereHas(
                'schoolEnrollment',
                fn ($enrollment) => $enrollment
                    ->whereDate('participant_birth_date', '<=', $today->subYears(14))
                    ->whereDate('participant_birth_date', '>', $today->subYears(18))
            ))
            ->when(($filters['age_group'] ?? null) === 'adult', fn ($query) => $query->whereHas(
                'schoolEnrollment',
                fn ($enrollment) => $enrollment->whereDate('participant_birth_date', '<=', $today->subYears(18))
            ))
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return view('admin.public-identity-authorizations.index', [
            'authorizations' => $authorizations,
            'states' => PublicIdentityAuthorizationState::cases(),
            'modes' => PublicIdentityAuthorizationMode::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(
        PublicIdentityAuthorization $publicIdentityAuthorization,
        PublicIdentityNoticeService $noticeService
    ) {
        $publicIdentityAuthorization->load([
            'schoolEnrollment.program',
            'player.user',
            'minorAssentRecorder',
            'reviewer',
            'revoker',
            'events.actor',
        ]);
        $birthDate = $publicIdentityAuthorization->schoolEnrollment?->participant_birth_date;

        return view('admin.public-identity-authorizations.show', [
            'authorization' => $publicIdentityAuthorization,
            'notice' => $noticeService->current(),
            'players' => Player::query()
                ->with('user')
                ->when(
                    $birthDate !== null,
                    fn ($query) => $query->whereDate('birth_date', $birthDate->toDateString()),
                    fn ($query) => $query->whereRaw('1 = 0')
                )
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function linkPlayer(
        LinkPublicIdentityAuthorizationPlayerRequest $request,
        PublicIdentityAuthorization $publicIdentityAuthorization,
        PublicIdentityAuthorizationService $service
    ) {
        $service->linkPlayer(
            $publicIdentityAuthorization,
            Player::query()->findOrFail($request->validated('player_id')),
            $request->user()
        );

        return $this->back($publicIdentityAuthorization, 'Jugador vinculado correctamente.');
    }

    public function recordAssent(
        RecordPublicIdentityMinorAssentRequest $request,
        PublicIdentityAuthorization $publicIdentityAuthorization,
        PublicIdentityAuthorizationService $service
    ) {
        $request->validated();
        $service->recordMinorAssent($publicIdentityAuthorization, $request->user());

        return $this->back($publicIdentityAuthorization, 'Conformidad informada del menor registrada.');
    }

    public function approve(
        ReviewPublicIdentityAuthorizationRequest $request,
        PublicIdentityAuthorization $publicIdentityAuthorization,
        PublicIdentityAuthorizationService $service
    ) {
        $service->approve(
            $publicIdentityAuthorization,
            $request->user(),
            $request->validated('private_reason')
        );

        return $this->back($publicIdentityAuthorization, 'Autorización aprobada.');
    }

    public function deny(
        ReviewPublicIdentityAuthorizationRequest $request,
        PublicIdentityAuthorization $publicIdentityAuthorization,
        PublicIdentityAuthorizationService $service
    ) {
        $service->deny(
            $publicIdentityAuthorization,
            $request->user(),
            $request->validated('private_reason')
        );

        return $this->back($publicIdentityAuthorization, 'Autorización denegada.');
    }

    public function revoke(
        ReviewPublicIdentityAuthorizationRequest $request,
        PublicIdentityAuthorization $publicIdentityAuthorization,
        PublicIdentityAuthorizationService $service
    ) {
        $service->revoke(
            $publicIdentityAuthorization,
            $request->user(),
            $request->validated('private_reason')
        );

        return $this->back($publicIdentityAuthorization, 'Autorización revocada.');
    }

    public function resend(
        ResendPublicIdentityAuthorizationRequest $request,
        PublicIdentityAuthorization $publicIdentityAuthorization,
        PublicIdentityAuthorizationService $service,
        PublicIdentityAuthorizationNotificationService $notificationService
    ) {
        $request->validated();
        if (! config('public_identity.notification_enabled')) {
            throw ValidationException::withMessages([
                'notification' => 'El envío de notificaciones está desactivado.',
            ]);
        }
        $result = $service->resend($publicIdentityAuthorization, $request->user());
        $sent = $notificationService->send($result['authorization'], $result['token']);

        if (! $sent) {
            return redirect()
                ->route('admin.public-identity-authorizations.show', $publicIdentityAuthorization)
                ->with('error', 'La solicitud sigue pendiente, pero el correo no pudo enviarse.');
        }

        return $this->back($publicIdentityAuthorization, 'Confirmación reenviada.');
    }

    private function back(PublicIdentityAuthorization $authorization, string $message)
    {
        return redirect()
            ->route('admin.public-identity-authorizations.show', $authorization)
            ->with('success', $message);
    }
}
