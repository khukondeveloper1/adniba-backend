<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\App;
use App\Services\AdConfigService;

class AppSuspensionService
{
    public function __construct(
        private readonly AdConfigService $configService,
        private readonly AppStateService $stateService,
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

        // Notify the developer via email
        if ($app->user) {
            SendEmailJob::dispatch(
                userId:  $app->user_id,
                toEmail: $app->user->email,
                subject: "Your app \"{$app->name}\" has been suspended",
                type:    'custom',
                data:    [
                    'name' => $app->user->name,
                    'body' => "
                        <h2>App Suspended</h2>
                        <p>Your app <strong>{$app->name}</strong> ({$app->package_name}) has been suspended.</p>
                        <p><strong>Reason:</strong> {$reason}</p>
                        <p>Please contact support if you believe this is a mistake.</p>
                    ",
                ]
            )->onQueue('default');
        }

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

        // Notify the developer
        if ($app->user) {
            SendEmailJob::dispatch(
                userId:  $app->user_id,
                toEmail: $app->user->email,
                subject: "Your app \"{$app->name}\" has been reactivated",
                type:    'custom',
                data:    [
                    'name' => $app->user->name,
                    'body' => "
                        <h2>App Reactivated</h2>
                        <p>Your app <strong>{$app->name}</strong> ({$app->package_name}) has been reactivated and is now live.</p>
                    ",
                ]
            )->onQueue('default');
        }

        return $app->fresh();
    }
}
