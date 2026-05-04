<?php

namespace App\Http\Middleware;

use App\Services\AppService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSdkKey
{
    public function __construct(
        private readonly AppService $appService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('x-api-key');

        if (empty($apiKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Missing x-api-key header.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $app = $this->appService->resolveFromApiKey($apiKey);

        if (!$app) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($app->isSuspended()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'App is suspended. Contact support.',
                'reason'  => $app->suspension_reason,
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$app->isActive()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'App is inactive.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('sdk_app', $app);

        return $next($request);
    }
}
