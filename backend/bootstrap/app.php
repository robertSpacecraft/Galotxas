<?php

use App\Exceptions\OfficialResultHistoryDeletionBlockedException;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Http\Controllers\LivenessController;
use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$trustedProxiesValue = trim((string) env('TRUSTED_PROXIES', ''));
$trustedProxies = in_array($trustedProxiesValue, ['*', '**'], true)
    ? $trustedProxiesValue
    : array_values(array_filter(array_map(
        static fn (string $proxy): string => trim($proxy),
        explode(',', $trustedProxiesValue)
    )));

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: static function (): void {
            Route::get('/up', LivenessController::class)
                ->name('liveness');
        },
    )
    ->withMiddleware(function (Middleware $middleware) use ($trustedProxies): void {
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->append(AddSecurityHeaders::class);
        $middleware->preventRequestsDuringMaintenance(except: ['up']);

        if ($trustedProxies !== '' && $trustedProxies !== []) {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_TRAEFIK,
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (
            OfficialResultMutationBlockedException|OfficialResultHistoryDeletionBlockedException $exception,
            Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'data' => null,
                ], 409);
            }

            return back()->with('error', $exception->getMessage());
        });
    })->create();
