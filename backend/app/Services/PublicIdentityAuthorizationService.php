<?php

namespace App\Services;

use App\Enums\PublicIdentityAuthorizationEventType;
use App\Enums\PublicIdentityAuthorizationMode;
use App\Enums\PublicIdentityAuthorizationState;
use App\Models\Player;
use App\Models\PublicIdentityAuthorization;
use App\Models\SchoolEnrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicIdentityAuthorizationService
{
    public function __construct(
        private readonly PublicIdentityNoticeService $noticeService,
        private readonly PublicIdentityAuthorizationEventService $eventService,
        private readonly UnicodeTextService $text,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{authorization: PublicIdentityAuthorization, token: string|null}
     */
    public function createForEnrollment(
        SchoolEnrollment $enrollment,
        array $attributes
    ): array {
        $mode = PublicIdentityAuthorizationMode::from($attributes['mode']);
        $notice = $this->noticeService->current();
        $now = CarbonImmutable::now();

        if (! $enrollment->wasMinorAtRequest()) {
            throw ValidationException::withMessages([
                'public_identity_authorization' => 'La autorización de representante sólo está disponible para participantes menores.',
            ]);
        }

        return $this->createAuthorization(
            $enrollment,
            null,
            $mode,
            Str::lower(trim($enrollment->contact_email)),
            $enrollment->guardian_name,
            $enrollment->guardian_relationship,
            $notice,
            $now,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{authorization: PublicIdentityAuthorization, token: string|null}
     */
    public function createForPlayer(Player $player, User $actor, array $attributes): array
    {
        if (! config('public_identity.authorization_enabled')) {
            $this->fail('public_identity_authorization', 'La solicitud de identidad pública está desactivada.');
        }

        $mode = is_string($attributes['mode'] ?? null)
            ? PublicIdentityAuthorizationMode::tryFrom($attributes['mode'])
            : null;
        if (! in_array($mode, [
            PublicIdentityAuthorizationMode::ALIAS,
            PublicIdentityAuthorizationMode::NAME_INITIAL,
        ], true)) {
            $this->fail('mode', 'El modo de identidad pública no es válido.');
        }
        if (! config('public_identity.notification_enabled')) {
            $this->fail(
                'notification',
                'El envío de confirmaciones está desactivado. No puede iniciarse una solicitud directa.'
            );
        }

        $notice = $this->noticeService->current();
        $noticeId = $attributes['notice_id'] ?? null;
        $noticeVersion = $attributes['notice_version'] ?? null;
        if (
            ! is_string($noticeId)
            || ! is_string($noticeVersion)
            || ! $this->noticeService->recognizes(
                $noticeId,
                $noticeVersion,
                PublicIdentityAuthorization::SCOPE
            )
        ) {
            $this->fail('notice_version', 'El aviso de autorización no está vigente.');
        }

        $guardianName = $this->normalizedRequiredText(
            $attributes['guardian_name'] ?? null,
            'guardian_name',
            'El nombre del representante es obligatorio.'
        );
        $guardianRelationship = $this->normalizedRequiredText(
            $attributes['guardian_relationship'] ?? null,
            'guardian_relationship',
            'La relación del representante es obligatoria.'
        );
        $guardianEmail = is_string($attributes['guardian_email'] ?? null)
            ? Str::lower(trim($attributes['guardian_email']))
            : '';
        if ($guardianEmail === '' || filter_var($guardianEmail, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail('guardian_email', 'El correo del representante no es válido.');
        }
        if (! in_array(
            $attributes['guardian_authority_declared'] ?? null,
            ['yes', 'on', '1', 1, true, 'true'],
            true
        )) {
            $this->fail(
                'guardian_authority_declared',
                'Debe constar que el representante declaró ante el Club ejercer la patria potestad o tutela.'
            );
        }

        return DB::transaction(function () use (
            $player,
            $mode,
            $guardianEmail,
            $guardianName,
            $guardianRelationship,
            $notice,
            $actor
        ): array {
            $lockedPlayer = Player::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($player->id);

            if ($lockedPlayer->birth_date === null) {
                $this->fail('player', 'El jugador necesita una fecha de nacimiento para solicitar esta autorización.');
            }
            if (! $this->isMinor($lockedPlayer)) {
                $this->fail('player', 'La autorización de representante sólo está disponible para jugadores menores.');
            }
            if (PublicIdentityAuthorization::query()
                ->where('player_id', $lockedPlayer->id)
                ->where('scope', PublicIdentityAuthorization::SCOPE)
                ->whereIn('state', [
                    PublicIdentityAuthorizationState::PENDING->value,
                    PublicIdentityAuthorizationState::APPROVED->value,
                ])
                ->exists()) {
                $this->fail(
                    'player',
                    'El jugador ya tiene una solicitud pendiente o aprobada para este alcance.'
                );
            }

            $this->assertPlayerSupportsMode($lockedPlayer, $mode);

            return $this->createAuthorization(
                null,
                $lockedPlayer,
                $mode,
                $guardianEmail,
                $guardianName,
                $guardianRelationship,
                $notice,
                CarbonImmutable::now(),
                $actor,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $notice
     * @return array{authorization: PublicIdentityAuthorization, token: string|null}
     */
    private function createAuthorization(
        ?SchoolEnrollment $enrollment,
        ?Player $player,
        PublicIdentityAuthorizationMode $mode,
        string $guardianEmail,
        string $guardianName,
        string $guardianRelationship,
        array $notice,
        CarbonImmutable $now,
        ?User $actor = null,
    ): array {
        $authorization = new PublicIdentityAuthorization;
        $authorization->forceFill([
            'school_enrollment_id' => $enrollment?->id,
            'player_id' => $player?->id,
            'scope' => PublicIdentityAuthorization::SCOPE,
            'mode' => $mode,
            'state' => $mode === PublicIdentityAuthorizationMode::ANONYMOUS
                ? PublicIdentityAuthorizationState::DENIED
                : PublicIdentityAuthorizationState::PENDING,
            'approval_slot' => null,
            'guardian_email' => $guardianEmail,
            'guardian_name' => $guardianName,
            'guardian_relationship' => $guardianRelationship,
            'guardian_authority_declared_at' => $now,
            'notice_id' => $notice['id'],
            'notice_version' => $notice['version'],
            'requested_at' => $now,
            'denied_at' => $mode === PublicIdentityAuthorizationMode::ANONYMOUS ? $now : null,
        ]);

        $token = null;
        if ($mode !== PublicIdentityAuthorizationMode::ANONYMOUS) {
            $token = $this->freshToken();
            $authorization->forceFill([
                'confirmation_token_hash' => hash('sha256', $token),
                'confirmation_token_expires_at' => $now->addHours(
                    max(1, (int) config('public_identity.confirmation_ttl_hours', 48))
                ),
            ]);
        }
        $authorization->save();

        $this->eventService->record(
            $authorization,
            $mode === PublicIdentityAuthorizationMode::ANONYMOUS
                ? PublicIdentityAuthorizationEventType::ANONYMOUS_SELECTED
                : PublicIdentityAuthorizationEventType::REQUESTED,
            $actor,
        );

        return ['authorization' => $authorization->refresh(), 'token' => $token];
    }

    /** @return array{authorization: PublicIdentityAuthorization, token: string} */
    public function resend(PublicIdentityAuthorization $authorization, User $actor): array
    {
        return DB::transaction(function () use ($authorization, $actor): array {
            $locked = $this->lock($authorization);
            $this->assertPending($locked);
            if ($locked->guardian_confirmed_at !== null) {
                $this->fail('state', 'La confirmación ya fue completada y no puede reenviarse.');
            }

            $token = $this->freshToken();
            $locked->forceFill([
                'confirmation_token_hash' => hash('sha256', $token),
                'confirmation_token_expires_at' => CarbonImmutable::now()->addHours(
                    max(1, (int) config('public_identity.confirmation_ttl_hours', 48))
                ),
                'confirmation_token_used_at' => null,
            ])->save();
            $this->eventService->record($locked, PublicIdentityAuthorizationEventType::RESENT, $actor);

            return ['authorization' => $locked->refresh(), 'token' => $token];
        });
    }

    /** @return array<string, mixed>|null */
    public function lookup(string $plainToken): ?array
    {
        $authorization = $this->findUsableToken($plainToken);
        if ($authorization === null) {
            return null;
        }

        return [
            'scope' => $authorization->scope,
            'mode' => $authorization->mode->value,
            'notice_version' => $authorization->notice_version,
            'expires_at' => $authorization->confirmation_token_expires_at?->toIso8601String(),
        ];
    }

    public function confirm(string $plainToken): bool
    {
        return $this->decideToken($plainToken, true);
    }

    public function denyByGuardian(string $plainToken): bool
    {
        return $this->decideToken($plainToken, false);
    }

    public function linkPlayer(
        PublicIdentityAuthorization $authorization,
        Player $player,
        User $actor
    ): PublicIdentityAuthorization {
        return DB::transaction(function () use ($authorization, $player, $actor): PublicIdentityAuthorization {
            $player = Player::query()->lockForUpdate()->findOrFail($player->id);
            $locked = $this->lock($authorization);
            $this->assertPending($locked);

            if ($locked->school_enrollment_id === null) {
                $this->fail(
                    'player_id',
                    'Una autorización creada desde un jugador no puede vincularse a otro jugador.'
                );
            }

            $enrollment = $locked->schoolEnrollment()->lockForUpdate()->first();
            $previousPlayerId = $locked->player_id;

            if (
                $player->birth_date === null
                || $enrollment === null
                || $player->birth_date->toDateString() !== $enrollment->participant_birth_date->toDateString()
                || ! $this->isMinor($player)
            ) {
                $this->fail('player_id', 'El jugador debe ser menor y coincidir con la fecha de nacimiento de la inscripción.');
            }

            if ($previousPlayerId === $player->id) {
                $this->fail('player_id', 'El jugador seleccionado ya está vinculado a esta solicitud.');
            }

            if (
                $previousPlayerId !== null
                && ($locked->guardian_confirmed_at !== null || $locked->minor_assent_recorded_at !== null)
            ) {
                $this->fail(
                    'player_id',
                    'No puede cambiarse el jugador después de confirmar la evidencia. Revoca o cierra esta solicitud y registra una nueva autorización.'
                );
            }

            $locked->forceFill(['player_id' => $player->id])->save();
            $this->eventService->record(
                $locked,
                $previousPlayerId === null
                    ? PublicIdentityAuthorizationEventType::PLAYER_LINKED
                    : PublicIdentityAuthorizationEventType::PLAYER_LINK_CHANGED,
                $actor,
                $previousPlayerId === null
                    ? ['player_id' => $player->id]
                    : [
                        'previous_player_id' => $previousPlayerId,
                        'player_id' => $player->id,
                    ]
            );

            return $locked->refresh();
        });
    }

    public function recordMinorAssent(
        PublicIdentityAuthorization $authorization,
        User $actor
    ): PublicIdentityAuthorization {
        return DB::transaction(function () use ($authorization, $actor): PublicIdentityAuthorization {
            $locked = $this->lock($authorization);
            $this->assertPending($locked);
            $player = $locked->player()->first();
            if ($player === null || ! $this->requiresMinorAssent($player)) {
                $this->fail('minor_assent', 'La conformidad sólo procede para menores de 14 a 17 años vinculados.');
            }

            $now = CarbonImmutable::now();
            $locked->forceFill([
                'minor_assent_recorded_at' => $now,
                'minor_assent_recorded_by' => $actor->id,
            ])->save();
            $this->eventService->record(
                $locked,
                PublicIdentityAuthorizationEventType::MINOR_ASSENT_RECORDED,
                $actor,
                ['notice_version' => $locked->notice_version]
            );

            return $locked->refresh();
        });
    }

    public function approve(
        PublicIdentityAuthorization $authorization,
        User $actor,
        ?string $privateReason = null
    ): PublicIdentityAuthorization {
        return DB::transaction(function () use ($authorization, $actor, $privateReason): PublicIdentityAuthorization {
            $locked = $this->lock($authorization);
            $this->assertPending($locked);
            $player = $locked->player()->first();

            if ($player === null || ! $this->isMinor($player)) {
                $this->fail('player_id', 'La autorización requiere un jugador menor vinculado de forma inequívoca.');
            }
            if ($locked->mode === PublicIdentityAuthorizationMode::ANONYMOUS) {
                $this->fail('mode', 'La identidad anónima no necesita aprobación.');
            }
            if ($locked->guardian_confirmed_at === null) {
                $this->fail('guardian_confirmation', 'El representante todavía no ha confirmado la autorización.');
            }
            if (! $this->noticeService->recognizes($locked->notice_id, $locked->notice_version, $locked->scope)) {
                $this->fail('notice_version', 'La versión del aviso no está reconocida.');
            }
            if ($this->requiresMinorAssent($player) && $locked->minor_assent_recorded_at === null) {
                $this->fail('minor_assent', 'Debe registrarse la conformidad informada del menor de 14 a 17 años.');
            }
            if (PublicIdentityAuthorization::query()
                ->where('player_id', $player->id)
                ->where('scope', $locked->scope)
                ->where('state', PublicIdentityAuthorizationState::APPROVED->value)
                ->where('id', '!=', $locked->id)
                ->exists()) {
                $this->fail('player_id', 'El jugador ya tiene una autorización aprobada para este alcance.');
            }

            $now = CarbonImmutable::now();
            $locked->forceFill([
                'state' => PublicIdentityAuthorizationState::APPROVED,
                'approval_slot' => 1,
                'reviewed_at' => $now,
                'reviewed_by' => $actor->id,
                'approved_at' => $now,
                'private_reason' => $this->nullableReason($privateReason),
                'confirmation_token_hash' => null,
            ])->save();
            $this->eventService->record($locked, PublicIdentityAuthorizationEventType::APPROVED, $actor);

            return $locked->refresh();
        });
    }

    public function deny(
        PublicIdentityAuthorization $authorization,
        User $actor,
        ?string $privateReason = null
    ): PublicIdentityAuthorization {
        return DB::transaction(function () use ($authorization, $actor, $privateReason): PublicIdentityAuthorization {
            $locked = $this->lock($authorization);
            $this->assertPending($locked);
            $now = CarbonImmutable::now();
            $locked->forceFill([
                'state' => PublicIdentityAuthorizationState::DENIED,
                'approval_slot' => null,
                'reviewed_at' => $now,
                'reviewed_by' => $actor->id,
                'denied_at' => $now,
                'private_reason' => $this->nullableReason($privateReason),
                'confirmation_token_hash' => null,
                'confirmation_token_used_at' => $now,
            ])->save();
            $this->eventService->record($locked, PublicIdentityAuthorizationEventType::DENIED, $actor);

            return $locked->refresh();
        });
    }

    public function revoke(
        PublicIdentityAuthorization $authorization,
        User $actor,
        ?string $privateReason = null
    ): PublicIdentityAuthorization {
        return DB::transaction(function () use ($authorization, $actor, $privateReason): PublicIdentityAuthorization {
            $locked = $this->lock($authorization);
            if ($locked->state !== PublicIdentityAuthorizationState::APPROVED) {
                $this->fail('state', 'Sólo puede revocarse una autorización aprobada.');
            }

            $now = CarbonImmutable::now();
            $locked->forceFill([
                'state' => PublicIdentityAuthorizationState::REVOKED,
                'approval_slot' => null,
                'revoked_at' => $now,
                'revoked_by' => $actor->id,
                'private_reason' => $this->nullableReason($privateReason),
            ])->save();
            $this->eventService->record($locked, PublicIdentityAuthorizationEventType::REVOKED, $actor);

            return $locked->refresh();
        });
    }

    public function isEffectiveFor(
        PublicIdentityAuthorization $authorization,
        Player $player,
        ?CarbonInterface $asOf = null
    ): bool {
        $referenceDate = $asOf
            ? CarbonImmutable::instance($asOf)
            : CarbonImmutable::now();

        return config('public_identity.authorization_enabled')
            && $authorization->player_id === $player->id
            && $authorization->scope === PublicIdentityAuthorization::SCOPE
            && $authorization->state === PublicIdentityAuthorizationState::APPROVED
            && $authorization->approval_slot === 1
            && $authorization->mode !== PublicIdentityAuthorizationMode::ANONYMOUS
            && $authorization->guardian_confirmed_at !== null
            && $authorization->reviewed_at !== null
            && $authorization->reviewed_by !== null
            && $authorization->approved_at !== null
            && ($authorization->expires_at === null || $authorization->expires_at->isAfter($referenceDate))
            && $this->noticeService->recognizes(
                $authorization->notice_id,
                $authorization->notice_version,
                $authorization->scope
            )
            && $this->isMinor($player, $referenceDate)
            && (! $this->requiresMinorAssent($player, $referenceDate)
                || ($authorization->minor_assent_recorded_at !== null
                    && $authorization->minor_assent_recorded_by !== null));
    }

    public function isMinor(Player $player, ?CarbonInterface $asOf = null): bool
    {
        if ($player->birth_date === null) {
            return false;
        }
        $referenceDate = $asOf
            ? CarbonImmutable::instance($asOf)->startOfDay()
            : CarbonImmutable::today();

        return CarbonImmutable::instance($player->birth_date)
            ->startOfDay()
            ->addYearsNoOverflow(18)
            ->isAfter($referenceDate);
    }

    public function requiresMinorAssent(Player $player, ?CarbonInterface $asOf = null): bool
    {
        if ($player->birth_date === null) {
            return false;
        }
        $referenceDate = $asOf
            ? CarbonImmutable::instance($asOf)->startOfDay()
            : CarbonImmutable::today();
        $birthDate = CarbonImmutable::instance($player->birth_date)->startOfDay();

        return $birthDate->addYearsNoOverflow(14)->lessThanOrEqualTo($referenceDate)
            && $birthDate->addYearsNoOverflow(18)->isAfter($referenceDate);
    }

    public function playerSupportsMode(
        Player $player,
        PublicIdentityAuthorizationMode $mode
    ): bool {
        return match ($mode) {
            PublicIdentityAuthorizationMode::ALIAS => $this->text->squish($player->nickname) !== '',
            PublicIdentityAuthorizationMode::NAME_INITIAL => $this->text->squish($player->user?->name) !== ''
                && $this->text->squish($player->user?->lastname) !== '',
            PublicIdentityAuthorizationMode::ANONYMOUS => true,
        };
    }

    private function decideToken(string $plainToken, bool $confirmed): bool
    {
        return DB::transaction(function () use ($plainToken, $confirmed): bool {
            $authorization = $this->findUsableToken($plainToken, true);
            if ($authorization === null) {
                return false;
            }

            $now = CarbonImmutable::now();
            $authorization->forceFill($confirmed ? [
                'guardian_confirmed_at' => $now,
                'confirmation_token_used_at' => $now,
                'confirmation_token_hash' => null,
            ] : [
                'state' => PublicIdentityAuthorizationState::DENIED,
                'guardian_denied_at' => $now,
                'denied_at' => $now,
                'confirmation_token_used_at' => $now,
                'confirmation_token_hash' => null,
            ])->save();
            $this->eventService->record(
                $authorization,
                $confirmed
                    ? PublicIdentityAuthorizationEventType::GUARDIAN_CONFIRMED
                    : PublicIdentityAuthorizationEventType::GUARDIAN_DENIED
            );

            return true;
        });
    }

    private function findUsableToken(
        string $plainToken,
        bool $lock = false
    ): ?PublicIdentityAuthorization {
        if (! config('public_identity.authorization_enabled') || strlen($plainToken) < 40) {
            return null;
        }

        $query = PublicIdentityAuthorization::query()
            ->where('confirmation_token_hash', hash('sha256', $plainToken));
        if ($lock) {
            $query->lockForUpdate();
        }
        $authorization = $query->first();

        if (
            $authorization === null
            || $authorization->state !== PublicIdentityAuthorizationState::PENDING
            || $authorization->confirmation_token_used_at !== null
            || $authorization->confirmation_token_expires_at === null
        ) {
            return null;
        }

        if (! $authorization->confirmation_token_expires_at->isFuture()) {
            $authorization->forceFill([
                'state' => PublicIdentityAuthorizationState::EXPIRED,
                'confirmation_token_hash' => null,
                'confirmation_token_used_at' => CarbonImmutable::now(),
            ])->save();
            $this->eventService->record($authorization, PublicIdentityAuthorizationEventType::EXPIRED);

            return null;
        }

        return $authorization;
    }

    private function lock(PublicIdentityAuthorization $authorization): PublicIdentityAuthorization
    {
        return PublicIdentityAuthorization::query()->lockForUpdate()->findOrFail($authorization->id);
    }

    private function assertPending(PublicIdentityAuthorization $authorization): void
    {
        if ($authorization->state !== PublicIdentityAuthorizationState::PENDING) {
            $this->fail('state', 'La acción sólo está disponible para solicitudes pendientes.');
        }
    }

    private function freshToken(): string
    {
        return Str::random(64);
    }

    private function assertPlayerSupportsMode(
        Player $player,
        PublicIdentityAuthorizationMode $mode
    ): void {
        if (
            $mode === PublicIdentityAuthorizationMode::ALIAS
            && ! $this->playerSupportsMode($player, $mode)
        ) {
            $this->fail('mode', 'El jugador no tiene un alias deportivo publicable.');
        }

        if (
            $mode === PublicIdentityAuthorizationMode::NAME_INITIAL
            && ! $this->playerSupportsMode($player, $mode)
        ) {
            $this->fail(
                'mode',
                'El jugador no tiene nombre y primer apellido suficientes para publicar nombre e inicial.'
            );
        }
    }

    private function normalizedRequiredText(mixed $value, string $field, string $message): string
    {
        $normalized = is_string($value) ? $this->text->squish($value) : '';
        if ($normalized === '') {
            $this->fail($field, $message);
        }

        return $normalized;
    }

    private function nullableReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : $reason;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
