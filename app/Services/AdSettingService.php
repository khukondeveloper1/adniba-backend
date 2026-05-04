<?php

namespace App\Services;

use App\Models\AdSetting;
use App\Repositories\Contracts\AdSettingRepositoryInterface;
use Illuminate\Support\Collection;

class AdSettingService
{
    public function __construct(
        private readonly AdSettingRepositoryInterface $settings,
        private readonly AdConfigService              $configService
    ) {}

    public function getForApp(int $appId): Collection
    {
        return $this->settings->getForApp($appId);
    }

    public function getForPlacement(int $appId, string $adType, string $placement): ?AdSetting
    {
        return $this->settings->getForPlacement($appId, $adType, $placement);
    }

    /**
     * Upsert a setting and immediately bust the cached config for the placement.
     */
    public function upsert(int $appId, string $adType, string $placement, array $data): AdSetting
    {
        $setting = $this->settings->upsert($appId, $adType, $placement, $data);

        // Bust Redis cache so the next SDK request gets fresh config
        $this->configService->bustCache($appId, $adType, $placement);

        return $setting;
    }

    public function delete(int $id, int $appId, string $adType, string $placement): bool
    {
        $result = $this->settings->delete($id);

        if ($result) {
            $this->configService->bustCache($appId, $adType, $placement);
        }

        return $result;
    }
}
