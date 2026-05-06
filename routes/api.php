<?php

use App\Http\Controllers\Api\V1\Admin\AdNetworkController;
use App\Http\Controllers\Api\V1\Admin\AdSettingController;
use App\Http\Controllers\Api\V1\Admin\AdUnitController;
use App\Http\Controllers\Api\V1\Admin\AnalyticsController;
use App\Http\Controllers\Api\V1\Admin\ApiDocController;
use App\Http\Controllers\Api\V1\Admin\AppController;
use App\Http\Controllers\Api\V1\Admin\AppSuspensionController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\EmailController;
use App\Http\Controllers\Api\V1\Admin\GlobalNetworkController;
use App\Http\Controllers\Api\V1\Admin\LimitRequestController;
use App\Http\Controllers\Api\V1\Admin\StaticPageController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Developer;
use App\Http\Controllers\Api\V1\External\AppInfoController;
use App\Http\Controllers\Api\V1\External\NetworkController as ExternalNetworkController;
use App\Http\Controllers\Api\V1\External\UnitController as ExternalUnitController;
use App\Http\Controllers\Api\V1\External\SettingController as ExternalSettingController;
use App\Http\Controllers\Api\V1\External\StatsController;
use App\Http\Controllers\Api\V1\PublicController;
use App\Http\Controllers\Api\V1\SDK\AdConfigController;
use App\Http\Controllers\Api\V1\SDK\EventController;
use App\Http\Controllers\Api\V1\SDK\NetworksController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // =========================================================================
    //  PUBLIC — No auth required
    // =========================================================================
    Route::prefix('public')->group(function () {
        Route::get('pages',              [PublicController::class, 'pages']);
        Route::get('pages/{key}',        [PublicController::class, 'page']);
        Route::get('api-docs',           [PublicController::class, 'apiDocs']);
        Route::get('api-docs/{slug}',    [PublicController::class, 'apiDoc']);
        Route::get('networks',           [PublicController::class, 'availableNetworks']);
    });

    // =========================================================================
    //  ADMIN — JWT protected (AdminUser)
    // =========================================================================
    Route::prefix('admin')->group(function () {

        // Auth (public)
        Route::prefix('auth')->group(function () {
            Route::post('login',   [AuthController::class, 'login']);
            Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth.admin');
            Route::post('logout',  [AuthController::class, 'logout'])->middleware('auth.admin');
        });

        // Protected admin routes
        Route::middleware(['auth.admin', 'throttle:admin'])->group(function () {

            // ── Apps ───────────────────────────────────────────────────────
            Route::apiResource('apps', AppController::class);
            Route::post('apps/{id}/rotate-key',   [AppController::class, 'rotateKey']);
            Route::patch('apps/{id}/status',      [AppController::class, 'toggleStatus']);
            Route::patch('apps/{id}/ads-enabled', [AppController::class, 'toggleAds']);
            Route::get('apps/{id}/events',        [AppController::class, 'events']);

            // App Suspension (NEW)
            Route::post('apps/{id}/suspend',   [AppSuspensionController::class, 'suspend']);
            Route::post('apps/{id}/unsuspend', [AppSuspensionController::class, 'unsuspend']);

            // ── Analytics only (no networks/units/settings per requirement) ─
            Route::prefix('apps/{appId}')->group(function () {
                // Admin can view analytics only — networks/units/settings = developer only
                Route::get('analytics',       [AnalyticsController::class, 'summary']);
                Route::get('analytics/daily', [AnalyticsController::class, 'daily']);
            });

            // ── Global Analytics ───────────────────────────────────────────
            Route::get('analytics',       [AnalyticsController::class, 'summary']);
            Route::get('analytics/daily', [AnalyticsController::class, 'daily']);

            // ── Global Ad Networks (NEW) ────────────────────────────────────
            Route::get('global-networks',               [GlobalNetworkController::class, 'index']);
            Route::post('global-networks',              [GlobalNetworkController::class, 'store']);
            Route::put('global-networks/{id}',          [GlobalNetworkController::class, 'update']);
            Route::patch('global-networks/{id}/toggle', [GlobalNetworkController::class, 'toggle']);
            Route::delete('global-networks/{id}',       [GlobalNetworkController::class, 'destroy']);

            // ── Developer User Management ───────────────────────────────────
            Route::get('users',                  [UserController::class, 'index']);
            Route::get('users/{id}',             [UserController::class, 'show']);
            Route::patch('users/{id}/status',    [UserController::class, 'toggleStatus']);
            Route::patch('users/{id}/app-limit', [UserController::class, 'setAppLimit']);
            Route::delete('users/{id}',          [UserController::class, 'destroy']);
            Route::post('users/{id}/send-email', [UserController::class, 'sendEmail']);

            // ── Limit Requests ─────────────────────────────────────────────
            Route::get('limit-requests',                [LimitRequestController::class, 'index']);
            Route::post('limit-requests/{id}/approve', [LimitRequestController::class, 'approve']);
            Route::post('limit-requests/{id}/reject',  [LimitRequestController::class, 'reject']);

            // ── Email ──────────────────────────────────────────────────────
            Route::get('emails',            [EmailController::class, 'index']);
            Route::post('emails/send',      [EmailController::class, 'send']);
            Route::post('emails/broadcast', [EmailController::class, 'broadcast']);

            // ── API Docs (NEW) ─────────────────────────────────────────────
            Route::get('api-docs',          [ApiDocController::class, 'index']);
            Route::post('api-docs',         [ApiDocController::class, 'store']);
            Route::get('api-docs/{id}',     [ApiDocController::class, 'show']);
            Route::put('api-docs/{id}',     [ApiDocController::class, 'update']);
            Route::delete('api-docs/{id}',  [ApiDocController::class, 'destroy']);

            // ── Static Pages (NEW) ─────────────────────────────────────────
            Route::get('pages',          [StaticPageController::class, 'index']);
            Route::get('pages/{key}',    [StaticPageController::class, 'show']);
            Route::put('pages/{key}',    [StaticPageController::class, 'upsert']);
        });
    });

    // =========================================================================
    //  DEVELOPER — JWT protected (User model)
    // =========================================================================
    Route::prefix('developer')->group(function () {

        // Auth (public)
        Route::prefix('auth')->group(function () {
            Route::post('register',       [Developer\AuthController::class, 'register']);
            Route::post('login',          [Developer\AuthController::class, 'login']);
            Route::post('logout',         [Developer\AuthController::class, 'logout'])->middleware('auth.developer');
            Route::get('me',              [Developer\AuthController::class, 'me'])->middleware('auth.developer');
            
            Route::post('refresh',  [Developer\AuthController::class, 'refresh'])->middleware('auth.developer');

            
            // Password reset (public — no auth needed)
            Route::post('forgot-password', [Developer\PasswordResetController::class, 'forgotPassword']);
            Route::post('reset-password',  [Developer\PasswordResetController::class, 'resetPassword']);

            // Email verification (public — no auth needed)
            Route::post('verify-email',          [Developer\AuthController::class, 'verifyEmail']);
            Route::post('resend-verification',   [Developer\AuthController::class, 'resendVerification']);
        });

        // Protected developer routes
        Route::middleware(['auth.developer', 'throttle:developer'])->group(function () {

            // ── Profile (NEW) ──────────────────────────────────────────────
            Route::get('profile',                [Developer\ProfileController::class, 'show']);
            Route::put('profile',                [Developer\ProfileController::class, 'update']);
            Route::post('profile/avatar',        [Developer\ProfileController::class, 'uploadAvatar']);
            Route::delete('profile/avatar',      [Developer\ProfileController::class, 'deleteAvatar']);
            Route::post('profile/change-password',[Developer\ProfileController::class, 'changePassword']);

            // ── Play Store Auto-fetch (NEW) ────────────────────────────────
            Route::post('play-store/fetch', [Developer\PlayStoreController::class, 'fetch']);

            // ── Apps ───────────────────────────────────────────────────────
            Route::get('apps',                  [Developer\AppController::class, 'index']);
            Route::post('apps',                 [Developer\AppController::class, 'store']);
            Route::get('apps/{id}',             [Developer\AppController::class, 'show']);
            Route::put('apps/{id}',             [Developer\AppController::class, 'update']);
            Route::delete('apps/{id}',          [Developer\AppController::class, 'destroy']);
            Route::post('apps/{id}/rotate-key', [Developer\AppController::class, 'rotateKey']);
            Route::patch('apps/{id}/status',    [Developer\AppController::class, 'toggleStatus']);
            Route::patch('apps/{id}/ads-enabled', [Developer\AppController::class, 'toggleAds']);
            Route::get('apps/{id}/stats',       [Developer\AppController::class, 'stats']);

            // ── Networks, Units, Settings (Developer-only control) ─────────
            Route::prefix('apps/{appId}')->group(function () {
                // Networks
                Route::get('networks',               [Developer\NetworkController::class, 'index']);
                Route::post('networks',              [Developer\NetworkController::class, 'store']);
                Route::patch('networks/{id}/toggle', [Developer\NetworkController::class, 'toggle']);
                Route::delete('networks/{id}',       [Developer\NetworkController::class, 'destroy']);

                // Ad Units
                Route::get('units',               [Developer\UnitController::class, 'index']);
                Route::post('units',              [Developer\UnitController::class, 'store']);
                Route::get('units/{id}',          [Developer\UnitController::class, 'show']);
                Route::put('units/{id}',          [Developer\UnitController::class, 'update']);
                Route::patch('units/{id}/toggle', [Developer\UnitController::class, 'toggle']);
                Route::delete('units/{id}',       [Developer\UnitController::class, 'destroy']);

                // Settings
                Route::get('settings',                      [Developer\SettingController::class, 'index']);
                Route::post('settings',                     [Developer\SettingController::class, 'upsert']);
                Route::get('settings/{adType}/{placement}', [Developer\SettingController::class, 'showPlacement']);
                Route::delete('settings/{id}',              [Developer\SettingController::class, 'destroy']);
            });

            // ── Limit Requests ─────────────────────────────────────────────
            Route::get('limit-requests',  [Developer\AppLimitController::class, 'index']);
            Route::post('limit-requests', [Developer\AppLimitController::class, 'store']);
        });
    });

    // =========================================================================
    //  SDK — x-api-key protected
    // =========================================================================
    Route::prefix('ads')
        ->middleware(['auth.sdk', 'throttle:sdk'])
        ->group(function () {
            Route::get('networks', [NetworksController::class, 'index']);
            Route::get('config',   [AdConfigController::class, 'show']);
            Route::post('event',   [EventController::class, 'store']);
        });

    // =========================================================================
    //  EXTERNAL API — x-api-key protected (developer's custom panel)
    // =========================================================================
    Route::prefix('external')
        ->middleware(['auth.external', 'throttle:external'])
        ->group(function () {
            Route::get('app',    [AppInfoController::class, 'info']);
            Route::get('config', [AppInfoController::class, 'config']);

            Route::get('stats',       [StatsController::class, 'summary']);
            Route::get('stats/daily', [StatsController::class, 'daily']);

            Route::get('networks',        [ExternalNetworkController::class, 'index']);
            Route::get('networks/active', [ExternalNetworkController::class, 'active']);

            Route::get('units',               [ExternalUnitController::class, 'index']);
            Route::post('units',              [ExternalUnitController::class, 'store']);
            Route::get('units/{id}',          [ExternalUnitController::class, 'show']);
            Route::put('units/{id}',          [ExternalUnitController::class, 'update']);
            Route::patch('units/{id}/toggle', [ExternalUnitController::class, 'toggle']);
            Route::delete('units/{id}',       [ExternalUnitController::class, 'destroy']);

            Route::get('settings',                      [ExternalSettingController::class, 'index']);
            Route::post('settings',                     [ExternalSettingController::class, 'upsert']);
            Route::get('settings/{adType}/{placement}', [ExternalSettingController::class, 'show']);
            Route::delete('settings/{id}',              [ExternalSettingController::class, 'destroy']);
        });
});
