<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * /api/v1/admin/limit-requests
 */
class LimitRequestController extends Controller
{
    public function __construct(
        private readonly AppLimitService $limitService
    ) {}

    /** GET /api/v1/admin/limit-requests?status=pending */
    public function index(Request $request): JsonResponse
    {
        $requests = $this->limitService->getAllRequests(
            $request->query('status')
        );

        return response()->json(['status' => 'ok', 'data' => $requests]);
    }

    /** POST /api/v1/admin/limit-requests/{id}/approve */
    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $admin   = JWTAuth::parseToken()->authenticate();
            $result  = $this->limitService->approve($id, $admin->id, $request->input('note'));
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['status' => 'ok', 'data' => $result]);
    }

    /** POST /api/v1/admin/limit-requests/{id}/reject */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $admin  = JWTAuth::parseToken()->authenticate();
            $result = $this->limitService->reject($id, $admin->id, $request->input('note'));
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['status' => 'ok', 'data' => $result]);
    }
}
