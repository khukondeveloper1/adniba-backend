<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Admin user not found.',
                ], Response::HTTP_UNAUTHORIZED);
            }
        } catch (TokenExpiredException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token has expired.',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (TokenInvalidException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token is invalid.',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (JWTException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token is absent.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set('admin', $user);

        return $next($request);
    }
}
