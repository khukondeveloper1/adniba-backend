<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class DeveloperAuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'status'    => 1,
            'app_limit' => 3,
        ]);

        // No JWT token until email is verified
        return [
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ];
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \RuntimeException('Invalid credentials.', 401);
        }

        if (!$user->isActive()) {
            throw new \RuntimeException('Your account has been deactivated.', 403);
        }

        if (!$user->email_verified_at) {
            throw new \RuntimeException('Please verify your email before logging in.', 403);
        }

        // ✅ User model দিয়ে সরাসরি token তৈরি
        $token = JWTAuth::fromUser($user);

        return $this->tokenResponse($token, $user);
    }

    public function logout(): void
    {
        JWTAuth::parseToken()->invalidate();
    }

    private function tokenResponse(string $token, User $user): array
    {
        return [
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
        ];
    }
}