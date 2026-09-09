<?php

namespace App\Services\Media\Backfill\Safety;

use Illuminate\Contracts\Foundation\Application;

class ApplyMaintenanceGuard
{
    public function __construct(private readonly Application $application) {}

    public function assertAllowed(): void
    {
        if (! $this->application->environment('testing') && ! $this->application->isDownForMaintenance()) {
            throw new BackfillSafetyException(SafetyError::MaintenanceRequired);
        }
    }
}
