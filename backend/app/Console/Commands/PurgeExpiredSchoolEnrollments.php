<?php

namespace App\Console\Commands;

use App\Enums\SchoolEnrollmentStatus;
use App\Models\SchoolEnrollment;
use App\Services\SchoolEnrollmentService;
use Illuminate\Console\Command;

class PurgeExpiredSchoolEnrollments extends Command
{
    protected $signature = 'school:purge-expired {--dry-run : Cuenta sin modificar datos}';

    protected $description = 'Anonimiza inscripciones de Escuela cuya retención ha vencido';

    public function handle(SchoolEnrollmentService $service): int
    {
        $query = SchoolEnrollment::query()
            ->whereIn('status', [
                SchoolEnrollmentStatus::PENDING->value,
                SchoolEnrollmentStatus::REJECTED->value,
                SchoolEnrollmentStatus::WITHDRAWN->value,
            ])
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now())
            ->where('retention_hold', false)
            ->whereNull('anonymized_at');

        $count = (clone $query)->count();
        if ($this->option('dry-run')) {
            $this->info("Inscripciones elegibles: {$count}. Sin cambios.");

            return self::SUCCESS;
        }

        $processed = 0;
        $query->orderBy('id')->chunkById(100, function ($enrollments) use (
            $service,
            &$processed
        ): void {
            foreach ($enrollments as $enrollment) {
                $service->anonymize($enrollment);
                $processed++;
            }
        });

        $this->info("Inscripciones anonimizadas: {$processed}.");

        return self::SUCCESS;
    }
}
