<?php

namespace App\Http\Controllers\Api\V1\Developer;

use App\Http\Controllers\Controller;
use App\Models\AdSetting;
use App\Models\AdUnit;
use App\Models\App;
use App\Services\AdSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(
        private readonly AdSettingService $settingService
    ) {}

    public function index(Request $request, int $appId): JsonResponse
    {
        $this->assertOwnership($request, $appId);

        return response()->json([
            'status' => 'ok',
            'data'   => $this->settingService->getForApp($appId),
        ]);
    }

    public function showPlacement(Request $request, int $appId, string $adType, string $placement): JsonResponse
    {
        $this->assertOwnership($request, $appId);

        $setting = $this->settingService->getForPlacement($appId, $adType, $placement);

        return response()->json([
            'status' => 'ok',
            'data'   => $setting ?? [
                'app_id'           => $appId,
                'ad_type'          => $adType,
                'placement'        => $placement,
                'fallback_enabled' => true,
                'network_id'       => null,
            ],
        ]);
    }

    public function upsert(Request $request, int $appId): JsonResponse
    {
        $this->assertOwnership($request, $appId);

        $request->validate([
            'ad_type'          => ['required', 'string', 'in:' . implode(',', AdUnit::AD_TYPES)],
            'placement'        => ['required', 'string', 'max:50'],
            'fallback_enabled' => ['required', 'boolean'],
            'network_id'       => ['nullable', 'integer', 'exists:ad_networks,id'],
        ]);

        $data = $request->only([
    'ad_type', 'placement', 'fallback_enabled', 'network_id',
]);
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

    public function destroy(Request $request, int $appId, int $id): JsonResponse
    {
        $this->assertOwnership($request, $appId);

        $setting = AdSetting::find($id);

        if (!$setting || $setting->app_id !== $appId) {
            return response()->json(['status' => 'error', 'message' => 'Not found.'], 404);
        }

        $this->settingService->delete($id, $appId, $setting->ad_type, $setting->placement);

        return response()->json(['status' => 'ok', 'message' => 'Setting deleted.']);
    }

    private function assertOwnership(Request $request, int $appId): void
    {
        $user   = $request->attributes->get('developer');
        $exists = App::where('id', $appId)->where('user_id', $user->id)->exists();

        if (!$exists) {
            abort(404, 'App not found.');
        }
    }
}
