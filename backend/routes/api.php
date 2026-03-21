<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SeasonController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MatchController;

use App\Http\Controllers\Api\V1\Admin\SeasonController as AdminSeasonController;
use App\Http\Controllers\Api\V1\Admin\ChampionshipController as AdminChampionshipController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\MatchController as AdminMatchController;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Public reading
    Route::get('/seasons', [SeasonController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/categories/{category}/standings', [CategoryController::class, 'standings']);
    Route::get('/categories/{category}/schedule', [CategoryController::class, 'schedule']);
    Route::get('/matches/{gameMatch}', [MatchController::class, 'show']);

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);

        // Player match flow
        Route::post('/matches/{gameMatch}/submit-result', [MatchController::class, 'submitResult']);
        Route::get('/matches/{gameMatch}/workflow', [MatchController::class, 'workflow']);
        Route::post('/matches/{gameMatch}/confirm-result', [MatchController::class, 'confirmResult']);

        // Admin
        Route::prefix('admin')
            ->middleware(\App\Http\Middleware\IsAdmin::class)
            ->group(function () {
                Route::apiResource('seasons', AdminSeasonController::class);
                Route::apiResource('championships', AdminChampionshipController::class);
                Route::apiResource('categories', AdminCategoryController::class);

                Route::post('/categories/{category}/entries', [AdminCategoryController::class, 'storeEntry']);

                // Match conflict management
                Route::get('/matches/under-review', [AdminMatchController::class, 'underReview']);
                Route::get('/matches/{gameMatch}/conflict', [AdminMatchController::class, 'showConflict']);
                Route::post('/matches/{gameMatch}/resolve-conflict', [AdminMatchController::class, 'resolveConflict']);
                Route::post('/matches/{gameMatch}/validate-result', [AdminMatchController::class, 'validateResult']);
            });
    });
});
