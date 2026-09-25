<?php

declare(strict_types=1);

use App\Exceptions\CategoryEntryIntegrityException;
use App\Services\CategoryEntryService;
use App\Services\OfficialResultLockService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

[
    ,
    $action,
    $categoryId,
    $payload,
    $token,
    $barrierDirectory,
    $label,
    $waitBeforeAction,
    $holdAfterAction,
] = $_SERVER['argv'];

$marker = static fn (string $name): string => $barrierDirectory.'/'.$label.'.'.$name;

$signal = static function (string $name) use ($marker): void {
    $path = $marker($name);
    $temporary = $path.'.'.getmypid().'.tmp';
    file_put_contents($temporary, '1', LOCK_EX);
    rename($temporary, $path);
};

$wait = static function (string $name) use ($marker): void {
    $deadline = microtime(true) + 20;

    while (! is_file($marker($name))) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException("Timeout esperando la barrera {$name}.");
        }

        usleep(10_000);
    }
};

$outcome = null;

try {
    $signal('started');

    DB::transaction(function () use (
        $app,
        $action,
        $categoryId,
        $payload,
        $token,
        $waitBeforeAction,
        $holdAfterAction,
        $signal,
        $wait,
        &$outcome,
    ): void {
        $signal('before_lock');

        if ($action === 'api_store_entry') {
            // Same category mutex as every participant writer; a second writer blocks here.
            app(OfficialResultLockService::class)->lockCategoryAndCurrentOfficialResults((int) $categoryId);
        }

        $signal('locked');

        if ($waitBeforeAction === '1') {
            $wait('proceed');
        }

        $signal('before_action');

        try {
            $outcome = match ($action) {
                'api_store_entry' => (function () use ($app, $categoryId, $payload, $token): array {
                    $response = $app->make(HttpKernel::class)->handle(Request::create(
                        "/api/v1/admin/categories/{$categoryId}/entries",
                        'POST',
                        [],
                        [],
                        [],
                        [
                            'HTTP_ACCEPT' => 'application/json',
                            'CONTENT_TYPE' => 'application/json',
                            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                        ],
                        $payload,
                    ));

                    return [
                        'status' => 'ok',
                        'http' => $response->getStatusCode(),
                        'body' => json_decode($response->getContent(), true),
                        'raw' => $response->getContent(),
                    ];
                })(),
                // A writer that skips the application checks and the category lock.
                'raw_insert' => (function () use ($categoryId, $payload): array {
                    $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

                    try {
                        DB::table('category_entries')->insert([
                            'category_id' => (int) $categoryId,
                            'entry_type' => $data['entry_type'],
                            'player_id' => $data['player_id'] ?? null,
                            'team_id' => $data['team_id'] ?? null,
                            'status' => 'approved',
                        ]);

                        return ['status' => 'ok'];
                    } catch (QueryException $exception) {
                        $translated = app(CategoryEntryService::class)->integrityViolationFrom($exception);

                        return [
                            'status' => 'exception',
                            'translated' => $translated instanceof CategoryEntryIntegrityException,
                            'message' => $translated?->getMessage(),
                            'field' => $translated?->field,
                            'driver_code' => $exception->errorInfo[1] ?? null,
                        ];
                    }
                })(),
                default => throw new InvalidArgumentException("Acción de carrera desconocida: {$action}"),
            };
        } catch (Throwable $exception) {
            $outcome = [
                'status' => 'exception',
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }

        $signal('acted');

        if ($holdAfterAction === '1') {
            $wait('release');
        }
    });
} catch (Throwable $exception) {
    $outcome = [
        'status' => 'harness_error',
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ];
}

fwrite(STDOUT, json_encode($outcome, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL);
