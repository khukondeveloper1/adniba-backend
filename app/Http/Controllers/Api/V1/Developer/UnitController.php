<?php

namespace App\Http\Controllers\Api\V1\Developer;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AdUnit;
use App\Services\AdUnitService;
use App\Services\AdConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UnitController extends Controller
{
    public function __construct(
        private readonly AdUnitService   $unitService,
        private readonly AdConfigService $configService,
    ) {}

    public function index(Request $request, int $appId): JsonResponse
    {
        $this->assertOwnership($request, $appId);

        return response()->json([
            'status' => 'ok',
            'data'   => $this->unitService->getForApp($appId),
        ]);
    }

    public function show(Request $request, int $appId, int $id): JsonResponse
    {
        $this->assertOwnership($request, $appId);
        $unit = $this->unitService->findById($id);

        if (!$unit || $unit->app_id !== $appId) {
            return response()->json(['status' => 'error', 'message' => 'Not found.'], 404);
        }

        return response()->json(['status' => 'ok', 'data' => $unit]);
    }

    public function store(Request $request, int $appId): JsonResponse
    {
        $this->assertOwnership($request, $appId);

        $request->validate([
            'network_id' => ['required', 'integer', 'exists:ad_networks,id'],
            'ad_type'    => ['required', 'string', 'in:' . implode(',', AdUnit::AD_TYPES)],
            'placement'  => ['required', 'string', 'max:50'],
            'unit_id'    => ['required', 'string', 'max:200'],
            'priority'   => ['sometimes', 'integer', 'min:1', 'max:100'],
            'enabled'    => ['sometimes', 'boolean'],
        ]);

        $unit = $this->unitService->createUnit($appId, $request->only([
    'network_id', 'ad_type', 'placement', 'unit_id', 'priority', 'enabled',
]));
        $this->configService->bustCache($appId, $unit->ad_type, $unit->placement);

        return response()->json(['status' => 'ok', 'data' => $unit], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $appId, int $id): JsonResponse
    {
        $this->assertOwnership($request, $appId);

        $request->validate([
            'unit_id'  => ['sometimes', 'string', 'max:200'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'enabled'  => ['sometimes', 'boolean'],
        ]);

        $unit = $this->unitService->updateUnit($id, $request->only([
    'unit_id', 'priority', 'enabled',
]));
        $this->configService->bustCache($appId, $unit->ad_type, $unit->placement);

        return response()->json(['status' => 'ok', 'data' => $unit]);
    }

    public function toggle(Request $request, int $appId, int $id): JsonResponse
    {
        $this->assertOwnership($request, $appId);
        $request->validate(['enabled' => ['required', 'boolean']]);

        $unit = $this->unitService->toggleUnit($id, (bool) $request->input('enabled'));
        $this->configService->bustCache($appId, $unit->ad_type, $unit->placement);

        return response()->json(['status' => 'ok', 'data' => $unit]);
    }

    public function destroy(Request $request, int $appId, int $id): JsonResponse
    {
        $this->assertOwnership($request, $appId);
        $unit = $this->unitService->findById($id);

        if ($unit) {
            $this->unitService->deleteUnit($id);
            $this->configService->bustCache($appId, $unit->ad_type, $unit->placement);
        }

        return response()->json(['status' => 'ok', 'message' => 'Ad unit deleted.']);
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
