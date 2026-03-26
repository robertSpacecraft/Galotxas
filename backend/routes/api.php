<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SeasonController;
use App\Http\Controllers\Api\V1\ChampionshipController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MatchController;
use App\Http\Controllers\Api\V1\ChampionshipRegistrationController;

use App\Http\Controllers\Api\V1\Admin\SeasonController as AdminSeasonController;
use App\Http\Controllers\Api\V1\Admin\ChampionshipController as AdminChampionshipController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\MatchController as AdminMatchController;
use App\Http\Controllers\Api\V1\Admin\ChampionshipRegistrationController as AdminChampionshipRegistrationController;
use App\Http\Controllers\Api\V1\ChampionshipRankingController;
use App\Http\Controllers\Api\V1\AllTimeRankingController;
use App\Http\Controllers\Api\V1\SeasonRankingController;

Route::prefix('v1')->group(function () {
    //Auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    //Public API
    Route::get('/seasons', [SeasonController::class, 'index']);

    Route::get('/championships', [ChampionshipController::class, 'index']);
    Route::get('/championships/{championship}', [ChampionshipController::class, 'show']);
    Route::get('championships/{championship}/ranking', ChampionshipRankingController::class);

    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/categories/{category}/standings', [CategoryController::class, 'standings']);
    Route::get('/categories/{category}/schedule', [CategoryController::class, 'schedule']);

    Route::get('/matches/{gameMatch}', [MatchController::class, 'show']);

    Route::get('seasons/{season}/ranking', SeasonRankingController::class);
    Route::get('rankings/all-time', AllTimeRankingController::class);

    //Authenticated API
    Route::middleware('auth:sanctum')->group(function () {
        //Me
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/me/player-profile', [AuthController::class, 'myPlayerProfile']);
        Route::post('/me/player-profile', [AuthController::class, 'createMyPlayerProfile']);
        Route::patch('/me/player-profile', [AuthController::class, 'updateMyPlayerProfile']);

        Route::get('/me/matches', [MatchController::class, 'myMatches']);
        Route::get('/me/matches/pending-actions', [MatchController::class, 'pendingActions']);


        //Player match flow
        Route::get('/matches/{gameMatch}/workflow', [MatchController::class, 'workflow']);
        Route::post('/matches/{gameMatch}/submit-result', [MatchController::class, 'submitResult']);
        Route::post('/matches/{gameMatch}/confirm-result', [MatchController::class, 'confirmResult']);

        Route::get('/matches/{gameMatch}/reschedule-workflow', [MatchController::class, 'rescheduleWorkflow']);
        Route::post('/matches/{gameMatch}/request-reschedule', [MatchController::class, 'requestReschedule']);
        Route::post('/matches/{gameMatch}/confirm-reschedule', [MatchController::class, 'confirmReschedule']);


        //Championship registration requests (player)
        Route::get('/championships/{championship}/registration', [ChampionshipRegistrationController::class, 'show']);
        Route::post('/championships/{championship}/register', [ChampionshipRegistrationController::class, 'submit']);

        //Admin API
        Route::prefix('admin')
            ->middleware(\App\Http\Middleware\IsAdmin::class)
            ->group(function () {
                Route::apiResource('seasons', AdminSeasonController::class);
                Route::apiResource('championships', AdminChampionshipController::class);
                Route::apiResource('categories', AdminCategoryController::class);

                Route::post('/categories/{category}/entries', [AdminCategoryController::class, 'storeEntry']);

                //Match conflict management
                Route::get('/matches/under-review', [AdminMatchController::class, 'underReview']);
                Route::get('/matches/{gameMatch}/conflict', [AdminMatchController::class, 'showConflict']);
                Route::post('/matches/{gameMatch}/resolve-conflict', [AdminMatchController::class, 'resolveConflict']);
                Route::post('/matches/{gameMatch}/validate-result', [AdminMatchController::class, 'validateResult']);

                //Championship registration requests (admin)
                Route::get(
                    '/championships/{championship}/registration-requests',
                    [AdminChampionshipRegistrationController::class, 'index']
                );

                Route::patch(
                    '/championships/{championship}/registration-requests/{registrationRequest}/status',
                    [AdminChampionshipRegistrationController::class, 'updateStatus']
                );
            });
    });
});
