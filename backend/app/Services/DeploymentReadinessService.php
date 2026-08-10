<?php

namespace App\Services;

use App\Models\SchoolProgram;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeploymentReadinessService
{
    public const PRODUCTION_FRONTEND_URL = 'https://galotxesmonover.es';

    public const PRODUCTION_API_URL = 'https://api.galotxesmonover.es';

    /**
     * @return list<array{name: string, passed: bool, detail: string}>
     */
    public function check(bool $allowLiveFeatures = false): array
    {
        $checks = [];
        $environment = (string) config('app.env');
        $isProduction = $environment === 'production';

        $this->add(
            $checks,
            'Entorno',
            in_array($environment, ['staging', 'production'], true),
            'Debe ser staging o production.'
        );
        $this->add(
            $checks,
            'Modo debug',
            config('app.debug') === false,
            'APP_DEBUG debe ser false.'
        );
        $this->add(
            $checks,
            'Clave de aplicación',
            $this->hasValidApplicationKey(),
            'APP_KEY debe ser una clave AES-256 válida y no trivial.'
        );

        $appUrl = rtrim((string) config('app.url'), '/');
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        $this->add(
            $checks,
            'URL del backend',
            $this->isDeploymentUrl($appUrl)
                && (! $isProduction || $appUrl === self::PRODUCTION_API_URL),
            'Debe ser HTTPS, no local y coincidir con la API canónica en producción.'
        );
        $this->add(
            $checks,
            'URL del frontend',
            $this->isDeploymentUrl($frontendUrl)
                && (! $isProduction || $frontendUrl === self::PRODUCTION_FRONTEND_URL),
            'Debe ser HTTPS, no local y coincidir con el origen canónico en producción.'
        );

        $this->checkDatabase($checks);
        $this->checkCors($checks, $frontendUrl);

        $this->add(
            $checks,
            'Sesión segura',
            config('session.secure') === true
                && config('session.http_only') === true
                && in_array(config('session.same_site'), ['lax', 'strict'], true),
            'La cookie Blade debe ser Secure, HttpOnly y SameSite lax o strict.'
        );
        $this->add(
            $checks,
            'Persistencia de sesión',
            config('session.driver') === 'database',
            'SESSION_DRIVER debe ser database.'
        );
        $this->add(
            $checks,
            'Caché',
            config('cache.default') === 'database',
            'CACHE_STORE debe ser database.'
        );
        $this->add(
            $checks,
            'Cola',
            config('queue.default') === 'sync',
            'QUEUE_CONNECTION debe ser sync mientras no exista un worker supervisado.'
        );
        $this->add(
            $checks,
            'Logs',
            config('logging.default') === 'stderr',
            'LOG_CHANNEL debe ser stderr.'
        );
        $trustedProxies = config('deployment.trusted_proxies');
        $hasTrustedProxies = in_array($trustedProxies, ['*', '**'], true)
            || (is_array($trustedProxies)
                && $trustedProxies !== []
                && ! in_array('', $trustedProxies, true));
        $this->add(
            $checks,
            'Proxy inverso',
            $hasTrustedProxies,
            'TRUSTED_PROXIES debe declarar explícitamente el proxy de la plataforma.'
        );
        $this->add(
            $checks,
            'Filesystem',
            config('filesystems.default') === 'local'
                && is_writable(storage_path())
                && is_writable(base_path('bootstrap/cache')),
            'El disco debe ser local y storage/bootstrap/cache deben ser escribibles.'
        );

        $this->checkFeatureFlags($checks, $allowLiveFeatures);

        return $checks;
    }

    /** @param list<array{name: string, passed: bool, detail: string}> $checks */
    private function checkDatabase(array &$checks): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        $this->add(
            $checks,
            'Motor de base de datos',
            $connection === 'mariadb' && $driver === 'mariadb',
            'DB_CONNECTION y su driver deben ser mariadb.'
        );

        try {
            DB::connection()->getPdo();
            $this->add($checks, 'Conexión de base de datos', true, 'Conexión disponible.');
        } catch (Throwable) {
            $this->add(
                $checks,
                'Conexión de base de datos',
                false,
                'No se pudo conectar con la base configurada.'
            );

            return;
        }

        try {
            /** @var Migrator $migrator */
            $migrator = app('migrator');
            $repository = $migrator->getRepository();

            if (! $repository->repositoryExists()) {
                $this->add(
                    $checks,
                    'Migraciones',
                    false,
                    'No existe el repositorio de migraciones.'
                );

                return;
            }

            $files = $migrator->getMigrationFiles([database_path('migrations')]);
            $pending = array_diff(array_keys($files), $repository->getRan());

            $this->add(
                $checks,
                'Migraciones',
                $pending === [],
                $pending === []
                    ? 'No hay migraciones pendientes.'
                    : sprintf('Hay %d migraciones pendientes.', count($pending))
            );
        } catch (Throwable) {
            $this->add(
                $checks,
                'Migraciones',
                false,
                'No se pudo consultar el estado de las migraciones.'
            );
        }
    }

    /** @param list<array{name: string, passed: bool, detail: string}> $checks */
    private function checkCors(array &$checks, string $frontendUrl): void
    {
        $origins = config('cors.allowed_origins');
        $valid = is_array($origins)
            && count($origins) === 1
            && $origins[0] === $frontendUrl
            && ! in_array('*', $origins, true)
            && config('cors.allowed_origins_patterns') === []
            && config('cors.supports_credentials') === false;

        $this->add(
            $checks,
            'CORS',
            $valid,
            'Debe admitir sólo el origen frontend exacto, sin wildcard ni cookies CORS.'
        );
    }

    /** @param list<array{name: string, passed: bool, detail: string}> $checks */
    private function checkFeatureFlags(array &$checks, bool $allowLiveFeatures): void
    {
        $contactEnabled = (bool) config('contact.form_enabled');
        $contactNotificationEnabled = (bool) config('contact.notification.enabled');
        $schoolEnabled = (bool) config('school.enrollment_enabled');
        $identityEnabled = (bool) config('public_identity.authorization_enabled');
        $identityNotificationEnabled = (bool) config('public_identity.notification_enabled');
        $schedulerEnabled = (bool) config('deployment.scheduler_enabled', false);

        if (! $allowLiveFeatures) {
            $this->add(
                $checks,
                'Funciones públicas iniciales',
                ! $contactEnabled
                    && ! $contactNotificationEnabled
                    && ! $schoolEnabled
                    && ! $identityEnabled
                    && ! $identityNotificationEnabled,
                'Contacto, Escuela, identidad de menores y sus notificaciones deben iniciar apagados.'
            );
        } else {
            $this->checkLiveFeatureDependencies(
                $checks,
                $contactEnabled,
                $schoolEnabled,
                $identityEnabled,
                $identityNotificationEnabled
            );
        }

        $this->add(
            $checks,
            'Scheduler',
            ! $schedulerEnabled,
            'Debe permanecer apagado hasta desplegar y validar una ejecución separada.'
        );

        if ($contactNotificationEnabled || $identityNotificationEnabled) {
            $this->checkMail($checks);
        }
    }

    /** @param list<array{name: string, passed: bool, detail: string}> $checks */
    private function checkLiveFeatureDependencies(
        array &$checks,
        bool $contactEnabled,
        bool $schoolEnabled,
        bool $identityEnabled,
        bool $identityNotificationEnabled
    ): void {
        if ($contactEnabled) {
            $this->add(
                $checks,
                'Contacto operativo',
                app(ContactFormAvailabilityService::class)->isEnabled(),
                'Contacto debe disponer de aviso, persistencia y destinatario válidos.'
            );
        }

        if ($schoolEnabled) {
            $program = SchoolProgram::query()->where('is_public', true)->first();
            $this->add(
                $checks,
                'Escuela operativa',
                $program !== null
                    && app(SchoolEnrollmentAvailabilityService::class)->isOpen($program),
                'Escuela debe resolver un programa público efectivamente abierto.'
            );
        }

        if ($identityEnabled) {
            try {
                app(PublicIdentityNoticeService::class)->current();
                $noticeAvailable = true;
            } catch (Throwable) {
                $noticeAvailable = false;
            }

            $this->add(
                $checks,
                'Identidad de menores',
                $noticeAvailable,
                'La autorización requiere su aviso vigente verificable.'
            );
            $this->add(
                $checks,
                'Flujo de identidad de menores',
                $identityNotificationEnabled,
                'La autorización no puede activarse sin su notificación operativa.'
            );
        }

        if ($identityNotificationEnabled) {
            $this->add(
                $checks,
                'Notificación de identidad',
                $identityEnabled,
                'La notificación no puede activarse sin la autorización.'
            );
        }
    }

    /** @param list<array{name: string, passed: bool, detail: string}> $checks */
    private function checkMail(array &$checks): void
    {
        $smtp = config('mail.mailers.smtp');
        $password = is_array($smtp) ? trim((string) ($smtp['password'] ?? '')) : '';
        $username = is_array($smtp) ? trim((string) ($smtp['username'] ?? '')) : '';

        $valid = config('mail.default') === 'smtp'
            && is_array($smtp)
            && ($smtp['host'] ?? null) === 'smtp.dondominio.com'
            && (int) ($smtp['port'] ?? 0) === 587
            && ($smtp['scheme'] ?? null) === 'smtp'
            && $username !== ''
            && $password !== ''
            && config('mail.from.address') === 'notificaciones@galotxesmonover.es'
            && config('contact.notification.to') === 'info@galotxesmonover.es'
            && config('contact.notification.reply_to_mode') === 'requester';

        $this->add(
            $checks,
            'Correo saliente',
            $valid,
            'SMTP debe estar completo y ajustado al contrato DonDominio aprobado.'
        );
    }

    private function hasValidApplicationKey(): bool
    {
        $configured = (string) config('app.key');
        $rawKey = str_starts_with($configured, 'base64:')
            ? base64_decode(substr($configured, 7), true)
            : $configured;

        if (! is_string($rawKey) || strlen($rawKey) !== 32) {
            return false;
        }

        return count(array_unique(str_split($rawKey))) > 1;
    }

    private function isDeploymentUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($value);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? null) === 'https'
            && ! isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            && (($parts['path'] ?? '') === '' || ($parts['path'] ?? '') === '/')
            && $host !== ''
            && $host !== 'localhost'
            && $host !== '0.0.0.0'
            && $host !== '::1'
            && ! str_starts_with($host, '127.')
            && ! str_ends_with($host, '.localhost')
            && ! str_ends_with($host, '.test')
            && ! str_ends_with($host, '.invalid')
            && $host !== 'example.com'
            && ! str_ends_with($host, '.example')
            && ! str_contains($host, 'placeholder')
            && ! str_contains($host, 'change-me');
    }

    /**
     * @param  list<array{name: string, passed: bool, detail: string}>  $checks
     */
    private function add(
        array &$checks,
        string $name,
        bool $passed,
        string $detail
    ): void {
        $checks[] = compact('name', 'passed', 'detail');
    }
}
