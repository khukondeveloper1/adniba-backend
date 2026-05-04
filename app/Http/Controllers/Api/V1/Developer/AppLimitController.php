<?php

namespace App\Http\Controllers\Api\V1\Developer;

use App\Http\Controllers\Controller;
use App\Services\AppLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /api/v1/developer/limit-requests
 */
class AppLimitController extends Controller
{
    public function __construct(
        private readonly AppLimitService $limitService
    ) {}

    /** GET /api/v1/developer/limit-requests — my requests */
    public function index(Request $request): JsonResponse
    {
        $user     = $request->attributes->get('developer');
        $requests = $user->limitRequests()->orderByDesc('created_at')->get();

        return response()->json(['status' => 'ok', 'data' => $requests]);
    }

    /** POST /api/v1/developer/limit-requests — submit new request */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'requested_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'reason'          => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->attributes->get('developer');

        try {
            $limitRequest = $this->limitService->submitRequest(
                $user,
                (int) $request->input('requested_limit'),
                $request->input('reason')
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }

        return response()->json(
            ['status' => 'ok', 'data' => $limitRequest],
            Response::HTTP_CREATED
        );
    }
}
