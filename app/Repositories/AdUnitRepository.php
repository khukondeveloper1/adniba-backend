<?php

namespace App\Repositories;

use App\Models\AdUnit;
use App\Repositories\Contracts\AdUnitRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdUnitRepository implements AdUnitRepositoryInterface
{
    public function getForApp(int $appId): Collection
    {
        return AdUnit::with('network')
            ->where('app_id', $appId)
            ->orderBy('ad_type')
            ->orderBy('placement')
            ->orderBy('priority')
            ->get();
    }

    /**
     * Hot path: single query using composite idx_priority index.
     * Joins ad_networks to ensure the network itself is also enabled.
     * No N+1 — returns everything the config API needs in one hit.
     */
    public function getConfigUnits(int $appId, string $adType, string $placement): Collection
    {
        return DB::table('ad_units as u')
            ->join('ad_networks as n', 'n.id', '=', 'u.network_id')
            ->where('u.app_id',    $appId)
            ->where('u.ad_type',   $adType)
            ->where('u.placement', $placement)
            ->where('u.enabled', 1)
            ->where('n.enabled', 1)
            ->select(
                'u.id',
                'u.unit_id',
                'u.priority',
                'n.name as network'
            )
            ->orderBy('u.priority')
            ->get();
    }

    /**
     * Force-mode: return the single unit for a specific network+placement.
     */
    public function getForcedUnit(
        int    $appId,
        int    $networkId,
        string $adType,
        string $placement
    ): ?AdUnit {
        return AdUnit::where('app_id',     $appId)
            ->where('network_id', $networkId)
            ->where('ad_type',    $adType)
            ->where('placement',  $placement)
            ->where('enabled',    1)
            ->first();
    }

    public function findById(int $id): ?AdUnit
    {
        return AdUnit::with('network')->find($id);
    }

    public function create(array $data): AdUnit
    {
        return AdUnit::create($data);
    }

    public function update(int $id, array $data): AdUnit
    {
        $unit = AdUnit::findOrFail($id);
        $unit->update($data);
        return $unit->fresh();
    }

    public function delete(int $id): bool
    {
        return (bool) AdUnit::destroy($id);
    }
}
