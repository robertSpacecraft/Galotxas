<?php

use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\ChampionshipController as AdminChampionshipController;
use App\Http\Controllers\Api\V1\Admin\ChampionshipRegistrationController as AdminChampionshipRegistrationController;
use App\Http\Controllers\Api\V1\Admin\MatchController as AdminMatchController;
use App\Http\Controllers\Api\V1\Admin\SeasonController as AdminSeasonController;
use App\Http\Controllers\Api\V1\AllTimeRankingController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ChampionshipController;
use App\Http\Controllers\Api\V1\ChampionshipRankingController;
use App\Http\Controllers\Api\V1\ChampionshipRegistrationController;
use App\Http\Controllers\Api\V1\CmsPageController;
use App\Http\Controllers\Api\V1\ContactConfigController;
use App\Http\Controllers\Api\V1\ContactRequestController;
use App\Http\Controllers\Api\V1\MatchController;
use App\Http\Controllers\Api\V1\MyChampionshipRegistrationController;
use App\Http\Controllers\Api\V1\MyDashboardController;
use App\Http\Controllers\Api\V1\ProfilePhotoController;
use App\Http\Controllers\Api\V1\ProfilePhotoImageController;
use App\Http\Controllers\Api\V1\PublicIdentityConfirmationController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SchoolEnrollmentController;
use App\Http\Controllers\Api\V1\SeasonController;
use App\Http\Controllers\Api\V1\SeasonRankingController;
use App\Http\Controllers\Api\V1\SponsorController;
use App\Http\Controllers\Api\V1\SponsorLogoController;
use App\Http\Middleware\EnsureContactFormIsEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\IsAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth.login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:auth.password');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:auth.password');

    // Public API
    Route::get('/seasons', [SeasonController::class, 'index']);

    Route::get('/championships', [ChampionshipController::class, 'index']);
    Route::get('/championships/{championship}', [ChampionshipController::class, 'show']);
    Route::get('/championships/{championship}/ranking', ChampionshipRankingController::class);

    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/categories/{category}/standings', [CategoryController::class, 'standings']);
    Route::get('/categories/{category}/schedule', [CategoryController::class, 'schedule']);

    Route::get('/matches/{gameMatch}', [MatchController::class, 'show']);

    Route::get('/cms/pages', [CmsPageController::class, 'index']);
    Route::get('/cms/pages/{slug}', [CmsPageController::class, 'show']);

    Route::get('/contact/config', ContactConfigController::class);
    Route::post('/contact-requests', [ContactRequestController::class, 'store'])
        ->middleware([
            EnsureContactFormIsEnabled::class,
            'throttle:contact-requests',
        ]);

    Route::get('/seasons/{season}/ranking', SeasonRankingController::class);
    Route::get('/rankings/all-time', AllTimeRankingController::class);

    Route::get('/school', SchoolController::class);
    Route::post('/school/enrollments', [SchoolEnrollmentController::class, 'store'])
        ->middleware('throttle:school-enrollments');

    Route::get('/sponsors', [SponsorController::class, 'index']);
    Route::get('/sponsors/{sponsor}/logo', SponsorLogoController::class)
        ->name('api.v1.sponsors.logo');

    Route::prefix('public-identity/confirmation')->group(function () {
        Route::post('/lookup', [PublicIdentityConfirmationController::class, 'lookup'])
            ->middleware('throttle:public-identity-token-lookup');
        Route::post('/confirm', [PublicIdentityConfirmationController::class, 'confirm'])
            ->middleware('throttle:public-identity-token-decision');
        Route::post('/deny', [PublicIdentityConfirmationController::class, 'deny'])
            ->middleware('throttle:public-identity-token-decision');
    });

    // Authenticated API
    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum');

    Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {

        // Me
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/me/player-profile', [AuthController::class, 'myPlayerProfile']);
        Route::post('/me/player-profile', [AuthController::class, 'createMyPlayerProfile']);
        Route::patch('/me/player-profile', [AuthController::class, 'updateMyPlayerProfile']);
        Route::post('/me/profile-photo', [ProfilePhotoController::class, 'store'])
            ->middleware('throttle:profile-photo-mutations');
        Route::delete('/me/profile-photo', [ProfilePhotoController::class, 'destroy'])
            ->middleware('throttle:profile-photo-mutations');
        Route::get('/me/profile-photo/image', ProfilePhotoImageController::class)
            ->name('api.v1.me.profile-photo.image');

        Route::get('/me/championship-registrations', [MyChampionshipRegistrationController::class, 'index']);
        Route::get('/me/matches', [MatchController::class, 'myMatches']);
        Route::get('/me/matches/pending-actions', [MatchController::class, 'pendingActions']);
        Route::get('/me/calendar', [MyDashboardController::class, 'calendar']);
        Route::get('/me/rankings', [MyDashboardController::class, 'rankings']);

        // Player match flow
        Route::get('/matches/{gameMatch}/workflow', [MatchController::class, 'workflow']);
        Route::post('/matches/{gameMatch}/submit-result', [MatchController::class, 'submitResult'])
            ->middleware('throttle:match.results');
        Route::post('/matches/{gameMatch}/confirm-result', [MatchController::class, 'confirmResult'])
            ->middleware('throttle:match.results');

        Route::get('/matches/{gameMatch}/reschedule-workflow', [MatchController::class, 'rescheduleWorkflow']);
        Route::post('/matches/{gameMatch}/request-reschedule', [MatchController::class, 'requestReschedule']);
        Route::post('/matches/{gameMatch}/confirm-reschedule', [MatchController::class, 'confirmReschedule']);

        // Championship registration requests (player)
        Route::get('/championships/{championship}/registration', [ChampionshipRegistrationController::class, 'show']);
        Route::post('/championships/{championship}/register', [ChampionshipRegistrationController::class, 'submit']);

        // Admin API
        Route::prefix('admin')
            ->middleware(IsAdmin::class)
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

                // Championship registration requests (admin)
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
