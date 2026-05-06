<?php

namespace App\Http\Controllers\Api\V1\Developer;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use App\Services\DeveloperAuthService;
use App\Services\DeveloperProfileService;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/developer/auth/register
 * POST /api/v1/developer/auth/login
 * POST /api/v1/developer/auth/logout
 * POST /api/v1/developer/auth/verify-email
 * POST /api/v1/developer/auth/resend-verification
 * GET  /api/v1/developer/auth/me
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly DeveloperAuthService    $authService,
        private readonly DeveloperProfileService $profileService,
        private readonly EmailService            $emailService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'                  => ['required', 'string', 'max:100'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        [$result, $user, $code] = DB::transaction(function () use ($request) {
            $result = $this->authService->register($request->only('name', 'email', 'password'));

            // Generate verification code and send email
            $user = \App\Models\User::findOrFail($result['user']['id']);
            $code = $this->profileService->generateVerificationCode($user);

            return [$result, $user, $code];
        });

        $this->emailService->sendVerification($user, $code);
        $this->emailService->sendWelcome($user);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Registration successful. Please check your email for the verification code.',
            'data'    => $result,
        ], Response::HTTP_CREATED);
    }

    /**
     * POST /api/v1/developer/auth/verify-email
     * Verify email with 6-digit code sent during registration.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'string', 'size:6'],
        ]);

        try {
            $user = $this->profileService->verifyEmail(
                $request->input('email'),
                $request->input('code')
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }

        // Email verified — issue JWT token and send welcome email
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Email verified successfully.',
            'data'    => [
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => config('jwt.ttl') * 60,
                'user'         => [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'app_limit' => $user->app_limit,
                    'apps_used' => $user->apps()->count(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/developer/auth/resend-verification
     * Resend verification code if expired or not received.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = \App\Models\User::where('email', $request->input('email'))->first();

        if (!$user) {
            // Don't reveal if email exists
            return response()->json([
                'status'  => 'ok',
                'message' => 'If this email is registered, a new verification code has been sent.',
            ]);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Email is already verified.',
            ], 422);
        }

        $code = $this->profileService->generateVerificationCode($user);
        $this->emailService->sendVerification($user, $code);

        return response()->json([
            'status'  => 'ok',
            'message' => 'If this email is registered, a new verification code has been sent.',
        ]);
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

    public function refresh(): JsonResponse
    {
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
