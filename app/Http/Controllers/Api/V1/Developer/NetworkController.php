<?php

namespace App\Http\Controllers\Api\V1\Developer;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Services\AdNetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NetworkController extends Controller
{
    public function __construct(
        private readonly AdNetworkService $networkService
    ) {}

    public function index(Request $request, int $appId): JsonResponse
    {
        $this->assertOwnership($request, $appId);

        return response()->json([
            'status' => 'ok',
            'data'   => $this->networkService->getForApp($appId),
        ]);
    }

    public function store(Request $request, int $appId): JsonResponse
    {
        $this->assertOwnership($request, $appId);

        $request->validate([
            'name'    => ['required', 'string', 'in:admob,meta,unity'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        try {
            $network = $this->networkService->createNetwork($appId, $request->only([
                        'name',
                'enabled',
            ]));
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ok', 'data' => $network], Response::HTTP_CREATED);
    }

    public function toggle(Request $request, int $appId, int $id): JsonResponse
    {
        $this->assertOwnership($request, $appId);
        $request->validate(['enabled' => ['required', 'boolean']]);

        $network = $this->networkService->toggleNetwork($id, (bool) $request->input('enabled'));

        return response()->json(['status' => 'ok', 'data' => $network]);
    }

    public function destroy(Request $request, int $appId, int $id): JsonResponse
    {
        $this->assertOwnership($request, $appId);
        $this->networkService->deleteNetwork($id);

        return response()->json(['status' => 'ok', 'message' => 'Network removed.']);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function assertOwnership(Request $request, int $appId): void
    {
        $user   = $request->attributes->get('developer');
        $exists = App::where('id', $appId)->where('user_id', $user->id)->exists();

        if (!$exists) {
            abort(404, 'App not found.');
        }
    }
}
