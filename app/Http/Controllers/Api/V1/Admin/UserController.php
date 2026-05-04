<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminUserService;
use App\Services\AppLimitService;
use App\Services\EmailService;
use App\Models\App;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $userService,
        private readonly AppLimitService  $limitService,
        private readonly EmailService     $emailService,
    ) {}

    /** GET /api/v1/admin/users */
    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->listUsers([
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ]);

        return response()->json([
            'status' => 'ok',
            'data'   => $users,
            'stats'  => $this->userService->getStats(),
        ]);
    }

    /** GET /api/v1/admin/users/{id} */
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getUser($id);

        return response()->json(['status' => 'ok', 'data' => $user]);
    }

    /** PATCH /api/v1/admin/users/{id}/status — activate/deactivate */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);

        $user = $this->userService->toggleStatus($id, (bool) $request->input('active'));

        return response()->json(['status' => 'ok', 'data' => $user]);
    }

    /** PATCH /api/v1/admin/users/{id}/app-limit */
    public function setAppLimit(Request $request, int $id): JsonResponse
    {
        $request->validate(['limit' => ['required', 'integer', 'min:1', 'max:100']]);

        $user = $this->userService->setAppLimit($id, (int) $request->input('limit'));

        return response()->json(['status' => 'ok', 'data' => $user]);
    }

    /** POST /api/v1/admin/users/{id}/send-email */
    public function sendEmail(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ]);

        $user = $this->userService->getUser($id);
        $log  = $this->emailService->sendToUser($user, $request->input('subject'), $request->input('body'));

        return response()->json([
            'status'  => 'ok',
            'message' => 'Email queued.',
            'log_id'  => $log->id,
        ]);
    }

    /**
     * DELETE /api/v1/admin/users/{id}
     * Remove a developer account permanently.
     * All their apps and data will be cascade deleted.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = $this->userService->getUser($id);

        // Safety check: cannot delete if user has active apps
        if ($user->apps()->where('status', App::STATUS_ACTIVE)->count() > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Cannot delete user with active apps. Deactivate their apps first.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'status'  => 'ok',
            'message' => 'Developer account permanently deleted.',
        ]);
    }
}
