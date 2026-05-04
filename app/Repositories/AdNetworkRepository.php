<?php

namespace App\Repositories;

use App\Models\AdNetwork;
use App\Repositories\Contracts\AdNetworkRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdNetworkRepository implements AdNetworkRepositoryInterface
{
    public function getForApp(int $appId): Collection
    {
        return AdNetwork::where('app_id', $appId)
            ->with('global')
            ->get()
            ->sortBy(fn($n) => $n->global?->display_name ?? '')
            ->values();
    }

    public function getEnabledForApp(int $appId): Collection
    {
        return AdNetwork::where('app_id', $appId)
            ->where('enabled', 1)
            ->with('global')
            ->get();
    }

    /**
     * Returns distinct networks that have at least one enabled ad unit for the
     * given app, ordered by their lowest unit priority value (best = 1 first).
     * This powers the /ads/networks init endpoint.
     */
    public function getActiveNetworksWithPriority(int $appId): Collection
    {
        return DB::table('ad_networks as n')
            ->join('ad_units as u', function ($join) use ($appId) {
                $join->on('u.network_id', '=', 'n.id')
                     ->where('u.app_id', '=', $appId)
                     ->where('u.enabled', '=', 1);
            })
            ->join('global_ad_networks as g', 'n.global_id', '=', 'g.id')
            ->where('n.app_id', $appId)
            ->where('n.enabled', 1)
            ->select('g.id as global_id', 'g.name', 'g.display_name', DB::raw('MIN(u.priority) as priority'))
            ->groupBy('n.id', 'g.id', 'g.name', 'g.display_name')
            ->orderBy('priority')
            ->get();
    }

    public function findById(int $id): ?AdNetwork
    {
        return AdNetwork::find($id);
    }

    public function findByAppAndName(int $appId, string $name): ?AdNetwork
    {
        $global = DB::table('global_ad_networks')->where('name', $name)->first();
        if (!$global) {
            return null;
        }

        return AdNetwork::where('app_id', $appId)
            ->where('global_id', $global->id)
            ->first();
    }

    public function create(array $data): AdNetwork
    {
        return AdNetwork::create($data);
    }

    public function update(int $id, array $data): AdNetwork
    {
        $network = AdNetwork::findOrFail($id);
        $network->update($data);
        return $network->fresh();
    }

    public function delete(int $id): bool
    {
        return (bool) AdNetwork::destroy($id);
    }
}
