<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DeveloperProfileService
{
    /**
     * Update developer profile fields.
     */
    public function updateProfile(User $user, array $data): User
    {
        $allowed = ['name', 'phone', 'company', 'website'];
        $user->update(array_intersect_key($data, array_flip($allowed)));

        return $user->fresh();
    }

    /**
     * Generate a 6-digit email verification code.
     * Stores hashed code in DB with 1-hour expiry.
     */
    public function generateVerificationCode(User $user): string
    {
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'email_verification_code'       => Hash::make($code),
            'email_verification_expires_at' => now()->addHour(),
        ]);

        return $code;
    }

    /**
     * Verify email using the 6-digit code.
     */
    public function verifyEmail(string $email, string $code): User
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new \RuntimeException('User not found.', 404);
        }

        if ($user->email_verified_at) {
            throw new \RuntimeException('Email is already verified.', 422);
        }

        if (!$user->email_verification_code) {
            throw new \RuntimeException('No verification code found. Please request a new one.', 422);
        }

        if (now()->gt($user->email_verification_expires_at)) {
            throw new \RuntimeException('Verification code has expired. Please request a new one.', 422);
        }

        if (!Hash::check($code, $user->email_verification_code)) {
            throw new \RuntimeException('Invalid verification code.', 422);
        }

        $user->update([
            'email_verified_at'             => now(),
            'email_verification_code'       => null,
            'email_verification_expires_at' => null,
        ]);

        return $user->fresh();
    }

    /**
     * Upload avatar image and return the stored path/URL.
     */
    public function uploadAvatar(User $user, UploadedFile $file): User
    {
        // Delete old avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $path = $file->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        return $user->fresh();
    }

    /**
     * Change password — requires current password verification.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw new \RuntimeException('Current password is incorrect.', 422);
        }

        if ($currentPassword === $newPassword) {
            throw new \RuntimeException('New password must be different from current password.', 422);
        }

        $user->update(['password' => Hash::make($newPassword)]);
    }

    /**
     * Generate a password reset token (for "forgot password" flow).
     * Stores token hash in DB with 1-hour expiry.
     * In production, email this token to the user.
     */
    public function generateResetToken(string $email): ?string
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Return null silently — don't reveal if email exists
            return null;
        }

        $token = Str::random(64);

        $user->update([
            'password_reset_token'      => Hash::make($token),
            'password_reset_expires_at' => now()->addHour(),
        ]);

        return $token;
    }

    /**
     * Reset password using a token.
     */
    public function resetPassword(string $email, string $token, string $newPassword): User
    {
        return DB::transaction(function () use ($email, $token, $newPassword) {
            $user = User::where('email', $email)->lockForUpdate()->first();

            if (!$user || !$user->password_reset_token) {
                throw new \RuntimeException('Invalid reset token.', 422);
            }

            if (now()->gt($user->password_reset_expires_at)) {
                throw new \RuntimeException('Reset token has expired. Please request a new one.', 422);
            }

            if (!Hash::check($token, $user->password_reset_token)) {
                throw new \RuntimeException('Invalid reset token.', 422);
            }

            $user->update([
                'password'                  => Hash::make($newPassword),
                'password_reset_token'      => null,
                'password_reset_expires_at' => null,
            ]);

            return $user->fresh();
        });
    }
}
