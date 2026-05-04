<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Models\AdUnit;
use App\Services\AdConfigService;
use App\Services\AdUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /api/v1/external/units
 *
 * Developer নিজের custom panel থেকে ad unit manage করতে পারবে।
 * Auth: x-api-key (App API Key)
 */
class UnitController extends Controller
{
    public function __construct(
        private readonly AdUnitService   $unitService,
        private readonly AdConfigService $configService,
    ) {}

    /**
     * সব units list
     * GET /api/v1/external/units
     * GET /api/v1/external/units?ad_type=banner&placement=home (filter)
     */
    public function index(Request $request): JsonResponse
    {
        $app   = $request->attributes->get('external_app');
        $units = $this->unitService->getForApp($app->id);

        // Optional filter
        if ($request->has('ad_type')) {
            $units = $units->where('ad_type', $request->query('ad_type'));
        }
        if ($request->has('placement')) {
            $units = $units->where('placement', $request->query('placement'));
        }

        return response()->json([
            'status' => 'ok',
            'app'    => ['id' => $app->id, 'name' => $app->name],
            'data'   => $units->values(),
        ]);
    }

    /**
     * একটা unit দেখো
     * GET /api/v1/external/units/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $app  = $request->attributes->get('external_app');
        $unit = $this->unitService->findById($id);

        if (!$unit || $unit->app_id !== $app->id) {
            return response()->json(['status' => 'error', 'message' => 'Unit not found.'], 404);
        }

        return response()->json(['status' => 'ok', 'data' => $unit]);
    }

    /**
     * নতুন unit তৈরি
     * POST /api/v1/external/units
     */
    public function store(Request $request): JsonResponse
    {
        $app = $request->attributes->get('external_app');

        $request->validate([
            'network_id' => ['required', 'integer', 'exists:ad_networks,id'],
            'ad_type'    => ['required', 'string', 'in:' . implode(',', AdUnit::AD_TYPES)],
            'placement'  => ['required', 'string', 'max:50'],
            'unit_id'    => ['required', 'string', 'max:200'],
            'priority'   => ['sometimes', 'integer', 'min:1', 'max:100'],
            'enabled'    => ['sometimes', 'boolean'],
        ]);

        $unit = $this->unitService->createUnit($app->id, $request->only([
            'network_id', 'ad_type', 'placement', 'unit_id', 'priority', 'enabled',
        ]));

        $this->configService->bustCache($app->id, $unit->ad_type, $unit->placement);

        return response()->json(
            ['status' => 'ok', 'data' => $unit],
            Response::HTTP_CREATED
        );
    }

    /**
     * Unit আপডেট
     * PUT /api/v1/external/units/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $app  = $request->attributes->get('external_app');
        $unit = $this->unitService->findById($id);

        if (!$unit || $unit->app_id !== $app->id) {
            return response()->json(['status' => 'error', 'message' => 'Unit not found.'], 404);
        }

        $request->validate([
            'unit_id'  => ['sometimes', 'string', 'max:200'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'enabled'  => ['sometimes', 'boolean'],
        ]);

        $updated = $this->unitService->updateUnit($id, $request->only([
            'unit_id', 'priority', 'enabled',
        ]));

        $this->configService->bustCache($app->id, $updated->ad_type, $updated->placement);

        return response()->json(['status' => 'ok', 'data' => $updated]);
    }

    /**
     * Unit enable/disable toggle
     * PATCH /api/v1/external/units/{id}/toggle
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $app  = $request->attributes->get('external_app');
        $unit = $this->unitService->findById($id);

        if (!$unit || $unit->app_id !== $app->id) {
            return response()->json(['status' => 'error', 'message' => 'Unit not found.'], 404);
        }

        $request->validate(['enabled' => ['required', 'boolean']]);

        $updated = $this->unitService->toggleUnit($id, (bool) $request->input('enabled'));
        $this->configService->bustCache($app->id, $updated->ad_type, $updated->placement);

        return response()->json(['status' => 'ok', 'data' => $updated]);
    }

    /**
     * Unit মুছো
     * DELETE /api/v1/external/units/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $app  = $request->attributes->get('external_app');
        $unit = $this->unitService->findById($id);

        if (!$unit || $unit->app_id !== $app->id) {
            return response()->json(['status' => 'error', 'message' => 'Unit not found.'], 404);
        }

        $this->unitService->deleteUnit($id);
        $this->configService->bustCache($app->id, $unit->ad_type, $unit->placement);

        return response()->json(['status' => 'ok', 'message' => 'Unit deleted.']);
    }
}
