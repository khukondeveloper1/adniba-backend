<?php

namespace App\Services;

use App\Models\App;
use App\Services\AdConfigService;

class AppSuspensionService
{
    public function __construct(
        private readonly AdConfigService $configService,
        private readonly AppStateService $stateService,
        private readonly EmailService $emailService,
    ) {}

    /**
     * Suspend an app.
     * - Sets is_suspended = 1
     * - Busts config cache so SDK gets blocked immediately
     * - Sends email to the app's owner
     */
    public function suspend(App $app, string $reason, ?int $adminId = null): App
    {
        if ($app->isSuspended()) {
            throw new \RuntimeException('App is already suspended.', 409);
        }

        $app = $this->stateService->suspend(
            $app,
            $reason,
            AppEventService::ACTOR_ADMIN,
            $adminId,
        );

        // Bust all cached config for this app — SDK gets blocked on next request
        $this->configService->bustCache($app->id);

        $app->loadMissing('user');
        $this->emailService->sendAppSuspended($app, $reason);

        return $app->fresh();
    }

    /**
     * Unsuspend an app.
     * - Clears suspension fields
     * - Busts cache so SDK can access again
     * - Notifies developer
     */
    public function unsuspend(App $app, ?int $adminId = null): App
    {
        if (!$app->isSuspended()) {
            throw new \RuntimeException('App is not suspended.', 409);
        }

        $app = $this->stateService->unsuspend(
            $app,
            AppEventService::ACTOR_ADMIN,
            $adminId,
        );

        // Bust cache so fresh config is served
        $this->configService->bustCache($app->id);

        $app->loadMissing('user');
        $this->emailService->sendAppReactivated($app);

        return $app->fresh();
    }
}
