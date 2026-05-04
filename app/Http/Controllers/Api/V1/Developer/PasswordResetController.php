<?php

namespace App\Http\Controllers\Api\V1\Developer;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailJob;
use App\Models\User;
use App\Services\DeveloperProfileService;
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
        private readonly DeveloperProfileService $profileService
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

            SendEmailJob::dispatch(
                userId:  $user->id,
                toEmail: $user->email,
                subject: 'Reset Your AdNiba Password',
                type:    'custom',
                data:    [
                    'name' => $user->name,
                    'body' => "
                        <h2>Password Reset Request</h2>
                        <p>You requested a password reset for your AdNiba account.</p>
                        <p>Your password reset token is:</p>
                        <pre style='background:#f4f4f4;padding:15px;font-size:18px;'>{$token}</pre>
                        <p>This token expires in <strong>1 hour</strong>.</p>
                        <p>If you did not request this, please ignore this email.</p>
                    ",
                ]
            )->onQueue('default');
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
            $this->profileService->resetPassword(
                $request->input('email'),
                $request->input('token'),
                $request->input('password')
            );
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
