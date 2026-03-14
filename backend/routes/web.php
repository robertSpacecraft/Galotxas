<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SeasonController as AdminSeasonController;
use App\Http\Controllers\Admin\ChampionshipController as AdminChampionshipController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
        Route::post('/login', [AuthController::class, 'login'])->name('admin.login.submit');
    });

    Route::middleware(['auth', \App\Http\Middleware\IsAdmin::class])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

        //Temporadas
        Route::get('/seasons', [AdminSeasonController::class, 'index'])->name('admin.seasons.index');
        Route::get('/seasons/create', [AdminSeasonController::class, 'create'])->name('admin.seasons.create');
        Route::post('/seasons', [AdminSeasonController::class, 'store'])->name('admin.seasons.store');
        Route::get('/seasons/{season}/edit', [AdminSeasonController::class, 'edit'])->name('admin.seasons.edit');
        Route::put('/seasons/{season}', [AdminSeasonController::class, 'update'])->name('admin.seasons.update');
        Route::delete('/seasons/{season}', [AdminSeasonController::class, 'destroy'])->name('admin.seasons.destroy');

        Route::get('/seasons/{season}/championships', [AdminSeasonController::class, 'championships'])
            ->name('admin.seasons.championships');

        Route::get('/seasons/{season}/championships/create', [AdminChampionshipController::class, 'create'])->name('admin.championships.create');
        Route::post('/seasons/{season}/championships', [AdminChampionshipController::class, 'store'])->name('admin.championships.store');

        //Campeonatos
        Route::get('/championships', [AdminChampionshipController::class, 'index'])->name('admin.championships.index');
        Route::get('/championships/{championship}/edit', [AdminChampionshipController::class, 'edit'])->name('admin.championships.edit');
        Route::put('/championships/{championship}', [AdminChampionshipController::class, 'update'])->name('admin.championships.update');
        Route::delete('/championships/{championship}', [AdminChampionshipController::class, 'destroy'])->name('admin.championships.destroy');

        //Categorías
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('admin.categories.index');
        Route::get('/championships/{championship}/categories', [AdminCategoryController::class, 'index'])
            ->name('admin.championships.categories');
        Route::get('/championships/{championship}/categories/create', [AdminCategoryController::class, 'create'])
            ->name('admin.categories.create');
        Route::post('/championships/{championship}/categories', [AdminCategoryController::class, 'store'])
            ->name('admin.categories.store');
        Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])
            ->name('admin.categories.edit');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])
            ->name('admin.categories.update');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])
            ->name('admin.categories.destroy');
    });
});
