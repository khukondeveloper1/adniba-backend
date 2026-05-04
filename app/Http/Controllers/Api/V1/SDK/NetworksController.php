<?php

namespace App\Http\Controllers\Api\V1\SDK;

use App\Http\Controllers\Controller;
use App\Services\AdNetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/ads/networks
 *
 * Called once at SDK initialisation to determine which ad SDKs to boot.
 * Returns only networks that have at least one enabled ad unit, ordered
 * by their minimum unit priority so the SDK knows which to init first.
 *
 * This response is intentionally LIGHTWEIGHT — no unit_ids, no config.
 */
class NetworksController extends Controller
{
    public function __construct(
        private readonly AdNetworkService $networkService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\App $app */
        $app = $request->attributes->get('sdk_app');

        // Gate: if ads are globally disabled, return empty list so SDK
        // skips initialisation entirely — saves battery and bandwidth.
        if (!$app->hasAdsEnabled()) {
            return response()->json([
                'status'   => 'ok',
                'networks' => [],
            ]);
        }

        $networks = $this->networkService
            ->getActiveNetworksWithPriority($app->id)
            ->map(fn ($n) => [
                'name'     => $n->name,
                'priority' => (int) $n->priority,
            ])
            ->values()
            ->toArray();

        return response()->json([
            'status'   => 'ok',
            'networks' => $networks,
        ]);
    }
}
