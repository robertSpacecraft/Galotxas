<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SeasonController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MatchController;

use App\Http\Controllers\Api\V1\Admin\SeasonController as AdminSeasonController;
use App\Http\Controllers\Api\V1\Admin\ChampionshipController as AdminChampionshipController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;

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
        Route::post('/matches/{gameMatch}/submit-result', [MatchController::class, 'submitResult']);

        // Admin
        Route::prefix('admin')->middleware(\App\Http\Middleware\IsAdmin::class)->group(function () {
            Route::apiResource('seasons', AdminSeasonController::class);
            Route::apiResource('championships', AdminChampionshipController::class);
            Route::apiResource('categories', AdminCategoryController::class);
            Route::post('/categories/{category}/entries', [AdminCategoryController::class, 'storeEntry']);
            Route::post('/matches/{gameMatch}/validate-result', [App\Http\Controllers\Api\V1\Admin\MatchController::class, 'validateResult']);
        });
    });
});
