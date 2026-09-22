<?php

namespace App\Services;

use App\Enums\PublicIdentityAuthorizationState;
use App\Models\Player;
use App\Models\PublicIdentityAuthorization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SelfServicePlayerProfileService
{
    private const DEFAULT_LEVEL = 1;

    public const KNOWN_DATE_CLEAR_ERROR =
        'No puedes eliminar una fecha de nacimiento ya registrada desde Mi Panel.';

    public const ADULT_TO_MINOR_ERROR =
        'No puedes cambiar desde Mi Panel una fecha de nacimiento adulta por una fecha de menor.';

    public const KNOWN_MINOR_DATE_ERROR =
        'Una persona registrada actualmente como menor no puede cambiar su fecha de nacimiento desde Mi Panel.';

    public const AUTHORIZATION_DATE_ERROR =
        'La fecha de nacimiento no puede cambiarse desde Mi Panel mientras exista una autorización de identidad pública pendiente o aprobada.';

    public const AUTHORIZATION_NICKNAME_ERROR =
        'El apodo no puede cambiarse desde Mi Panel mientras exista una autorización de identidad pública pendiente o aprobada para la persona menor.';

    public function __construct(
        private readonly PlayerSlugService $slugs,
        private readonly PlayerUniqueConstraintService $uniqueConstraints,
        private readonly ProfileDeclarationService $declarations,
        private readonly AccountProfileNoticeService $notices,
        private readonly PublicIdentityAuthorizationService $authorizations,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $user, array $attributes): Player
    {
        try {
            return DB::transaction(function () use ($user, $attributes): Player {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                if (Player::query()->where('user_id', $lockedUser->id)->exists()) {
                    throw ValidationException::withMessages([
                        'profile' => 'El usuario autenticado ya tiene un perfil de jugador asociado.',
                    ]);
                }

                $birthDate = $attributes['birth_date'] ?? null;
                if (! is_string($birthDate) || $birthDate === '') {
                    throw ValidationException::withMessages([
                        'birth_date' => 'La fecha de nacimiento es obligatoria.',
                    ]);
                }

                $generalRequired = ! $this->declarations->hasRecognizedGeneral($lockedUser);
                $this->assertDeclarations($attributes, $generalRequired, true);

                $player = Player::query()->create([
                    'user_id' => $lockedUser->id,
                    'nickname' => $attributes['nickname'] ?? null,
                    'slug' => $this->slugs->generate($attributes['nickname'] ?? null, $lockedUser),
                    'dni' => $attributes['dni'] ?? null,
                    'birth_date' => $birthDate,
                    'gender' => $attributes['gender'] ?? null,
                    'level' => $attributes['level'] ?? self::DEFAULT_LEVEL,
                    'license_number' => $attributes['license_number'] ?? null,
                    'dominant_hand' => $attributes['dominant_hand'] ?? null,
                    'notes' => $attributes['notes'] ?? null,
                    'active' => true,
                ]);

                if ($generalRequired) {
                    $this->declarations->recordGeneral($lockedUser, $player);
                }
                $this->declarations->recordBirthDate($lockedUser, $player);

                return $player->load(['user', 'publicIdentityAuthorizations']);
            });
        } catch (QueryException $exception) {
            $this->uniqueConstraints->rethrowAsValidation($exception);
        }
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $user, array $attributes): Player
    {
        try {
            return DB::transaction(function () use ($user, $attributes): Player {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $player = Player::query()
                    ->where('user_id', $lockedUser->id)
                    ->lockForUpdate()
                    ->first();

                if ($player === null) {
                    throw ValidationException::withMessages([
                        'profile' => 'El usuario autenticado no tiene un perfil de jugador asociado.',
                    ]);
                }

                $liveAuthorizations = PublicIdentityAuthorization::query()
                    ->where('player_id', $player->id)
                    ->whereIn('state', [
                        PublicIdentityAuthorizationState::PENDING->value,
                        PublicIdentityAuthorizationState::APPROVED->value,
                    ])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $birthDateChanges = $this->birthDateChanges($player, $attributes);
                $nicknameChanges = array_key_exists('nickname', $attributes)
                    && $attributes['nickname'] !== $player->nickname;
                $currentlyMinor = $this->authorizations->isMinor($player);

                if ($birthDateChanges) {
                    $this->assertBirthDateTransition($player, $attributes['birth_date'] ?? null);

                    if ($liveAuthorizations->isNotEmpty()) {
                        throw ValidationException::withMessages([
                            'birth_date' => self::AUTHORIZATION_DATE_ERROR,
                        ]);
                    }
                }

                if ($currentlyMinor && $nicknameChanges && $liveAuthorizations->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'nickname' => self::AUTHORIZATION_NICKNAME_ERROR,
                    ]);
                }

                $generalRequired = ! $this->declarations->hasRecognizedGeneral($lockedUser);
                $this->assertDeclarations($attributes, $generalRequired, $birthDateChanges);

                $updates = [];
                foreach (['nickname', 'dominant_hand', 'license_number', 'birth_date', 'notes'] as $field) {
                    if (array_key_exists($field, $attributes)) {
                        $updates[$field] = $attributes[$field];
                    }
                }

                if ($updates !== []) {
                    $player->update($updates);
                }

                if ($generalRequired) {
                    $this->declarations->recordGeneral($lockedUser, $player);
                }
                if ($birthDateChanges) {
                    $this->declarations->recordBirthDate($lockedUser, $player);
                }

                return $player->refresh()->load(['user', 'publicIdentityAuthorizations']);
            });
        } catch (QueryException $exception) {
            $this->uniqueConstraints->rethrowAsValidation($exception);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function birthDateChanges(Player $player, array $attributes): bool
    {
        return array_key_exists('birth_date', $attributes)
            && ($attributes['birth_date'] ?? null) !== $player->birth_date?->format('Y-m-d');
    }

    private function assertBirthDateTransition(Player $player, ?string $proposedBirthDate): void
    {
        if ($player->birth_date !== null && $proposedBirthDate === null) {
            throw ValidationException::withMessages([
                'birth_date' => self::KNOWN_DATE_CLEAR_ERROR,
            ]);
        }

        if ($player->birth_date === null || $proposedBirthDate === null) {
            return;
        }

        if ($this->authorizations->isMinor($player)) {
            throw ValidationException::withMessages([
                'birth_date' => self::KNOWN_MINOR_DATE_ERROR,
            ]);
        }

        $proposedIsMinor = CarbonImmutable::createFromFormat('!Y-m-d', $proposedBirthDate)
            ->addYearsNoOverflow(18)
            ->isAfter(CarbonImmutable::today());

        if ($proposedIsMinor) {
            throw ValidationException::withMessages([
                'birth_date' => self::ADULT_TO_MINOR_ERROR,
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function assertDeclarations(
        array $attributes,
        bool $generalRequired,
        bool $birthDateConfirmationRequired,
    ): void {
        if (! $generalRequired && ! $birthDateConfirmationRequired) {
            return;
        }

        if (! $this->notices->recognizes(
            (string) ($attributes['profile_notice_id'] ?? ''),
            (string) ($attributes['profile_notice_version'] ?? '')
        )) {
            throw ValidationException::withMessages([
                'profile_notice_version' => 'La versión del aviso de cuenta y perfil no está vigente.',
            ]);
        }

        if ($generalRequired && ! filter_var(
            $attributes['profile_declaration_accepted'] ?? false,
            FILTER_VALIDATE_BOOL
        )) {
            throw ValidationException::withMessages([
                'profile_declaration_accepted' => 'Debes aceptar la declaración de exactitud para continuar.',
            ]);
        }

        if ($birthDateConfirmationRequired && ! filter_var(
            $attributes['birth_date_confirmed'] ?? false,
            FILTER_VALIDATE_BOOL
        )) {
            throw ValidationException::withMessages([
                'birth_date_confirmed' => 'Debes confirmar que la fecha de nacimiento indicada es correcta.',
            ]);
        }
    }
}
