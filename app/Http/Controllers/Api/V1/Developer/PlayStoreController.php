<?php

namespace App\Http\Controllers\Api\V1\Developer;

use App\Http\Controllers\Controller;
use App\Services\PlayStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Developer provides a Play Store URL → we auto-extract app data.
 * /api/v1/developer/play-store/fetch
 */
class PlayStoreController extends Controller
{
    public function __construct(
        private readonly PlayStoreService $playStoreService
    ) {}

    /**
     * POST /api/v1/developer/play-store/fetch
     *
     * Body: { "url": "https://play.google.com/store/apps/details?id=com.example.app" }
     *
     * Response (success):
     * {
     *   "status": "ok",
     *   "data": {
     *     "name": "My App",
     *     "package_name": "com.example.app",
     *     "icon_url": "https://..."
     *   }
     * }
     *
     * Response (extraction failed — use manual input):
     * {
     *   "status": "partial",
     *   "package_name": "com.example.app",
     *   "message": "Could not extract app data. Please enter details manually.",
     *   "data": null
     * }
     */
    public function fetch(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url'],
        ]);

        $url    = $request->input('url');
        $result = $this->playStoreService->fetchAppData($url);

        if ($result['success']) {
            return response()->json([
                'status' => 'ok',
                'data'   => [
                    'name'         => $result['name'],
                    'package_name' => $result['package_name'],
                    'icon_url'     => $result['icon_url'],
                ],
            ]);
        }

        // Extraction failed — return what we could extract (package name at minimum)
        $packageName = $this->playStoreService->extractPackageName($url);

        return response()->json([
            'status'       => 'partial',
            'package_name' => $packageName,
            'message'      => $result['error'],
            'data'         => null,
        ], 200); // 200 not error — let frontend decide to show manual form
    }
}
