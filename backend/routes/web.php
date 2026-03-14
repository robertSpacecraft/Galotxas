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

        Route::get('/championships', [AdminChampionshipController::class, 'index'])->name('admin.championships.index');
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('admin.categories.index');
    });
});
