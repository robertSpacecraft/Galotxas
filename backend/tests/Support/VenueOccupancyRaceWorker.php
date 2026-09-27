<?php

declare(strict_types=1);

use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\User;
use App\Models\Venue;
use App\Services\GenerateLeagueScheduleService;
use App\Services\MatchRescheduleRequestService;
use App\Services\MatchResultService;
use App\Services\VenueDeletionService;
use App\Services\VenueOccupancyService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

[, $action, $payloadJson, $barrierDirectory, $label, $holdAfterAction] = $_SERVER['argv'];
$payload = json_decode($payloadJson, true, flags: JSON_THROW_ON_ERROR);
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
    $signal('before_action');

    $runAction = function () use (
        $action,
        $payload,
        $holdAfterAction,
        $signal,
        $wait,
        &$outcome,
    ): void {
        try {
            $outcome = match ($action) {
                'admin_update' => (function () use ($payload): array {
                    $match = GameMatch::query()->findOrFail((int) $payload['match_id']);
                    $updated = app(MatchResultService::class)->updateFromAdmin(
                        $match,
                        (int) $payload['category_id'],
                        CarbonImmutable::parse($payload['scheduled_date']),
                        (int) $payload['venue_id'],
                        $payload['status'],
                        null,
                        null,
                        User::query()->findOrFail((int) $payload['admin_id']),
                    );

                    return ['status' => 'ok', 'match_id' => $updated->id];
                })(),
                'confirm_reschedule' => (function () use ($payload): array {
                    $request = app(MatchRescheduleRequestService::class)->confirmRequest(
                        GameMatch::query()->findOrFail((int) $payload['match_id']),
                        User::query()->findOrFail((int) $payload['user_id']),
                    );

                    return ['status' => 'ok', 'request_id' => $request->id];
                })(),
                'generate_league' => (function () use ($payload): array {
                    app(GenerateLeagueScheduleService::class)->generate(
                        Championship::query()->findOrFail((int) $payload['championship_id'])
                    );

                    return ['status' => 'ok'];
                })(),
                'delete_venue' => [
                    'status' => 'ok',
                    'deleted' => app(VenueDeletionService::class)->deleteIfUnused(
                        Venue::query()->findOrFail((int) $payload['venue_id'])
                    ),
                ],
                'delete_venue_after_barrier' => (function () use ($payload, $signal, $wait): array {
                    $venue = Venue::query()
                        ->whereKey((int) $payload['venue_id'])
                        ->lockForUpdate()
                        ->firstOrFail();
                    $signal('venue_locked');
                    $wait('proceed');
                    $venue->delete();

                    return ['status' => 'ok', 'deleted' => true];
                })(),
                'lock_match' => [
                    'status' => 'ok',
                    'match_id' => GameMatch::query()
                        ->whereKey((int) $payload['match_id'])
                        ->lockForUpdate()
                        ->firstOrFail()
                        ->id,
                ],
                'lock_venues' => (function () use ($payload): array {
                    $ids = app(VenueOccupancyService::class)
                        ->lockVenues($payload['venue_ids'])
                        ->modelKeys();

                    return ['status' => 'ok', 'venue_ids' => $ids];
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
    };

    if ($action === 'delete_venue') {
        $runAction();
    } else {
        if ($action === 'generate_league') {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }
        DB::transaction($runAction);
    }
} catch (Throwable $exception) {
    $outcome = [
        'status' => 'harness_error',
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ];
}

fwrite(STDOUT, json_encode($outcome, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL);
