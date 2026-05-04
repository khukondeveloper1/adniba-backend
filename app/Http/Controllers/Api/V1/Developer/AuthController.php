<?php

namespace App\Http\Controllers\Api\V1\Developer;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use App\Services\DeveloperAuthService;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/developer/auth/register
 * POST /api/v1/developer/auth/login
 * POST /api/v1/developer/auth/logout
 * GET  /api/v1/developer/auth/me
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly DeveloperAuthService $authService,
        private readonly EmailService         $emailService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'                  => ['required', 'string', 'max:100'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        $result = $this->authService->register($request->only('name', 'email', 'password'));

        // Send welcome email async
        $user = \App\Models\User::find($result['user']['id']);
        $this->emailService->sendWelcome($user);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Registration successful.',
            'data'    => $result,
        ], Response::HTTP_CREATED);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->authService->login(
                $request->input('email'),
                $request->input('password')
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 401);
        }

        return response()->json(['status' => 'ok', 'data' => $result]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('developer');

        return response()->json([
            'status' => 'ok',
            'data'   => [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'app_limit'        => $user->app_limit,
                'apps_used'        => $user->apps()->count(),
                'remaining_slots'  => $user->remainingAppSlots(),
                'created_at'       => $user->created_at,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json(['status' => 'ok', 'message' => 'Logged out.']);
    }

    public function refresh(): JsonResponse{
    try {
        $newToken = JWTAuth::parseToken()->refresh();
    } catch (\Throwable $e) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Could not refresh token.',
        ], 401);
    }

    return response()->json([
        'status'       => 'ok',
        'access_token' => $newToken,
        'token_type'   => 'bearer',
        'expires_in'   => config('jwt.ttl') * 60,
    ]);
}




    





}
