<?php

namespace App\Services\Media\Backfill\Safety;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Database\DatabaseManager;

final readonly class StorageIdentity
{
    private function __construct(public string $hash) {}

    public static function current(DatabaseManager $database): self
    {
        $disk = config('media.disk');

        return self::fromConfiguration($database->connection()->getConfig(), $disk,
            config('filesystems.disks.'.$disk, []), app()->environment());
    }

    public static function fromConfiguration(array $database, string $disk, array $storage, string $environment): self
    {
        try {
            $database = (new ConfigurationUrlParser)->parseConfiguration($database);
        } catch (\Throwable) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
        if (($database['driver'] ?? null) !== 'mariadb' || isset($database['read']) || isset($database['write'])
            || ! is_string($database['host'] ?? null)) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
        $driver = $storage['driver'] ?? null;
        if (! (($disk === 'media_local' && $driver === 'local') || ($disk === 'media_s3' && $driver === 's3'))
            || ! empty($storage['prefix']) || ($driver === 's3' && ! empty($storage['root']))) {
            throw new BackfillSafetyException(SafetyError::UnsupportedStorage);
        }
        $root = $driver === 'local' ? realpath($storage['root'] ?? '') : null;
        if ($driver === 'local' && ($root === false || ! is_dir($root))) {
            throw new BackfillSafetyException(SafetyError::UnsupportedStorage);
        }
        $endpoint = $storage['endpoint'] ?? null;
        $parts = $endpoint ? parse_url($endpoint) : [];
        if ($parts === false || ($endpoint && ! isset($parts['host']))) {
            throw new BackfillSafetyException(SafetyError::UnsupportedStorage);
        }
        // Fixed field order, explicit allowlist; URL user/password/query/fragment never enter the hash.
        $values = [
            'v' => 1, 'environment' => $environment, 'db_driver' => 'mariadb',
            'db_host' => strtolower(trim($database['host'])), 'db_port' => (string) ($database['port'] ?? 3306),
            'db_database' => $database['database'] ?? '', 'db_socket' => $database['unix_socket'] ?? '',
            'db_prefix' => $database['prefix'] ?? '', 'disk' => $disk, 'driver' => $driver,
            'root' => $root, 'bucket' => $driver === 's3' ? ($storage['bucket'] ?? '') : null,
            'region' => $driver === 's3' ? ($storage['region'] ?? '') : null,
            'endpoint' => $driver === 's3' ? [strtolower($parts['scheme'] ?? ''), strtolower($parts['host'] ?? ''),
                $parts['port'] ?? null, rtrim($parts['path'] ?? '', '/')] : null,
            'path_style' => $driver === 's3' ? (bool) ($storage['use_path_style_endpoint'] ?? false) : null,
        ];

        return new self(hash('sha256', json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
    }

    public function lockName(): string
    {
        return 'galotxas:media:bf:'.substr($this->hash, 0, 40);
    }
}
