<?php

use App\Http\Controllers\Admin\CategoryRegistrationController;
use App\Http\Controllers\Admin\PlayerController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SeasonController as AdminSeasonController;
use App\Http\Controllers\Admin\ChampionshipController as AdminChampionshipController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\RankingController as AdminRankingController;
use App\Http\Controllers\Admin\ChampionshipRegistrationController as AdminChampionshipRegistrationController;

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

        Route::get('/seasons/{season}', [AdminSeasonController::class, 'show'])
            ->name('admin.seasons.show');

        //Campeonatos
        Route::get('/championships', [AdminChampionshipController::class, 'index'])->name('admin.championships.index');
        Route::get('/championships/{championship}', [AdminChampionshipController::class, 'show'])
            ->name('admin.championships.show');
        Route::get('/championships/{championship}/edit', [AdminChampionshipController::class, 'edit'])->name('admin.championships.edit');
        Route::put('/championships/{championship}', [AdminChampionshipController::class, 'update'])->name('admin.championships.update');
        Route::delete('/championships/{championship}', [AdminChampionshipController::class, 'destroy'])->name('admin.championships.destroy');

        //Campeonatos (aprobación)
        Route::post(
            '/championships/{championship}/registration-requests/approve-all',
            [AdminChampionshipRegistrationController::class, 'approveAllPending']
        )->name('admin.championships.registration-requests.approve-all');

        Route::post(
            '/championships/{championship}/registration-requests/{registrationRequest}/approve',
            [AdminChampionshipRegistrationController::class, 'approve']
        )->name('admin.championships.registration-requests.approve');

        Route::post(
            '/championships/{championship}/registration-requests/{registrationRequest}/reject',
            [AdminChampionshipRegistrationController::class, 'reject']
        )->name('admin.championships.registration-requests.reject');

        Route::post(
            '/championships/{championship}/registration-requests/{registrationRequest}/payment-status',
            [AdminChampionshipRegistrationController::class, 'updatePaymentStatus']
        )->name('admin.championships.registration-requests.update-payment-status');

        //Categorías
        Route::get('/championships/{championship}/categories', [AdminCategoryController::class, 'index'])
            ->name('admin.championships.categories');
        Route::get('/championships/{championship}/categories/create', [AdminCategoryController::class, 'create'])
            ->name('admin.categories.create');
        Route::post('/championships/{championship}/categories', [AdminCategoryController::class, 'store'])
            ->name('admin.categories.store');




        Route::get('/categories/{category}', [AdminCategoryController::class, 'show'])
            ->name('admin.categories.show');
        Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])
            ->name('admin.categories.edit');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])
            ->name('admin.categories.update');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])
            ->name('admin.categories.destroy');

        Route::post('categories/{category}/registrations', [CategoryRegistrationController::class, 'store'])
            ->name('admin.categories.registrations.store');
        Route::delete('categories/{category}/registrations/{registration}', [CategoryRegistrationController::class, 'destroy'])
            ->name('admin.categories.registrations.destroy');

        Route::post('/categories/{category}/teams', [\App\Http\Controllers\Admin\CategoryTeamController::class, 'store'])
            ->name('admin.categories.teams.store');
        Route::delete('/categories/{category}/teams/{team}', [\App\Http\Controllers\Admin\CategoryTeamController::class, 'destroy'])
            ->name('admin.categories.teams.destroy');

        Route::post('/categories/{category}/generate-league', [AdminCategoryController::class, 'generateLeague'])
            ->name('admin.categories.generate-league');

        Route::patch('/categories/{category}/matches/{match}', [\App\Http\Controllers\Admin\GameMatchController::class, 'update'])
            ->name('admin.categories.matches.update');

        Route::post('/categories/{category}/generate-cup', [AdminCategoryController::class, 'generateCup'])
            ->name('admin.categories.generate-cup');

        Route::delete('/categories/{category}/cup', [AdminCategoryController::class, 'deleteCup'])
            ->name('admin.categories.delete-cup');
        Route::post('/categories/{category}/generate-finals', [AdminCategoryController::class, 'generateFinals'])
            ->name('admin.categories.generate-finals');

        //Ranking
        Route::get('/rankings/history', [AdminRankingController::class, 'historical'])
            ->name('admin.rankings.history');

        //Jugadores
        Route::get('/players', [PlayerController::class, 'index'])
            ->name('admin.players.index');
        Route::get('/players/create', [PlayerController::class, 'create'])
            ->name('admin.players.create');
        Route::get('/players/{player}', [PlayerController::class, 'show'])
            ->name('admin.players.show');
        Route::post('/players', [PlayerController::class, 'store'])
            ->name('admin.players.store');
        Route::get('/players/{player}/edit', [PlayerController::class, 'edit'])
            ->name('admin.players.edit');
        Route::put('/players/{player}', [PlayerController::class, 'update'])
            ->name('admin.players.update');
        Route::delete('/players/{player}', [PlayerController::class, 'destroy'])
            ->name('admin.players.destroy');

        //Usuarios
        Route::get('/users', [UserController::class, 'index'])
            ->name('admin.users.index');
        Route::get('/users/create', [UserController::class, 'create'])
            ->name('admin.users.create');
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->name('admin.users.show');
        Route::post('/users', [UserController::class, 'store'])
            ->name('admin.users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])
            ->name('admin.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->name('admin.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->name('admin.users.destroy');
    });
});
