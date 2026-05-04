<?php

namespace App\Services;

use App\Models\App;
use App\Repositories\Contracts\AdSettingRepositoryInterface;
use App\Repositories\Contracts\AdUnitRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class AdConfigService
{
    private const CACHE_TTL_MIN = 60;
    private const CACHE_TTL_MAX = 300;

    public function __construct(
        private readonly AdSettingRepositoryInterface $settings,
        private readonly AdUnitRepositoryInterface    $units,
    ) {}

    public function getConfig(App $app, string $adType, string $placement): array
    {
        $ttl      = $this->randomTtl();
        $cacheKey = $this->cacheKey($app->id, $adType, $placement);

        // ✅ Cache fail হলে সরাসরি DB থেকে দাও
        try {
            return Cache::remember($cacheKey, $ttl, function () use ($app, $adType, $placement, $ttl) {
                return $this->buildConfig($app, $adType, $placement, $ttl);
            });
        } catch (\Throwable $e) {
            // Cache নেই (Redis নেই) — সরাসরি build করো
            return $this->buildConfig($app, $adType, $placement, $ttl);
        }
    }

    public function bustCache(int $appId, ?string $adType = null, ?string $placement = null): void
    {
        // ✅ Cache fail হলে silently skip
        try {
            if ($adType && $placement) {
                Cache::forget($this->cacheKey($appId, $adType, $placement));
                return;
            }
            Cache::flush();
        } catch (\Throwable $e) {
            // Cache নেই — কোনো সমস্যা নেই
        }
    }

    private function buildConfig(App $app, string $adType, string $placement, int $ttl): array
    {
        $setting = $this->settings->getForPlacement($app->id, $adType, $placement);

        $fallbackEnabled = $setting ? $setting->isFallbackMode() : true;

        if (!$fallbackEnabled && $setting?->network_id) {
            $ads = $this->buildForcedAds($app->id, $setting->network_id, $adType, $placement);
        } else {
            $ads = $this->buildFallbackAds($app->id, $adType, $placement);
        }

        return [
            'status'           => 'ok',
            'fallback'         => $fallbackEnabled,
            'ad_type'          => $adType,
            'placement'        => $placement,
            'ads'              => $ads,
            'refresh_interval' => $ttl,
        ];
    }

    private function buildForcedAds(int $appId, int $networkId, string $adType, string $placement): array
    {
        $unit = $this->units->getForcedUnit($appId, $networkId, $adType, $placement);

        if (!$unit) {
            return [];
        }

        return [[
            'network'  => $unit->network->name ?? 'unknown',
            'unit_id'  => $unit->unit_id,
            'priority' => 1,
        ]];
    }

    private function buildFallbackAds(int $appId, string $adType, string $placement): array
    {
        return $this->units
            ->getConfigUnits($appId, $adType, $placement)
            ->map(fn ($u) => [
                'network'  => $u->network,
                'unit_id'  => $u->unit_id,
                'priority' => (int) $u->priority,
            ])
            ->values()
            ->toArray();
    }

    private function cacheKey(int $appId, string $adType, string $placement): string
    {
        return "adcfg:{$appId}:{$adType}:{$placement}";
    }

    private function randomTtl(): int
    {
        return random_int(self::CACHE_TTL_MIN, self::CACHE_TTL_MAX);
    }
}