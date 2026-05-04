<?php

namespace App\Repositories\Contracts;

use App\Models\AdUnit;
use Illuminate\Support\Collection;

interface AdUnitRepositoryInterface
{
    public function getForApp(int $appId): Collection;

    /**
     * Returns enabled ad units for a placement, joined with the network's
     * enabled flag, ordered by priority ASC.  Used by the config hot path.
     */
    public function getConfigUnits(int $appId, string $adType, string $placement): Collection;

    /**
     * Returns a single enabled unit for a specific network+placement.
     * Used in force (non-fallback) mode.
     */
    public function getForcedUnit(
        int    $appId,
        int    $networkId,
        string $adType,
        string $placement
    ): ?AdUnit;

    public function findById(int $id): ?AdUnit;

    public function create(array $data): AdUnit;

    public function update(int $id, array $data): AdUnit;

    public function delete(int $id): bool;
}
