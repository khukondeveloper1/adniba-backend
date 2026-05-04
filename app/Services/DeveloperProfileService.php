<?php

namespace App\Services;

use App\Models\User;
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
    public function resetPassword(string $email, string $token, string $newPassword): void
    {
        $user = User::where('email', $email)->first();

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
    }
}
