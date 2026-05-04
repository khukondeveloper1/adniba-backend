<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Models\AdSetting;
use App\Models\AdUnit;
use App\Services\AdSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/v1/external/settings
 *
 * Developer নিজের API Key দিয়ে fallback/force mode control করতে পারবে।
 */
class SettingController extends Controller
{
    public function __construct(
        private readonly AdSettingService $settingService
    ) {}

    /**
     * সব settings
     * GET /api/v1/external/settings
     */
    public function index(Request $request): JsonResponse
    {
        $app      = $request->attributes->get('external_app');
        $settings = $this->settingService->getForApp($app->id);

        return response()->json([
            'status' => 'ok',
            'app'    => ['id' => $app->id, 'name' => $app->name],
            'data'   => $settings,
        ]);
    }

    /**
     * একটা placement এর setting দেখো
     * GET /api/v1/external/settings/{adType}/{placement}
     */
    public function show(Request $request, string $adType, string $placement): JsonResponse
    {
        $app     = $request->attributes->get('external_app');
        $setting = $this->settingService->getForPlacement($app->id, $adType, $placement);

        return response()->json([
            'status' => 'ok',
            'data'   => $setting ?? [
                'app_id'           => $app->id,
                'ad_type'          => $adType,
                'placement'        => $placement,
                'fallback_enabled' => true,
                'network_id'       => null,
                'note'             => 'No setting found — default fallback mode.',
            ],
        ]);
    }

    /**
     * Setting upsert (create or update)
     * POST /api/v1/external/settings
     *
     * Fallback mode:  { "ad_type": "banner", "placement": "home", "fallback_enabled": true }
     * Force mode:     { "ad_type": "banner", "placement": "home", "fallback_enabled": false, "network_id": 1 }
     */
    public function upsert(Request $request): JsonResponse
    {
        $app = $request->attributes->get('external_app');

        $request->validate([
            'ad_type'          => ['required', 'string', 'in:' . implode(',', AdUnit::AD_TYPES)],
            'placement'        => ['required', 'string', 'max:50'],
            'fallback_enabled' => ['required', 'boolean'],
            'network_id'       => [
                'nullable', 'integer', 'exists:ad_networks,id',
                function ($attribute, $value, $fail) use ($request) {
                    if (!$request->boolean('fallback_enabled') && empty($value)) {
                        $fail('network_id is required when fallback_enabled is false.');
                    }
                },
            ],
        ]);

        $data    = $request->only(['ad_type', 'placement', 'fallback_enabled', 'network_id']);
        $setting = $this->settingService->upsert(
            $app->id,
            $data['ad_type'],
            $data['placement'],
            [
                'fallback_enabled' => $data['fallback_enabled'],
                'network_id'       => $data['fallback_enabled'] ? null : ($data['network_id'] ?? null),
            ]
        );

        return response()->json(['status' => 'ok', 'data' => $setting]);
    }

    /**
     * Setting মুছো — fallback mode এ ফিরে যাবে
     * DELETE /api/v1/external/settings/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $app     = $request->attributes->get('external_app');
        $setting = AdSetting::find($id);

        if (!$setting || $setting->app_id !== $app->id) {
            return response()->json(['status' => 'error', 'message' => 'Setting not found.'], 404);
        }

        $this->settingService->delete($id, $app->id, $setting->ad_type, $setting->placement);

        return response()->json(['status' => 'ok', 'message' => 'Setting deleted. Fallback mode restored.']);
    }
}
