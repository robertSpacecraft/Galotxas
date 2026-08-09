<?php

namespace App\Services;

use App\Models\PublicIdentityAuthorization;
use RuntimeException;

class PublicIdentityNoticeService
{
    public const ID = 'NOTICE-PUBLIC-IDENTITY-MINORS';

    /** @var array<string, mixed>|null */
    private ?array $notice = null;

    /** @return array<string, mixed> */
    public function current(): array
    {
        if ($this->notice !== null) {
            return $this->notice;
        }

        $path = resource_path('generated/legal/form-notices.json');
        $contents = @file_get_contents($path);
        $artifact = is_string($contents) ? json_decode($contents, true) : null;
        $notices = $artifact['notices'] ?? null;
        $notice = is_array($notices)
            ? collect($notices)->firstWhere('id', self::ID)
            : null;

        if (
            ! is_array($notice)
            || ($artifact['schemaVersion'] ?? null) !== 1
            || count($notices ?? []) !== 3
            || ($notice['id'] ?? null) !== self::ID
            || ($notice['status'] ?? null) !== 'vigente'
            || ($notice['scope'] ?? null) !== PublicIdentityAuthorization::SCOPE
            || ! is_string($notice['version'] ?? null)
        ) {
            throw new RuntimeException('La proyección legal de autorización no es válida.');
        }

        return $this->notice = $notice;
    }

    public function recognizes(string $noticeId, string $version, string $scope): bool
    {
        $notice = $this->current();

        return hash_equals((string) $notice['id'], $noticeId)
            && hash_equals((string) $notice['version'], $version)
            && hash_equals((string) $notice['scope'], $scope);
    }
}
