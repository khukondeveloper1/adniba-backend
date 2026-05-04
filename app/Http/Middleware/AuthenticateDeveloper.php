<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticateDeveloper
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Token parse করো — validate করে
            $token   = JWTAuth::parseToken();
            $payload = $token->getPayload();

            // Token এর ভেতর থেকে role ও sub বের করো
            $role   = $payload->get('role');   // 'developer'
            $userId = $payload->get('sub');    // user id

            // Admin token দিয়ে developer route block করো
            if ($role !== 'developer') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // users table থেকে খোঁজো — admin_users নয়
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not found.',
                ], 401);
            }

            if (!$user->isActive()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Your account is deactivated.',
                ], 403);
            }

            $request->attributes->set('developer', $user);

        } catch (TokenExpiredException) {
            return response()->json(['status' => 'error', 'message' => 'Token expired.'], 401);
        } catch (TokenInvalidException) {
            return response()->json(['status' => 'error', 'message' => 'Token invalid.'], 401);
        } catch (JWTException) {
            return response()->json(['status' => 'error', 'message' => 'Token absent.'], 401);
        }

        return $next($request);
    }
}