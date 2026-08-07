<?php

namespace App\Console\Commands;

use App\Models\ContactRequest;
use Illuminate\Console\Command;

class PurgeContactAbuseHashes extends Command
{
    protected $signature = 'contact:purge-abuse-hashes {--dry-run : Cuenta sin modificar datos}';

    protected $description = 'Elimina HMAC de IP de Contacto cuando vence su plazo operativo';

    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) config('contact.abuse_hash_retention_days', 30)));
        $query = ContactRequest::query()
            ->whereNotNull('ip_hash')
            ->where('retention_hold', false)
            ->where(function ($query) use ($cutoff): void {
                $query->where('ip_hash_expires_at', '<=', now())
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query->whereNull('ip_hash_expires_at')
                            ->where('created_at', '<=', $cutoff);
                    });
            });

        $count = (clone $query)->count();
        if ($this->option('dry-run')) {
            $this->info("Hashes elegibles: {$count}. Sin cambios.");

            return self::SUCCESS;
        }

        $processed = $query->update([
            'ip_hash' => null,
            'ip_hash_expires_at' => null,
        ]);
        $this->info("Hashes eliminados: {$processed}.");

        return self::SUCCESS;
    }
}
