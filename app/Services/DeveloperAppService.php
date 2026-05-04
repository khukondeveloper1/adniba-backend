<?php

namespace App\Services;

use App\Models\App;
use App\Models\User;
use App\Repositories\Contracts\AppRepositoryInterface;
use Illuminate\Support\Collection;

class DeveloperAppService
{
    public function __construct(
        private readonly AppRepositoryInterface $apps,
        private readonly AppService             $appService,
    ) {}

    /**
     * List all apps belonging to the developer.
     */
    public function listMyApps(User $user): Collection
    {
        return App::where('user_id', $user->id)
            ->withCount(['adNetworks', 'adUnits'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get a single app — must belong to this user.
     */
    public function getMyApp(User $user, int $appId): App
    {
        $app = App::where('id', $appId)
            ->where('user_id', $user->id)
            ->with(['adNetworks', 'adUnits', 'adSettings'])
            ->first();

        if (!$app) {
            throw new \RuntimeException('App not found.', 404);
        }

        return $app;
    }

    /**
     * Create a new app for the developer — respects app_limit.
     */
    public function createApp(User $user, array $data): App
    {
        if (!$user->canCreateApp()) {
            throw new \RuntimeException(
                "App limit reached ({$user->app_limit}). Request a limit increase.", 403
            );
        }

        $data['user_id'] = $user->id;
        $data['api_key'] = $this->apps->generateUniqueApiKey();

        return $this->apps->create($data);
    }

    /**
     * Update an app — must belong to this user.
     */
    public function updateApp(User $user, int $appId, array $data): App
    {
        $this->assertOwnership($user, $appId);
        unset($data['api_key'], $data['user_id'], $data['app_status'], $data['is_suspended'], $data['status']);

        return $this->apps->update($appId, $data);
    }

    public function setAppStatus(User $user, int $appId, bool $active): App
    {
        $this->assertOwnership($user, $appId);

        return $this->appService->setAppStatus(
            $appId,
            $active,
            AppEventService::ACTOR_DEVELOPER,
            $user->id,
        );
    }

    public function setAdEnabled(User $user, int $appId, bool $enabled): App
    {
        $this->assertOwnership($user, $appId);

        return $this->appService->setAdEnabledByActor(
            $appId,
            $enabled,
            AppEventService::ACTOR_DEVELOPER,
            $user->id,
        );
    }

    /**
     * Delete an app — must belong to this user.
     */
    public function deleteApp(User $user, int $appId): bool
    {
        $this->assertOwnership($user, $appId);
        return $this->apps->delete($appId);
    }

    /**
     * Rotate API key — must belong to this user.
     */
    public function rotateApiKey(User $user, int $appId): App
    {
        $this->assertOwnership($user, $appId);
        return $this->appService->rotateApiKey($appId);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function assertOwnership(User $user, int $appId): void
    {
        $exists = App::where('id', $appId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$exists) {
            throw new \RuntimeException('App not found.', 404);
        }
    }
}
