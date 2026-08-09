<?php

namespace App\Services;

use App\Enums\SchoolEnrollmentStatus;
use App\Exceptions\SchoolEnrollmentUnavailableException;
use App\Models\SchoolEnrollment;
use App\Models\SchoolLevel;
use App\Models\SchoolProgram;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchoolEnrollmentService
{
    public const PUBLIC_LEVEL_ERROR = 'El nivel seleccionado no está disponible.';

    public const ADMIN_LEVEL_ERROR =
        'El nivel debe estar activo y pertenecer al programa de la inscripción.';

    public function __construct(
        private readonly PublicIdentityAuthorizationService $authorizationService,
        private readonly PublicIdentityAuthorizationNotificationService $notificationService,
        private readonly SchoolEnrollmentAvailabilityService $availability
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPublic(
        array $attributes,
        ?User $user = null
    ): SchoolEnrollment {
        $result = DB::transaction(function () use ($attributes, $user): array {
            $program = SchoolProgram::query()
                ->effectivelyPublic()
                ->lockForUpdate()
                ->first();

            if ($program === null || ! $this->availability->isOpen($program)) {
                throw new SchoolEnrollmentUnavailableException;
            }

            $level = $this->resolveLevel(
                $program,
                $attributes['school_level_id'] ?? null,
                requireLevel: false,
                requirePublic: true
            );

            $enrollment = $this->createPending(
                $program,
                $level,
                $attributes,
                $user,
                CarbonImmutable::now(),
                includeAdminNotes: false
            );

            $authorization = null;
            if (isset($attributes['public_identity_authorization'])) {
                $authorization = $this->authorizationService->createForEnrollment(
                    $enrollment,
                    $attributes['public_identity_authorization']
                );
            }

            return ['enrollment' => $enrollment, 'authorization' => $authorization];
        });

        if ($result['authorization'] !== null && $result['authorization']['token'] !== null) {
            $this->notificationService->send(
                $result['authorization']['authorization'],
                $result['authorization']['token']
            );
        }

        return $result['enrollment'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createManual(array $attributes): SchoolEnrollment
    {
        return DB::transaction(function () use ($attributes): SchoolEnrollment {
            $program = SchoolProgram::query()
                ->lockForUpdate()
                ->findOrFail($attributes['school_program_id']);
            $level = $this->resolveLevel(
                $program,
                $attributes['school_level_id'] ?? null,
                requireLevel: false,
                requirePublic: false
            );

            return $this->createPending(
                $program,
                $level,
                $attributes,
                null,
                CarbonImmutable::now(),
                includeAdminNotes: true
            );
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateDetails(
        SchoolEnrollment $enrollment,
        array $attributes,
        ?User $actor = null
    ): SchoolEnrollment {
        return DB::transaction(function () use (
            $enrollment,
            $attributes,
            $actor
        ): SchoolEnrollment {
            $locked = $this->lockEnrollment($enrollment);
            if ($locked->isAnonymized()) {
                throw ValidationException::withMessages([
                    'enrollment' => 'No se pueden corregir datos de una inscripción anonimizada.',
                ]);
            }
            $participant = $this->normalizedParticipantAttributes(
                $attributes,
                CarbonImmutable::instance($locked->requested_at)
            );

            $locked->forceFill([
                ...$participant,
                'admin_notes' => $attributes['admin_notes'] ?? null,
                'corrected_at' => CarbonImmutable::now(),
                'corrected_by' => $actor?->id,
            ])->save();

            return $locked->refresh();
        });
    }

    public function approve(
        SchoolEnrollment $enrollment,
        int $levelId,
        ?User $actor = null
    ): SchoolEnrollment {
        return DB::transaction(function () use (
            $enrollment,
            $levelId,
            $actor
        ): SchoolEnrollment {
            $locked = $this->lockEnrollment($enrollment);
            $this->assertStatus($locked, SchoolEnrollmentStatus::PENDING);
            $level = $this->resolveLevel(
                $locked->program,
                $levelId,
                requireLevel: true,
                requirePublic: false
            );

            $locked->forceFill([
                'school_level_id' => $level->id,
                'status' => SchoolEnrollmentStatus::ACTIVE,
                'activated_at' => CarbonImmutable::now(),
                'activated_by' => $actor?->id,
                'rejected_at' => null,
                'rejected_by' => null,
                'withdrawn_at' => null,
                'withdrawn_by' => null,
                'retention_until' => null,
            ])->save();

            return $locked->refresh();
        });
    }

    public function reject(
        SchoolEnrollment $enrollment,
        ?User $actor = null
    ): SchoolEnrollment {
        return DB::transaction(function () use ($enrollment, $actor): SchoolEnrollment {
            $locked = $this->lockEnrollment($enrollment);
            $this->assertStatus($locked, SchoolEnrollmentStatus::PENDING);
            $rejectedAt = CarbonImmutable::now();

            $locked->forceFill([
                'status' => SchoolEnrollmentStatus::REJECTED,
                'activated_at' => null,
                'activated_by' => null,
                'rejected_at' => $rejectedAt,
                'rejected_by' => $actor?->id,
                'withdrawn_at' => null,
                'withdrawn_by' => null,
                'retention_until' => $rejectedAt->addMonthsNoOverflow(
                    $this->unformalizedRetentionMonths()
                ),
            ])->save();

            return $locked->refresh();
        });
    }

    public function withdraw(
        SchoolEnrollment $enrollment,
        ?User $actor = null
    ): SchoolEnrollment {
        return DB::transaction(function () use ($enrollment, $actor): SchoolEnrollment {
            $locked = $this->lockEnrollment($enrollment);
            $this->assertStatus($locked, SchoolEnrollmentStatus::ACTIVE);
            $withdrawnAt = CarbonImmutable::now();

            $locked->forceFill([
                'status' => SchoolEnrollmentStatus::WITHDRAWN,
                'rejected_at' => null,
                'rejected_by' => null,
                'withdrawn_at' => $withdrawnAt,
                'withdrawn_by' => $actor?->id,
                'retention_until' => $withdrawnAt->addYearsNoOverflow(
                    $this->studentRetentionYears()
                ),
            ])->save();

            return $locked->refresh();
        });
    }

    public function reassignLevel(
        SchoolEnrollment $enrollment,
        int $levelId,
        ?User $actor = null
    ): SchoolEnrollment {
        return DB::transaction(function () use (
            $enrollment,
            $levelId,
            $actor
        ): SchoolEnrollment {
            $locked = $this->lockEnrollment($enrollment);
            $this->assertStatus($locked, SchoolEnrollmentStatus::ACTIVE);
            $level = $this->resolveLevel(
                $locked->program,
                $levelId,
                requireLevel: true,
                requirePublic: false
            );

            $locked->forceFill([
                'school_level_id' => $level->id,
                'corrected_at' => CarbonImmutable::now(),
                'corrected_by' => $actor?->id,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPending(
        SchoolProgram $program,
        ?SchoolLevel $level,
        array $attributes,
        ?User $user,
        CarbonImmutable $requestedAt,
        bool $includeAdminNotes
    ): SchoolEnrollment {
        $participant = $this->normalizedParticipantAttributes(
            $attributes,
            $requestedAt
        );

        $enrollment = new SchoolEnrollment;
        $enrollment->forceFill([
            'school_program_id' => $program->id,
            'school_level_id' => $level?->id,
            'user_id' => $user?->id,
            ...$participant,
            'status' => SchoolEnrollmentStatus::PENDING,
            'requested_at' => $requestedAt,
            'activated_at' => null,
            'rejected_at' => null,
            'withdrawn_at' => null,
            'retention_until' => $requestedAt->addMonthsNoOverflow(
                $this->unformalizedRetentionMonths()
            ),
            'admin_notes' => $includeAdminNotes
                ? ($attributes['admin_notes'] ?? null)
                : null,
            'privacy_notice_version' => $includeAdminNotes
                ? ($attributes['privacy_notice_version'] ?? null)
                : $attributes['privacy_notice_version'],
            'privacy_notice_id' => $includeAdminNotes
                ? ($attributes['privacy_notice_id'] ?? null)
                : $attributes['privacy_notice_id'],
            'privacy_acknowledged_at' => $includeAdminNotes
                ? null
                : $requestedAt,
        ]);
        $enrollment->save();

        return $enrollment->refresh();
    }

    public function placeRetentionHold(
        SchoolEnrollment $enrollment,
        User $actor,
        string $reason
    ): SchoolEnrollment {
        return DB::transaction(function () use ($enrollment, $actor, $reason): SchoolEnrollment {
            $locked = $this->lockEnrollment($enrollment);

            if ($locked->status === SchoolEnrollmentStatus::ACTIVE || $locked->isAnonymized()) {
                throw ValidationException::withMessages([
                    'retention_hold_reason' => 'Sólo puede suspenderse la eliminación de una inscripción no activa y no anonimizada.',
                ]);
            }

            if ($locked->retention_hold) {
                throw ValidationException::withMessages([
                    'retention_hold_reason' => 'La inscripción ya tiene una suspensión activa.',
                ]);
            }

            $locked->forceFill([
                'retention_hold' => true,
                'retention_hold_reason' => $reason,
                'retention_hold_placed_at' => CarbonImmutable::now(),
                'retention_hold_placed_by' => $actor->id,
                'retention_hold_released_at' => null,
                'retention_hold_released_by' => null,
            ])->save();

            return $locked->refresh();
        });
    }

    public function releaseRetentionHold(
        SchoolEnrollment $enrollment,
        User $actor
    ): SchoolEnrollment {
        return DB::transaction(function () use ($enrollment, $actor): SchoolEnrollment {
            $locked = $this->lockEnrollment($enrollment);

            if (! $locked->retention_hold) {
                throw ValidationException::withMessages([
                    'retention_hold' => 'La inscripción no tiene una suspensión activa.',
                ]);
            }

            $locked->forceFill([
                'retention_hold' => false,
                'retention_hold_released_at' => CarbonImmutable::now(),
                'retention_hold_released_by' => $actor->id,
            ])->save();

            return $locked->refresh();
        });
    }

    public function anonymize(
        SchoolEnrollment $enrollment,
        ?User $actor = null
    ): SchoolEnrollment {
        return DB::transaction(function () use ($enrollment, $actor): SchoolEnrollment {
            $locked = $this->lockEnrollment($enrollment);

            if (! $this->canAnonymize($locked)) {
                throw ValidationException::withMessages([
                    'anonymize' => 'La inscripción no cumple las condiciones de anonimización.',
                ]);
            }

            $locked->forceFill([
                'user_id' => null,
                'participant_name' => null,
                'participant_birth_date' => null,
                'contact_phone' => null,
                'contact_email' => null,
                'guardian_name' => null,
                'guardian_relationship' => null,
                'admin_notes' => null,
                'retention_hold_reason' => null,
                'anonymized_at' => CarbonImmutable::now(),
                'corrected_at' => CarbonImmutable::now(),
                'corrected_by' => $actor?->id,
            ])->save();

            return $locked->refresh();
        });
    }

    public function canAnonymize(SchoolEnrollment $enrollment): bool
    {
        return $enrollment->status !== SchoolEnrollmentStatus::ACTIVE
            && $enrollment->retention_until?->lessThanOrEqualTo(CarbonImmutable::now())
            && ! $enrollment->retention_hold
            && ! $enrollment->isAnonymized();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizedParticipantAttributes(
        array $attributes,
        CarbonImmutable $referenceDate
    ): array {
        $birthDate = CarbonImmutable::parse($attributes['participant_birth_date']);
        $isMinor = SchoolEnrollmentAgeService::isMinor(
            $birthDate,
            $referenceDate
        );

        if (
            $isMinor
            && (
                empty($attributes['guardian_name'])
                || empty($attributes['guardian_relationship'])
            )
        ) {
            throw ValidationException::withMessages([
                'guardian_name' => 'El representante es obligatorio para participantes menores.',
                'guardian_relationship' => 'La relación con el representante es obligatoria para participantes menores.',
            ]);
        }

        return [
            'participant_name' => $attributes['participant_name'],
            'participant_birth_date' => $birthDate->toDateString(),
            'contact_phone' => $attributes['contact_phone'],
            'contact_email' => $attributes['contact_email'],
            'guardian_name' => $isMinor
                ? ($attributes['guardian_name'] ?? null)
                : null,
            'guardian_relationship' => $isMinor
                ? ($attributes['guardian_relationship'] ?? null)
                : null,
        ];
    }

    private function resolveLevel(
        SchoolProgram $program,
        mixed $levelId,
        bool $requireLevel,
        bool $requirePublic
    ): ?SchoolLevel {
        if ($levelId === null || $levelId === '') {
            if ($requireLevel) {
                throw ValidationException::withMessages([
                    'school_level_id' => self::ADMIN_LEVEL_ERROR,
                ]);
            }

            return null;
        }

        $query = SchoolLevel::query()
            ->whereKey((int) $levelId)
            ->where('school_program_id', $program->id)
            ->where('is_active', true);

        if ($requirePublic) {
            $query->effectivelyPublic();
        }

        $level = $query->lockForUpdate()->first();

        if ($level === null) {
            throw ValidationException::withMessages([
                'school_level_id' => $requirePublic
                    ? self::PUBLIC_LEVEL_ERROR
                    : self::ADMIN_LEVEL_ERROR,
            ]);
        }

        return $level;
    }

    private function assertStatus(
        SchoolEnrollment $enrollment,
        SchoolEnrollmentStatus $expected
    ): void {
        if ($enrollment->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => 'La acción no es válida para el estado actual de la inscripción.',
            ]);
        }
    }

    private function lockEnrollment(
        SchoolEnrollment $enrollment
    ): SchoolEnrollment {
        return SchoolEnrollment::query()
            ->with('program')
            ->lockForUpdate()
            ->findOrFail($enrollment->id);
    }

    private function unformalizedRetentionMonths(): int
    {
        return max(1, (int) config('school.retention.unformalized_months', 6));
    }

    private function studentRetentionYears(): int
    {
        return max(1, (int) config('school.retention.student_years', 2));
    }
}
