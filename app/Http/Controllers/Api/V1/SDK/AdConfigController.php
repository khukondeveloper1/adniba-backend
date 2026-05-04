<?php

namespace App\Http\Controllers\Api\V1\SDK;

use App\Http\Controllers\Controller;
use App\Http\Requests\SDK\AdConfigRequest;
use App\Services\AdConfigService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/ads/config?ad_type=banner&placement=home
 *
 * Core hot-path endpoint. Must respond < 100 ms.
 *
 * Flow:
 *  1. Middleware already resolved + validated the App from x-api-key.
 *  2. Check global_ad_enabled flag.
 *  3. Delegate to AdConfigService which handles Redis caching + fallback logic.
 */
class AdConfigController extends Controller
{
    public function __construct(
        private readonly AdConfigService $configService
    ) {}

    public function show(AdConfigRequest $request): JsonResponse
    {
        /** @var \App\Models\App $app */
        $app = $request->attributes->get('sdk_app');

        // Global kill-switch — fastest possible early return
        if (!$app->hasAdsEnabled()) {
            return response()->json([
                'status'    => 'ok',
                'fallback'  => false,
                'ad_type'   => $request->query('ad_type'),
                'placement' => $request->query('placement'),
                'ads'       => [],
                'refresh_interval' => 60,
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
