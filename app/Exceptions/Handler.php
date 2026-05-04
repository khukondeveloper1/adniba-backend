<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Force all API responses to JSON — no HTML error pages.
     */
    public function render($request, Throwable $e): JsonResponse|\Illuminate\Http\Response
    {
        // Always return JSON for /api/* routes
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->renderApiException($e);
        }

        return parent::render($request, $e);
    }

    private function renderApiException(Throwable $e): JsonResponse
    {
        // 422 Validation
        if ($e instanceof ValidationException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        // 404
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Resource not found.',
            ], 404);
        }

        // 401 Unauthenticated
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Generic HTTP exceptions (403, 429, etc.)
        if ($e instanceof HttpException) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage() ?: 'HTTP error.',
            ], $e->getStatusCode());
        }

        // Business-logic RuntimeException with explicit code
        if ($e instanceof \RuntimeException && $e->getCode() >= 400) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        // Unhandled — 500
        $debug = config('app.debug');

        return response()->json([
            'status'  => 'error',
            'message' => $debug ? $e->getMessage() : 'Internal server error.',
            'trace'   => $debug ? collect(explode("\n", $e->getTraceAsString()))->take(10) : [],
        ], 500);
    }
}
