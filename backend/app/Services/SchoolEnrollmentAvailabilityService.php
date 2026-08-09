<?php

namespace App\Services;

use App\Models\SchoolLevel;
use App\Models\SchoolLocation;
use App\Models\SchoolProgram;
use RuntimeException;
use Throwable;

class SchoolEnrollmentAvailabilityService
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        private readonly SchoolEnrollmentNoticeService $noticeService
    ) {}

    public function status(SchoolProgram $program): string
    {
        if ($this->readinessIssues($program) !== []) {
            return self::UNAVAILABLE;
        }

        if (
            ! (bool) config('school.enrollment_enabled', false)
            || ! $program->enrollments_open
        ) {
            return self::CLOSED;
        }

        return self::OPEN;
    }

    public function isOpen(SchoolProgram $program): bool
    {
        return $this->status($program) === self::OPEN;
    }

    /** @return array{status: string, enrollments_open: bool, privacy_notice: array<string, string>|null} */
    public function publicConfiguration(SchoolProgram $program): array
    {
        $status = $this->status($program);

        try {
            $notice = $this->noticeService->current();
            $privacyNotice = [
                'id' => (string) $notice['id'],
                'version' => (string) $notice['version'],
                'privacy_url' => (string) $notice['privacyUrl'],
            ];
        } catch (RuntimeException) {
            $privacyNotice = null;
        }

        return [
            'status' => $status,
            'enrollments_open' => $status === self::OPEN,
            'privacy_notice' => $privacyNotice,
        ];
    }

    /** @return list<string> */
    public function readinessIssues(SchoolProgram $program): array
    {
        $issues = [];

        if (! $program->is_public) {
            $issues[] = 'El programa debe ser público.';
        }

        if (blank($program->public_description)) {
            $issues[] = 'Falta la presentación pública de la Escuela.';
        }

        if (blank($program->enrollment_information)) {
            $issues[] = 'Falta la explicación pública del proceso de inscripción.';
        }

        if (! $this->hasActiveDefaultLocation($program)) {
            $issues[] = 'La ubicación habitual debe existir y estar activa.';
        }

        if (
            blank($program->contact_email)
            || filter_var($program->contact_email, FILTER_VALIDATE_EMAIL) === false
        ) {
            $issues[] = 'Falta un correo operativo privado válido para gestionar solicitudes.';
        }

        if (! $this->hasPublicOperationalLevel($program)) {
            $issues[] = 'Debe existir al menos un nivel público activo con horario y ubicación activos.';
        }

        try {
            $this->noticeService->current();
        } catch (Throwable) {
            $issues[] = 'El aviso vigente de privacidad de Escuela no está disponible.';
        }

        return $issues;
    }

    private function hasActiveDefaultLocation(SchoolProgram $program): bool
    {
        if ($program->default_school_location_id === null) {
            return false;
        }

        if ($program->relationLoaded('defaultLocation')) {
            return $program->defaultLocation?->is_active === true;
        }

        return SchoolLocation::query()
            ->whereKey($program->default_school_location_id)
            ->active()
            ->exists();
    }

    private function hasPublicOperationalLevel(SchoolProgram $program): bool
    {
        if (! $program->exists) {
            return false;
        }

        if ($program->relationLoaded('levels')) {
            return $program->levels->contains(
                fn (SchoolLevel $level): bool => $level->is_active
                    && $level->is_public
                    && $level->relationLoaded('schedules')
                    && $level->schedules->contains(
                        fn ($schedule): bool => $schedule->is_active
                            && $schedule->relationLoaded('location')
                            && $schedule->location?->is_active === true
                    )
            );
        }

        return SchoolLevel::query()
            ->where('school_program_id', $program->id)
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereHas('schedules', fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('location', fn ($locationQuery) => $locationQuery->active()))
            ->exists();
    }
}
