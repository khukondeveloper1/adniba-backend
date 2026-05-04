<?php

namespace App\Repositories\Contracts;

use App\Models\AdNetwork;
use Illuminate\Support\Collection;

interface AdNetworkRepositoryInterface
{
    /** All networks for a given app. */
    public function getForApp(int $appId): Collection;

    /** Only enabled networks for a given app. */
    public function getEnabledForApp(int $appId): Collection;

    /**
     * Networks that have at least one ENABLED ad-unit for this app,
     * ordered by minimum unit priority (for the /ads/networks init endpoint).
     */
    public function getActiveNetworksWithPriority(int $appId): Collection;

    public function findById(int $id): ?AdNetwork;

    public function findByAppAndName(int $appId, string $name): ?AdNetwork;

    public function create(array $data): AdNetwork;

    public function update(int $id, array $data): AdNetwork;

    public function delete(int $id): bool;
}
