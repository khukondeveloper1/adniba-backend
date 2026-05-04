<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Services\AppSuspensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin can suspend/unsuspend any app.
 * Suspension immediately blocks all SDK requests from that app.
 * Developer receives an email notification.
 */
class AppSuspensionController extends Controller
{
    public function __construct(
        private readonly AppSuspensionService $suspensionService
    ) {}

    /**
     * POST /api/v1/admin/apps/{id}/suspend
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $app = App::with('user')->findOrFail($id);
        $admin = $request->attributes->get('admin');

        try {
            $app = $this->suspensionService->suspend(
                $app,
                $request->input('reason'),
                $admin?->id,
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 409);
        }

        return response()->json([
            'status'  => 'ok',
            'message' => 'App suspended. Developer has been notified.',
            'data'    => $app,
        ]);
    }

    /**
     * POST /api/v1/admin/apps/{id}/unsuspend
     */
    public function unsuspend(Request $request, int $id): JsonResponse
    {
        $app = App::with('user')->findOrFail($id);
        $admin = $request->attributes->get('admin');

        try {
            $app = $this->suspensionService->unsuspend($app, $admin?->id);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 409);
        }

        return response()->json([
            'status'  => 'ok',
            'message' => 'App reactivated. Developer has been notified.',
            'data'    => $app,
        ]);
    }
}
