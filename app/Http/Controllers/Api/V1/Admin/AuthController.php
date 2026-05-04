<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\AdminUser;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/admin/auth/login
 * POST /api/v1/admin/auth/refresh
 * POST /api/v1/admin/auth/logout
 */
class AuthController extends Controller
{
    /**
     * Authenticate admin and issue a JWT.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $admin = AdminUser::where('username', $request->input('username'))->first();

        if (!$admin || !Hash::check($request->input('password'), $admin->password_hash)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = JWTAuth::fromUser($admin);

        return response()->json([
            'status'       => 'ok',
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60, // seconds
        ]);
    }

    /**
     * Rotate the current JWT without requiring credentials again.
     */
    public function refresh(): JsonResponse
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not refresh token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'status'       => 'ok',
            'access_token' => $newToken,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60,
        ]);
    }

    /**
     * Invalidate the current JWT (server-side blacklist).
     */
    public function logout(): JsonResponse
    {
        JWTAuth::parseToken()->invalidate();

        return response()->json(['status' => 'ok', 'message' => 'Logged out.']);
    }
}
