<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdUnitRequest;
use App\Services\AdUnitService;
use App\Services\AdConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource: /api/v1/admin/apps/{appId}/units
 */
class AdUnitController extends Controller
{
    public function __construct(
        private readonly AdUnitService   $unitService,
        private readonly AdConfigService $configService
    ) {}

    /** GET /api/v1/admin/apps/{appId}/units */
    public function index(int $appId): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data'   => $this->unitService->getForApp($appId),
        ]);
    }

    /** GET /api/v1/admin/apps/{appId}/units/{id} */
    public function show(int $appId, int $id): JsonResponse
    {
        $unit = $this->unitService->findById($id);

        if (!$unit || $unit->app_id !== $appId) {
            return response()->json(['status' => 'error', 'message' => 'Not found.'], 404);
        }

        return response()->json(['status' => 'ok', 'data' => $unit]);
    }

    /** POST /api/v1/admin/apps/{appId}/units */
    public function store(CreateAdUnitRequest $request, int $appId): JsonResponse
    {
        $unit = $this->unitService->createUnit($appId, $request->validated());

        // Bust cache for the new placement
        $this->configService->bustCache($appId, $unit->ad_type, $unit->placement);

        return response()->json(
            ['status' => 'ok', 'data' => $unit],
            Response::HTTP_CREATED
        );
    }

    /** PUT /api/v1/admin/apps/{appId}/units/{id} */
    public function update(Request $request, int $appId, int $id): JsonResponse
    {
        $request->validate([
            'unit_id'  => ['sometimes', 'string', 'max:200'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'enabled'  => ['sometimes', 'boolean'],
        ]);

        $unit = $this->unitService->updateUnit($id, $request->validated());

        $this->configService->bustCache($appId, $unit->ad_type, $unit->placement);

        return response()->json(['status' => 'ok', 'data' => $unit]);
    }

    /** PATCH /api/v1/admin/apps/{appId}/units/{id}/toggle */
    public function toggle(Request $request, int $appId, int $id): JsonResponse
    {
        $request->validate(['enabled' => ['required', 'boolean']]);

        $unit = $this->unitService->toggleUnit($id, (bool) $request->input('enabled'));

        $this->configService->bustCache($appId, $unit->ad_type, $unit->placement);

        return response()->json(['status' => 'ok', 'data' => $unit]);
    }

    /** DELETE /api/v1/admin/apps/{appId}/units/{id} */
    public function destroy(int $appId, int $id): JsonResponse
    {
        $unit = $this->unitService->findById($id);

        if ($unit) {
            $this->unitService->deleteUnit($id);
            $this->configService->bustCache($appId, $unit->ad_type, $unit->placement);
        }

        return response()->json(['status' => 'ok', 'message' => 'Ad unit deleted.']);
    }
}
