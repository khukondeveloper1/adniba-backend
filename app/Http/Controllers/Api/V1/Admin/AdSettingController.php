<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertAdSettingRequest;
use App\Services\AdSettingService;
use Illuminate\Http\JsonResponse;

/**
 * Resource: /api/v1/admin/apps/{appId}/settings
 *
 * Controls the fallback vs force-mode behaviour per placement.
 * POST/PUT both upsert (create-or-update) using the unique key
 * (app_id, ad_type, placement).
 */
class AdSettingController extends Controller
{
    public function __construct(
        private readonly AdSettingService $settingService
    ) {}

    /** GET /api/v1/admin/apps/{appId}/settings */
    public function index(int $appId): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data'   => $this->settingService->getForApp($appId),
        ]);
    }

    /** GET /api/v1/admin/apps/{appId}/settings/placement?ad_type=&placement= */
    public function showPlacement(int $appId, string $adType, string $placement): JsonResponse
    {
        $setting = $this->settingService->getForPlacement($appId, $adType, $placement);

        if (!$setting) {
            // Return a sensible default rather than 404 — the SDK assumes fallback=true when absent
            return response()->json([
                'status' => 'ok',
                'data'   => [
                    'app_id'           => $appId,
                    'ad_type'          => $adType,
                    'placement'        => $placement,
                    'fallback_enabled' => true,
                    'network_id'       => null,
                ],
            ]);
        }

        return response()->json(['status' => 'ok', 'data' => $setting]);
    }

    /** POST /api/v1/admin/apps/{appId}/settings — upsert */
    public function upsert(UpsertAdSettingRequest $request, int $appId): JsonResponse
    {
        $data = $request->validated();

        $setting = $this->settingService->upsert(
            $appId,
            $data['ad_type'],
            $data['placement'],
            [
                'fallback_enabled' => $data['fallback_enabled'],
                'network_id'       => $data['fallback_enabled'] ? null : $data['network_id'],
            ]
        );

        return response()->json(['status' => 'ok', 'data' => $setting]);
    }

    /** DELETE /api/v1/admin/apps/{appId}/settings/{id} */
    public function destroy(int $appId, int $id): JsonResponse
    {
        // Fetch before delete so we have ad_type + placement for cache bust
        $setting = \App\Models\AdSetting::find($id);

        if (!$setting || $setting->app_id !== $appId) {
            return response()->json(['status' => 'error', 'message' => 'Not found.'], 404);
        }

        $this->settingService->delete($id, $appId, $setting->ad_type, $setting->placement);

        return response()->json(['status' => 'ok', 'message' => 'Setting deleted.']);
    }
}
