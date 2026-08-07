<?php

namespace App\Console\Commands;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Services\ContactRequestService;
use Illuminate\Console\Command;

class PurgeExpiredContactRequests extends Command
{
    protected $signature = 'contact:purge-expired {--dry-run : Cuenta sin modificar datos}';

    protected $description = 'Anonimiza solicitudes de contacto cerradas cuya retención ha vencido';

    public function handle(ContactRequestService $service): int
    {
        $query = ContactRequest::query()
            ->where('status', ContactRequestStatus::CLOSED->value)
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now())
            ->where('retention_hold', false)
            ->whereNull('anonymized_at');

        $count = (clone $query)->count();
        if ($this->option('dry-run')) {
            $this->info("Solicitudes elegibles: {$count}. Sin cambios.");

            return self::SUCCESS;
        }

        $processed = 0;
        $query->orderBy('id')->chunkById(100, function ($requests) use ($service, &$processed): void {
            foreach ($requests as $contactRequest) {
                $service->anonymize($contactRequest);
                $processed++;
            }
        });

        $this->info("Solicitudes anonimizadas: {$processed}.");

        return self::SUCCESS;
    }
}
