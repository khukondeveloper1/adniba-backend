<?php

namespace App\Services;

use App\Models\AdUnit;
use App\Repositories\Contracts\AdUnitRepositoryInterface;
use Illuminate\Support\Collection;

class AdUnitService
{
    public function __construct(
        private readonly AdUnitRepositoryInterface $units
    ) {}

    public function getForApp(int $appId): Collection
    {
        return $this->units->getForApp($appId);
    }

    public function getConfigUnits(int $appId, string $adType, string $placement): Collection
    {
        return $this->units->getConfigUnits($appId, $adType, $placement);
    }

    public function getForcedUnit(int $appId, int $networkId, string $adType, string $placement): ?AdUnit
    {
        return $this->units->getForcedUnit($appId, $networkId, $adType, $placement);
    }

    public function createUnit(int $appId, array $data): AdUnit
    {
        $data['app_id'] = $appId;
        return $this->units->create($data);
    }

    public function updateUnit(int $id, array $data): AdUnit
    {
        return $this->units->update($id, $data);
    }

    public function toggleUnit(int $id, bool $enabled): AdUnit
    {
        return $this->units->update($id, ['enabled' => $enabled]);
    }

    public function deleteUnit(int $id): bool
    {
        return $this->units->delete($id);
    }

    public function findById(int $id): ?AdUnit
    {
        return $this->units->findById($id);
    }
}
