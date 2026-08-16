<?php

namespace App\Providers;

use App\Services\ContactRequestFingerprintService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth.login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth.register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('auth.password', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('match.results', function (Request $request) {
            $userKey = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(10)->by($userKey.'|'.$request->ip());
        });

        RateLimiter::for('profile-photo-mutations', function (Request $request) {
            $userKey = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(5)
                ->by($userKey.'|'.$request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Demasiados intentos. Inténtalo de nuevo más tarde.',
                    'data' => null,
                ], 429));
        });

        RateLimiter::for('school-enrollments', function (Request $request) {
            $key = hash_hmac(
                'sha256',
                $request->ip().'|'.Str::lower(trim((string) $request->input('contact_email'))),
                (string) config('app.key')
            );

            return Limit::perMinute(5)
                ->by($key)
                ->response(fn () => response()->json([
                    'message' => 'Demasiadas solicitudes. Inténtalo de nuevo más tarde.',
                    'data' => null,
                ], 429));
        });

        RateLimiter::for('contact-requests', function (Request $request) {
            $key = app(ContactRequestFingerprintService::class)->rateLimitKey(
                $request->ip(),
                $request->input('email')
            );

            return Limit::perMinutes(10, 5)
                ->by($key)
                ->response(fn () => response()->json([
                    'message' => 'Demasiadas solicitudes. Inténtalo de nuevo más tarde.',
                    'data' => null,
                ], 429));
        });

        RateLimiter::for('public-identity-token-lookup', function (Request $request) {
            return Limit::perMinutes(10, 10)
                ->by($this->publicIdentityRateLimitKey($request))
                ->response(fn () => response()->json([
                    'message' => 'Demasiados intentos. Inténtalo de nuevo más tarde.',
                    'data' => null,
                ], 429));
        });

        RateLimiter::for('public-identity-token-decision', function (Request $request) {
            return Limit::perMinutes(10, 5)
                ->by($this->publicIdentityRateLimitKey($request))
                ->response(fn () => response()->json([
                    'message' => 'Demasiados intentos. Inténtalo de nuevo más tarde.',
                    'data' => null,
                ], 429));
        });

        RateLimiter::for('public-identity-admin-resend', function (Request $request) {
            return Limit::perMinutes(10, 5)
                ->by((string) $request->user()?->getAuthIdentifier());
        });

        ResetPassword::createUrlUsing(function (object $user, string $token) {
            $frontendUrl = rtrim(config('app.frontend_url'), '/');

            return $frontendUrl.'/reset-password?token='.$token.'&email='.urlencode($user->email);
        });
    }

    private function publicIdentityRateLimitKey(Request $request): string
    {
        $key = (string) config('app.key');

        return hash_hmac(
            'sha256',
            $request->ip().'|'.(string) $request->input('token'),
            $key
        );
    }
}
