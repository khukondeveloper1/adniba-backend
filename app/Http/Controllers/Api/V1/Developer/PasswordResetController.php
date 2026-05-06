<?php

namespace App\Http\Controllers\Api\V1\Developer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeveloperProfileService;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Password reset flow for developers (forgot password).
 * Public endpoints — no auth required.
 *
 * /api/v1/developer/auth/forgot-password
 * /api/v1/developer/auth/reset-password
 */
class PasswordResetController extends Controller
{
    public function __construct(
        private readonly DeveloperProfileService $profileService,
        private readonly EmailService $emailService,
    ) {}

    /**
     * POST /api/v1/developer/auth/forgot-password
     * Developer enters their email → reset token is generated → email is sent
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $token = $this->profileService->generateResetToken($request->input('email'));

        // Always return same response to prevent email enumeration
        if ($token) {
            $user = User::where('email', $request->input('email'))->first();

            if ($user) {
                $this->emailService->sendPasswordReset($user, $token);
            }
        }

        return response()->json([
            'status'  => 'ok',
            'message' => 'If this email is registered, a reset link has been sent.',
        ]);
    }

    /**
     * POST /api/v1/developer/auth/reset-password
     * Developer enters email + token + new password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'                 => ['required', 'email'],
            'token'                 => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        try {
            $user = $this->profileService->resetPassword(
                $request->input('email'),
                $request->input('token'),
                $request->input('password')
            );

            $this->emailService->sendPasswordResetSuccess($user);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'status'  => 'ok',
            'message' => 'Password reset successfully. You can now login.',
        ]);
    }
}
