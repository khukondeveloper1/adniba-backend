<?php

use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\AuthenticateDeveloper;
use App\Http\Middleware\AuthenticateExternalKey;
use App\Http\Middleware\AuthenticateSdkKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.admin'     => AuthenticateAdmin::class,
            'auth.sdk'       => AuthenticateSdkKey::class,
            'auth.developer' => AuthenticateDeveloper::class,
            'auth.external'  => AuthenticateExternalKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->render(function (
            \Illuminate\Database\UniqueConstraintViolationException $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Duplicate entry — এই data আগেই আছে।',
                ], 409);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['status' => 'error', 'message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $e->getMessage() ?: 'HTTP error.',
                ], $e->getStatusCode());
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn(Request $request, \Throwable $e) => $request->is('api/*')
        );
    })
    ->create();
