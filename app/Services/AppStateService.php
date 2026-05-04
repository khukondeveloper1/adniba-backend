<?php

namespace App\Services;

use App\Models\App;
use Illuminate\Support\Facades\DB;

class AppStateService
{
    private const ALLOWED_STATUSES = [
        App::STATUS_ACTIVE,
        App::STATUS_INACTIVE,
        App::STATUS_SUSPENDED,
    ];

    public function __construct(
        private readonly AppEventService $events,
        private readonly AdConfigService $configService,
    ) {}

    public function activate(
        App $app,
        string $actorType,
        ?int $actorId = null,
        ?string $reason = null,
        string $eventType = 'activated',
    ): App {
        return $this->transition($app, App::STATUS_ACTIVE, $actorType, $actorId, $eventType, $reason);
    }

    public function deactivate(
        App $app,
        string $actorType,
        ?int $actorId = null,
        ?string $reason = null,
    ): App {
        return $this->transition($app, App::STATUS_INACTIVE, $actorType, $actorId, 'deactivated', $reason);
    }

    public function suspend(App $app, string $reason, string $actorType, ?int $actorId = null): App
    {
        return $this->transition($app, App::STATUS_SUSPENDED, $actorType, $actorId, 'suspended', $reason);
    }

    public function unsuspend(App $app, string $actorType, ?int $actorId = null, ?string $reason = null): App
    {
        return $this->transition($app, App::STATUS_ACTIVE, $actorType, $actorId, 'unsuspended', $reason);
    }

    public function transition(
        App $app,
        string $toStatus,
        string $actorType,
        ?int $actorId,
        string $eventType,
        ?string $reason = null,
        ?array $metadata = null,
    ): App {
        $this->assertValidStatus($toStatus);

        return DB::transaction(function () use ($app, $toStatus, $actorType, $actorId, $eventType, $reason, $metadata) {
            $app = App::whereKey($app->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $app->status;

            $this->assertAllowedTransition($fromStatus, $toStatus, $eventType);

            if ($fromStatus === $toStatus) {
                throw new \RuntimeException("App is already {$toStatus}.", 409);
            }

            $updates = ['status' => $toStatus];

            if ($toStatus === App::STATUS_SUSPENDED) {
                $updates['suspension_reason'] = $reason;
                $updates['suspended_at'] = now();
            }

            if ($fromStatus === App::STATUS_SUSPENDED && $toStatus !== App::STATUS_SUSPENDED) {
                $updates['suspension_reason'] = null;
                $updates['suspended_at'] = null;
            }

            $app->update($updates);

            $this->events->log(
                app: $app,
                eventType: $eventType,
                actorType: $actorType,
                actorId: $actorId,
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                reason: $reason,
                metadata: $metadata,
            );

            $this->configService->bustCache($app->id);

            return $app->fresh();
        });
    }

    private function assertValidStatus(string $status): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \RuntimeException('Invalid app status.', 422);
        }
    }

    private function assertAllowedTransition(string $fromStatus, string $toStatus, string $eventType): void
    {
        if ($fromStatus === App::STATUS_SUSPENDED && $toStatus === App::STATUS_INACTIVE) {
            throw new \RuntimeException('Unsuspend the app before deactivating it.', 422);
        }

        if ($fromStatus === App::STATUS_SUSPENDED && $toStatus === App::STATUS_ACTIVE) {
            $allowedEvents = ['unsuspended', 'appeal_approved'];

            if (!in_array($eventType, $allowedEvents, true)) {
                throw new \RuntimeException('Suspended apps can only be restored through an unsuspend or appeal approval.', 422);
            }
        }
    }
}
