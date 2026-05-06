<?php

namespace App\Services;

use App\Models\App;
use App\Repositories\Contracts\AppRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AppService
{
    public function __construct(
        private readonly AppRepositoryInterface $apps,
        private readonly AppStateService        $stateService,
        private readonly AppEventService        $eventService,
        private readonly AdConfigService        $configService,
        private readonly EmailService           $emailService,
    ) {}

    public function listApps(): Collection
    {
        return $this->apps->all();
    }

    public function getApp(int $id): App
    {
        $app = $this->apps->findById($id);

        if (!$app) {
            throw new \RuntimeException("App #{$id} not found.", 404);
        }

        return $app;
    }

    public function createApp(array $data): App
    {
        $data['api_key'] = $this->apps->generateUniqueApiKey();

        return $this->apps->create($data);
    }

    public function updateApp(int $id, array $data): App
    {
        // Never allow direct api_key override via this path
        unset($data['api_key'], $data['app_status'], $data['is_suspended'], $data['status']);

        return $this->apps->update($id, $data);
    }

    /**
     * Rotate the API key — invalidates all SDK sessions immediately.
     * Callers should bust the Redis cache for this app after rotation.
     */
    public function rotateApiKey(int $id): App
    {
        $newKey = $this->apps->generateUniqueApiKey();
        return $this->apps->update($id, ['api_key' => $newKey]);
    }

    public function deleteApp(int $id): bool
    {
        return $this->apps->delete($id);
    }

    public function setAdEnabled(int $id, bool $enabled): App
    {
        $app = $this->getApp($id);

        if ($app->global_ad_enabled === $enabled) {
            return $app;
        }

        $updated = $this->apps->update($id, ['global_ad_enabled' => $enabled]);

        $this->eventService->log(
            app: $updated,
            eventType: $enabled ? 'global_ads_enabled' : 'global_ads_disabled',
            actorType: AppEventService::ACTOR_SYSTEM,
            metadata: ['from' => $app->global_ad_enabled, 'to' => $enabled],
        );

        $this->configService->bustCache($id);

        return $updated;
    }

    public function setAdEnabledByActor(int $id, bool $enabled, string $actorType, ?int $actorId = null): App
    {
        $app = $this->getApp($id);

        if ($app->global_ad_enabled === $enabled) {
            return $app;
        }

        $updated = $this->apps->update($id, ['global_ad_enabled' => $enabled]);

        $this->eventService->log(
            app: $updated,
            eventType: $enabled ? 'global_ads_enabled' : 'global_ads_disabled',
            actorType: $actorType,
            actorId: $actorId,
            metadata: ['from' => $app->global_ad_enabled, 'to' => $enabled],
        );

        $this->configService->bustCache($id);

        return $updated;
    }

    public function setAppStatus(int $id, bool $active, string $actorType = AppEventService::ACTOR_SYSTEM, ?int $actorId = null): App
    {
        $app = $this->getApp($id);

        $updated = $active
            ? $this->stateService->activate($app, $actorType, $actorId)
            : $this->stateService->deactivate($app, $actorType, $actorId);

        $updated->loadMissing('user');
        $this->emailService->sendAppStatusChanged($updated);

        return $updated;
    }

    public function listEvents(int $id): Collection
    {
        $this->getApp($id);

        return $this->eventService->forApp($id);
    }

    /**
     * Resolve app from API key — used by the SDK middleware.
     * Returns null on invalid / inactive key so the middleware can 401 fast.
     */
    public function resolveFromApiKey(string $apiKey): ?App
    {
        return $this->apps->findByApiKey($apiKey);
    }
}
