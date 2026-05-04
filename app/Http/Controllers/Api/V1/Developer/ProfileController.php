<?php

namespace App\Http\Controllers\Api\V1\Developer;

use App\Http\Controllers\Controller;
use App\Services\DeveloperProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Developer profile management.
 * /api/v1/developer/profile
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly DeveloperProfileService $profileService
    ) {}

    /** GET /api/v1/developer/profile */
    public function show(Request $request): JsonResponse
    {
        $user = $request->attributes->get('developer');
        $user->loadCount('apps');

        return response()->json([
            'status' => 'ok',
            'data'   => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'company'        => $user->company,
                'website'        => $user->website,
                'avatar'         => $user->avatar
                    ? asset('storage/' . $user->avatar)
                    : null,
                'app_limit'      => $user->app_limit,
                'apps_used'      => $user->apps_count,
                'remaining_slots'=> max(0, $user->app_limit - $user->apps_count),
                'status'         => $user->status,
                'created_at'     => $user->created_at,
            ],
        ]);
    }

    /** PUT /api/v1/developer/profile */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name'    => ['sometimes', 'string', 'max:100'],
            'phone'   => ['nullable', 'string', 'max:20'],
            'company' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        $user    = $request->attributes->get('developer');
        $updated = $this->profileService->updateProfile($user, $request->only([
            'name', 'phone', 'company', 'website',
        ]));

        return response()->json(['status' => 'ok', 'data' => $updated]);
    }

    /**
     * POST /api/v1/developer/profile/avatar
     * Upload profile picture (multipart/form-data, field: avatar)
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user    = $request->attributes->get('developer');
        $updated = $this->profileService->uploadAvatar($user, $request->file('avatar'));

        return response()->json([
            'status'     => 'ok',
            'avatar_url' => asset('storage/' . $updated->avatar),
        ]);
    }

    /** DELETE /api/v1/developer/profile/avatar */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->attributes->get('developer');

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return response()->json(['status' => 'ok', 'message' => 'Avatar removed.']);
    }

    /**
     * POST /api/v1/developer/profile/change-password
     * Requires current password verification.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => ['required', 'string'],
            'new_password'          => ['required', 'string', 'min:8', 'confirmed'],
            'new_password_confirmation' => ['required'],
        ]);

        $user = $request->attributes->get('developer');

        try {
            $this->profileService->changePassword(
                $user,
                $request->input('current_password'),
                $request->input('new_password')
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }

        return response()->json([
            'status'  => 'ok',
            'message' => 'Password changed successfully.',
        ]);
    }
}
