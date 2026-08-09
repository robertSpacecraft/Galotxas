<?php

namespace App\Services;

use RuntimeException;

class SchoolEnrollmentNoticeService
{
    public const ID = 'NOTICE-SCHOOL-ENROLLMENT';

    public const SCOPE = 'school_enrollment';

    public const PRIVACY_URL = '/legal/privacidad';

    /** @var array<string, mixed>|null */
    private ?array $notice = null;

    /** @return array<string, mixed> */
    public function current(): array
    {
        if ($this->notice !== null) {
            return $this->notice;
        }

        $contents = @file_get_contents(resource_path('generated/legal/form-notices.json'));
        $artifact = is_string($contents) ? json_decode($contents, true) : null;
        $notices = $artifact['notices'] ?? null;
        $notice = is_array($notices)
            ? collect($notices)->firstWhere('id', self::ID)
            : null;

        if (
            ! is_array($notice)
            || ($artifact['schemaVersion'] ?? null) !== 1
            || count($notices ?? []) !== 3
            || ($notice['status'] ?? null) !== 'vigente'
            || ($notice['scope'] ?? null) !== self::SCOPE
            || ($notice['privacyUrl'] ?? null) !== self::PRIVACY_URL
            || ! is_string($notice['version'] ?? null)
        ) {
            throw new RuntimeException('La proyección legal de inscripción de Escuela no es válida.');
        }

        return $this->notice = $notice;
    }

    public function recognizes(string $noticeId, string $version): bool
    {
        $notice = $this->current();

        return hash_equals((string) $notice['id'], $noticeId)
            && hash_equals((string) $notice['version'], $version);
    }
}
