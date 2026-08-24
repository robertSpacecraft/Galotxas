<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_is_minimal_public_and_does_not_disclose_runtime_details(): void
    {
        $response = $this->get('/up');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->assertSeeText('OK');

        $this->assertSame("OK\n", $response->getContent());
        $this->assertStringNotContainsString('Laravel', $response->getContent());
        $this->assertStringNotContainsString('MariaDB', $response->getContent());
        $this->assertStringNotContainsString('APP_', $response->getContent());
    }

    public function test_cors_allows_only_the_exact_configured_origin_for_bearer_api(): void
    {
        config()->set('cors.allowed_origins', ['https://galotxesmonover.es']);
        config()->set('cors.allowed_origins_patterns', []);
        config()->set('cors.supports_credentials', false);

        $this->withHeader('Origin', 'https://galotxesmonover.es')
            ->getJson('/api/v1/seasons')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://galotxesmonover.es');

        $this->flushHeaders();

        $this->withHeader('Origin', 'https://evil.example')
            ->getJson('/api/v1/seasons')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://galotxesmonover.es')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_deploy_check_passes_a_closed_valid_production_configuration(): void
    {
        $this->configureValidProduction();

        $exitCode = Artisan::call('deploy:check');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Preflight backend válido', $output);
        $this->assertStringContainsString('Correo saliente', $output);
        $this->assertStringNotContainsString((string) config('app.key'), $output);
        $this->assertStringNotContainsString('database-secret', $output);
        $this->assertStringNotContainsString('re_test_deployment_secret', $output);
    }

    public function test_resend_transport_is_loadable_without_sending_a_real_message(): void
    {
        config()->set('services.resend.key', 're_test_transport_secret');
        Mail::forgetMailers();

        $transport = Mail::mailer('resend')->getSymfonyTransport();

        $this->assertInstanceOf(ResendTransport::class, $transport);
    }

    public function test_deploy_check_fails_closed_for_unsafe_urls_debug_cors_or_flags(): void
    {
        $this->configureValidProduction();
        config()->set('app.debug', true);
        config()->set('app.url', 'http://localhost:8080');
        config()->set('cors.allowed_origins', ['*']);
        config()->set('contact.form_enabled', true);

        $exitCode = Artisan::call('deploy:check');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('BLOQUEO', $output);
        $this->assertStringContainsString('Preflight bloqueado', $output);
    }

    public function test_deploy_check_accepts_array_as_the_safe_staging_mail_baseline(): void
    {
        $this->configureValidStaging();

        $exitCode = Artisan::call('deploy:check');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Correo saliente', $output);
        $this->assertStringContainsString('Preflight backend válido', $output);
    }

    public function test_deploy_check_rejects_log_as_the_staging_mail_baseline(): void
    {
        $this->configureValidStaging();
        config()->set('mail.default', 'log');

        $exitCode = Artisan::call('deploy:check');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Correo saliente', Artisan::output());
    }

    public function test_deploy_check_requires_a_complete_resend_gate_in_staging(): void
    {
        $this->configureValidStaging();
        config()->set('mail.default', 'resend');

        $exitCode = Artisan::call('deploy:check');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Correo saliente', Artisan::output());

        config()->set('services.resend.key', 're_test_staging_secret');
        config()->set('mail.from.address', 'staging@galotxesmonover.es');

        $exitCode = Artisan::call('deploy:check');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringNotContainsString('re_test_staging_secret', $output);
    }

    public function test_deploy_check_requires_resend_in_production_with_closed_optional_features(): void
    {
        $this->configureValidProduction();
        config()->set('mail.default', 'array');

        $exitCode = Artisan::call('deploy:check');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Correo saliente', Artisan::output());
    }

    public function test_deploy_check_keeps_live_feature_dependencies_fail_closed(): void
    {
        $this->configureValidProduction();
        config()->set('public_identity.authorization_enabled', true);

        $exitCode = Artisan::call('deploy:check', ['--allow-live-features' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Flujo de identidad de menores', Artisan::output());

        config()->set('public_identity.notification_enabled', true);
        config()->set('services.resend.key', '');

        $exitCode = Artisan::call('deploy:check', ['--allow-live-features' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Correo saliente', Artisan::output());
    }

    public function test_platform_contract_uses_a_single_railway_service_without_startup_migrations(): void
    {
        $railway = json_decode(
            (string) file_get_contents(base_path('railway.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $startScript = (string) file_get_contents(base_path('docker/production/start.sh'));
        $dockerfile = (string) file_get_contents(base_path('Dockerfile'));
        $phpConfiguration = (string) file_get_contents(base_path('docker/production/php.ini'));
        $developmentNginx = (string) file_get_contents(base_path('docker/app/nginx/default.conf'));
        $productionNginx = (string) file_get_contents(
            base_path('docker/production/nginx.conf.template')
        );

        $this->assertSame('DOCKERFILE', $railway['build']['builder']);
        $this->assertSame('/up', $railway['deploy']['healthcheckPath']);
        $this->assertStringContainsString('${PORT}', $startScript);
        $this->assertStringContainsString('config:cache', $startScript);
        $this->assertStringContainsString('route:cache', $startScript);
        $this->assertStringContainsString('view:cache', $startScript);
        $this->assertStringNotContainsString('artisan migrate', $startScript);
        $this->assertStringNotContainsString('artisan migrate', $dockerfile);
        $this->assertStringContainsString('expose_php=Off', $phpConfiguration);
        $this->assertStringContainsString('display_errors=Off', $phpConfiguration);
        $this->assertStringContainsString('fastcgi_buffer_size 16k', $developmentNginx);
        $this->assertStringContainsString('fastcgi_buffer_size 16k', $productionNginx);
    }

    private function configureValidProduction(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        config()->set('app.key', 'base64:'.base64_encode('0123456789abcdef0123456789abcdef'));
        config()->set('app.url', 'https://api.galotxesmonover.es');
        config()->set('app.frontend_url', 'https://galotxesmonover.es');
        config()->set('database.default', 'mariadb');
        config()->set('database.connections.mariadb.driver', 'mariadb');
        config()->set('database.connections.mariadb.password', 'database-secret');
        config()->set('cors.allowed_origins', ['https://galotxesmonover.es']);
        config()->set('cors.allowed_origins_patterns', []);
        config()->set('cors.supports_credentials', false);
        config()->set('session.driver', 'database');
        config()->set('session.secure', true);
        config()->set('session.http_only', true);
        config()->set('session.same_site', 'lax');
        config()->set('cache.default', 'database');
        config()->set('queue.default', 'sync');
        config()->set('logging.default', 'stderr');
        config()->set('deployment.trusted_proxies', '*');
        config()->set('filesystems.default', 'local');
        config()->set('contact.form_enabled', false);
        config()->set('contact.notification.enabled', false);
        config()->set('school.enrollment_enabled', false);
        config()->set('public_identity.authorization_enabled', false);
        config()->set('public_identity.notification_enabled', false);
        config()->set('deployment.scheduler_enabled', false);
        config()->set('mail.default', 'resend');
        config()->set('services.resend.key', 're_test_deployment_secret');
        config()->set('mail.from.address', 'notificaciones@galotxesmonover.es');
        config()->set('mail.from.name', 'Club Galotxes Monòver');
    }

    private function configureValidStaging(): void
    {
        $this->configureValidProduction();

        config()->set('app.env', 'staging');
        config()->set('app.url', 'https://api-staging.galotxesmonover.es');
        config()->set('app.frontend_url', 'https://staging.galotxesmonover.es');
        config()->set('cors.allowed_origins', ['https://staging.galotxesmonover.es']);
        config()->set('mail.default', 'array');
        config()->set('services.resend.key', '');
        config()->set('mail.from.address', 'no-reply@staging.invalid');
        config()->set('mail.from.name', 'Club Galotxes Monòver (staging)');
    }
}
