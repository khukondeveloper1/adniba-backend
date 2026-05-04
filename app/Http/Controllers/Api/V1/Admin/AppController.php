<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAppRequest;
use App\Http\Requests\Admin\UpdateAppRequest;
use App\Services\AppEventService;
use App\Services\AppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource: /api/v1/admin/apps
 */
class AppController extends Controller
{
    public function __construct(
        private readonly AppService $appService
    ) {}

    /** GET /api/v1/admin/apps */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data'   => $this->appService->listApps(),
        ]);
    }

    /** GET /api/v1/admin/apps/{id} */
    public function show(int $id): JsonResponse
    {
        $app = $this->appService->getApp($id);

        return response()->json(['status' => 'ok', 'data' => $app]);
    }

    /** POST /api/v1/admin/apps */
    public function store(CreateAppRequest $request): JsonResponse
    {
        $app = $this->appService->createApp($request->validated());

        return response()->json(
            ['status' => 'ok', 'data' => $app],
            Response::HTTP_CREATED
        );
    }

    /** PUT /api/v1/admin/apps/{id} */
    public function update(UpdateAppRequest $request, int $id): JsonResponse
    {
        $app = $this->appService->updateApp($id, $request->validated());

        return response()->json(['status' => 'ok', 'data' => $app]);
    }

    /** DELETE /api/v1/admin/apps/{id} */
    public function destroy(int $id): JsonResponse
    {
        $this->appService->deleteApp($id);

        return response()->json(['status' => 'ok', 'message' => 'App deleted.']);
    }

    /** POST /api/v1/admin/apps/{id}/rotate-key */
    public function rotateKey(int $id): JsonResponse
    {
        $app = $this->appService->rotateApiKey($id);

        return response()->json([
            'status'  => 'ok',
            'api_key' => $app->api_key,
        ]);
    }

    /** PATCH /api/v1/admin/apps/{id}/status */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);

        $admin = $request->attributes->get('admin');

        try {
            $app = $this->appService->setAppStatus(
                $id,
                (bool) $request->input('active'),
                AppEventService::ACTOR_ADMIN,
                $admin?->id,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['status' => 'ok', 'data' => $app]);
    }

    /** PATCH /api/v1/admin/apps/{id}/ads-enabled */
    public function toggleAds(Request $request, int $id): JsonResponse
    {
        $request->validate(['enabled' => ['required', 'boolean']]);

        $admin = $request->attributes->get('admin');

        $app = $this->appService->setAdEnabledByActor(
            $id,
            (bool) $request->input('enabled'),
            AppEventService::ACTOR_ADMIN,
            $admin?->id,
        );

        return response()->json(['status' => 'ok', 'data' => $app]);
    }

    /** GET /api/v1/admin/apps/{id}/events */
    public function events(int $id): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data'   => $this->appService->listEvents($id),
        ]);
    }
}
