<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Services\AdConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * External App Info & Config endpoints
 *
 * GET /api/v1/external/app       — App overview
 * GET /api/v1/external/config    — Ad config (same as SDK but for external panel)
 */
class AppInfoController extends Controller
{
    public function __construct(
        private readonly AdConfigService $configService
    ) {}

    /**
     * App overview — info + quick stats
     * GET /api/v1/external/app
     */
    public function info(Request $request): JsonResponse
    {
        $app = $request->attributes->get('external_app');

        // Load relationships
        $app->load(['adNetworks', 'adSettings']);
        $app->loadCount(['adNetworks', 'adUnits']);

        return response()->json([
            'status' => 'ok',
            'data'   => [
                'id'                  => $app->id,
                'name'                => $app->name,
                'package_name'        => $app->package_name,
                'status'              => $app->status,
                'app_status'          => $app->app_status,
                'global_ad_enabled'   => $app->global_ad_enabled,
                'ad_networks_count'   => $app->ad_networks_count,
                'ad_units_count'      => $app->ad_units_count,
                'networks'            => $app->adNetworks,
                'created_at'          => $app->created_at,
            ],
        ]);
    }

    /**
     * Ad config — same as SDK /ads/config but for external panel
     * GET /api/v1/external/config?ad_type=banner&placement=home
     */
    public function config(Request $request): JsonResponse
    {
        $request->validate([
            'ad_type'   => ['required', 'string', 'in:banner,interstitial,rewarded,native,app_open'],
            'placement' => ['required', 'string', 'max:50'],
        ]);

        $app = $request->attributes->get('external_app');

        if (!$app->hasAdsEnabled()) {
            return response()->json([
                'status'    => 'ok',
                'fallback'  => false,
                'ad_type'   => $request->query('ad_type'),
                'placement' => $request->query('placement'),
                'ads'       => [],
                'note'      => 'Ads are globally disabled for this app.',
            ]);
        }

        $config = $this->configService->getConfig(
            $app,
            $request->query('ad_type'),
            $request->query('placement')
        );

        return response()->json($config);
    }
}
