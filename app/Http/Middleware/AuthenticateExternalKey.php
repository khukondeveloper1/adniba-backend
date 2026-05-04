<?php

namespace App\Http\Middleware;

use App\Models\App;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateExternalKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('x-api-key');

        if (empty($apiKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Missing x-api-key header.',
                'hint'    => 'Use your App API key from the AdNiba dashboard.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $app = App::where('api_key', $apiKey)->first();

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
                'message' => 'This app is inactive.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('external_app', $app);

        return $next($request);
    }
}
