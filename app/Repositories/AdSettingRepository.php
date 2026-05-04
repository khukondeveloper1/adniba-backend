<?php

namespace App\Repositories;

use App\Models\AdSetting;
use App\Repositories\Contracts\AdSettingRepositoryInterface;
use Illuminate\Support\Collection;

class AdSettingRepository implements AdSettingRepositoryInterface
{
    public function getForApp(int $appId): Collection
    {
        return AdSetting::with('network')
            ->where('app_id', $appId)
            ->orderBy('ad_type')
            ->orderBy('placement')
            ->get();
    }

    public function getForPlacement(int $appId, string $adType, string $placement): ?AdSetting
    {
        // Hits idx_lookup index exactly
        return AdSetting::where('app_id',   $appId)
            ->where('ad_type',   $adType)
            ->where('placement', $placement)
            ->first();
    }

    /**
     * Upsert via updateOrCreate — maps to INSERT … ON DUPLICATE KEY UPDATE
     * using the uq_override unique key (app_id, ad_type, placement).
     */
    public function upsert(int $appId, string $adType, string $placement, array $data): AdSetting
    {
        return AdSetting::updateOrCreate(
            [
                'app_id'    => $appId,
                'ad_type'   => $adType,
                'placement' => $placement,
            ],
            $data
        );
    }

    public function delete(int $id): bool
    {
        return (bool) AdSetting::destroy($id);
    }
}
