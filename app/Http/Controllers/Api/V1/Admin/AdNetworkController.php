<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateNetworkRequest;
use App\Services\AdNetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource: /api/v1/admin/apps/{appId}/networks
 */
class AdNetworkController extends Controller
{
    public function __construct(
        private readonly AdNetworkService $networkService
    ) {}

    /** GET /api/v1/admin/apps/{appId}/networks */
    public function index(int $appId): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data'   => $this->networkService->getForApp($appId),
        ]);
    }

    /** POST /api/v1/admin/apps/{appId}/networks */
    public function store(CreateNetworkRequest $request, int $appId): JsonResponse
    {
        try {
            $network = $this->networkService->createNetwork($appId, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
        $httpCode = ($e->getCode() >= 100 && $e->getCode() <= 599)
        ? $e->getCode()
        : 409;

    return response()->json(
        ['status' => 'error', 'message' => $e->getMessage()],
        $httpCode
    );
}

        return response()->json(
            ['status' => 'ok', 'data' => $network],
            Response::HTTP_CREATED
        );
    }

    /** PATCH /api/v1/admin/apps/{appId}/networks/{id}/toggle */
    public function toggle(Request $request, int $appId, int $id): JsonResponse
    {
        $request->validate(['enabled' => ['required', 'boolean']]);

        $network = $this->networkService->toggleNetwork($id, (bool) $request->input('enabled'));

        return response()->json(['status' => 'ok', 'data' => $network]);
    }

    /** DELETE /api/v1/admin/apps/{appId}/networks/{id} */
    public function destroy(int $appId, int $id): JsonResponse
    {
        $this->networkService->deleteNetwork($id);

        return response()->json(['status' => 'ok', 'message' => 'Network removed.']);
    }
}
