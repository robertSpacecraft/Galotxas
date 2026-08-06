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
        private readonly PublicIdentityAuthorizationNotificationService $notificationService
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
                ->where('enrollments_open', true)
                ->lockForUpdate()
                ->first();

            if ($program === null) {
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
        array $attributes
    ): SchoolEnrollment {
        return DB::transaction(function () use (
            $enrollment,
            $attributes
        ): SchoolEnrollment {
            $locked = $this->lockEnrollment($enrollment);
            $participant = $this->normalizedParticipantAttributes(
                $attributes,
                CarbonImmutable::instance($locked->requested_at)
            );

            $locked->forceFill([
                ...$participant,
                'admin_notes' => $attributes['admin_notes'] ?? null,
            ])->save();

            return $locked->refresh();
        });
    }

    public function approve(
        SchoolEnrollment $enrollment,
        int $levelId
    ): SchoolEnrollment {
        return DB::transaction(function () use (
            $enrollment,
            $levelId
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
                'rejected_at' => null,
                'withdrawn_at' => null,
            ])->save();

            return $locked->refresh();
        });
    }

    public function reject(SchoolEnrollment $enrollment): SchoolEnrollment
    {
        return DB::transaction(function () use ($enrollment): SchoolEnrollment {
            $locked = $this->lockEnrollment($enrollment);
            $this->assertStatus($locked, SchoolEnrollmentStatus::PENDING);

            $locked->forceFill([
                'status' => SchoolEnrollmentStatus::REJECTED,
                'activated_at' => null,
                'rejected_at' => CarbonImmutable::now(),
                'withdrawn_at' => null,
            ])->save();

            return $locked->refresh();
        });
    }

    public function withdraw(SchoolEnrollment $enrollment): SchoolEnrollment
    {
        return DB::transaction(function () use ($enrollment): SchoolEnrollment {
            $locked = $this->lockEnrollment($enrollment);
            $this->assertStatus($locked, SchoolEnrollmentStatus::ACTIVE);

            $locked->forceFill([
                'status' => SchoolEnrollmentStatus::WITHDRAWN,
                'rejected_at' => null,
                'withdrawn_at' => CarbonImmutable::now(),
            ])->save();

            return $locked->refresh();
        });
    }

    public function reassignLevel(
        SchoolEnrollment $enrollment,
        int $levelId
    ): SchoolEnrollment {
        return DB::transaction(function () use (
            $enrollment,
            $levelId
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
            'admin_notes' => $includeAdminNotes
                ? ($attributes['admin_notes'] ?? null)
                : null,
            'privacy_notice_version' => $includeAdminNotes
                ? ($attributes['privacy_notice_version'] ?? null)
                : $attributes['privacy_notice_version'],
            'privacy_acknowledged_at' => $includeAdminNotes
                ? null
                : $requestedAt,
        ]);
        $enrollment->save();

        return $enrollment->refresh();
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
}
