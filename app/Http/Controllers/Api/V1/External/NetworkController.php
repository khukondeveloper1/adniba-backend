<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Services\AdNetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/external/networks
 *
 * Developer এর app এর active networks দেখার জন্য।
 * Read-only — management এর জন্য Developer JWT API ব্যবহার করুন।
 */
class NetworkController extends Controller
{
    public function __construct(
        private readonly AdNetworkService $networkService
    ) {}

    /**
     * সব networks (enabled + disabled উভয়ই)
     * GET /api/v1/external/networks
     */
    public function index(Request $request): JsonResponse
    {
        $app      = $request->attributes->get('external_app');
        $networks = $this->networkService->getForApp($app->id);

        return response()->json([
            'status' => 'ok',
            'app'    => ['id' => $app->id, 'name' => $app->name],
            'data'   => $networks,
        ]);
    }

    /**
     * শুধু enabled networks — priority অনুযায়ী
     * GET /api/v1/external/networks/active
     */
    public function active(Request $request): JsonResponse
    {
        $app      = $request->attributes->get('external_app');
        $networks = $this->networkService->getActiveNetworksWithPriority($app->id);

        return response()->json([
            'status' => 'ok',
            'app'    => ['id' => $app->id, 'name' => $app->name],
            'data'   => $networks,
        ]);
    }
}
