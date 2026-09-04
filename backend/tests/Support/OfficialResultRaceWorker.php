<?php

declare(strict_types=1);

use App\Enums\GameMatchStatus;
use App\Models\GameMatch;
use App\Models\User;
use App\Services\MatchResultService;
use App\Services\OfficializeLeagueResultService;
use App\Services\OfficialResultLockService;
use App\Services\ReopenLeagueResultService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[
    ,
    $action,
    $categoryId,
    $matchId,
    $actorId,
    $barrierDirectory,
    $label,
    $waitBeforeAction,
    $holdAfterAction,
] = $_SERVER['argv'];

$marker = static function (string $name) use ($barrierDirectory, $label): string {
    return $barrierDirectory.'/'.$label.'.'.$name;
};

$signal = static function (string $name) use ($marker): void {
    $path = $marker($name);
    $temporary = $path.'.'.getmypid().'.tmp';
    file_put_contents($temporary, '1', LOCK_EX);
    rename($temporary, $path);
};

$wait = static function (string $name) use ($marker): void {
    $deadline = microtime(true) + 15;

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
        $action,
        $categoryId,
        $matchId,
        $actorId,
        $waitBeforeAction,
        $holdAfterAction,
        $signal,
        $wait,
        &$outcome,
    ): void {
        $signal('before_lock');
        app(OfficialResultLockService::class)
            ->lockCategoryAndCurrentOfficialResults((int) $categoryId);
        $signal('locked');

        if ($waitBeforeAction === '1') {
            $wait('proceed');
        }

        try {
            $actor = User::query()->findOrFail((int) $actorId);
            $result = match ($action) {
                'officialize' => app(OfficializeLeagueResultService::class)
                    ->officialize((int) $categoryId, $actor),
                'reopen' => app(ReopenLeagueResultService::class)
                    ->reopen((int) $categoryId, $actor, 'Reapertura concurrente'),
                'writer' => (function () use ($matchId, $categoryId, $actor) {
                    $match = GameMatch::query()->findOrFail((int) $matchId);

                    return app(MatchResultService::class)->updateFromAdmin(
                        $match,
                        (int) $categoryId,
                        CarbonImmutable::now()->addDay(),
                        (int) $match->venue_id,
                        GameMatchStatus::VALIDATED->value,
                        10,
                        8,
                        $actor,
                    );
                })(),
                default => throw new InvalidArgumentException("Acción de carrera desconocida: {$action}"),
            };

            $outcome = [
                'status' => 'ok',
                'action' => $action,
                'id' => $result->id,
                'version' => $result->version ?? null,
            ];
        } catch (Throwable $exception) {
            $outcome = [
                'status' => 'exception',
                'action' => $action,
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
        'action' => $action,
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ];
}

fwrite(STDOUT, json_encode($outcome, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL);
