<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Admin manages static content pages.
 * /api/v1/admin/pages
 */
class StaticPageController extends Controller
{
    /** GET /api/v1/admin/pages */
    public function index(): JsonResponse
    {
        $pages = StaticPage::orderBy('key')->get();

        return response()->json(['status' => 'ok', 'data' => $pages]);
    }

    /** GET /api/v1/admin/pages/{key} */
    public function show(string $key): JsonResponse
    {
        $page = StaticPage::where('key', $key)->firstOrFail();

        return response()->json(['status' => 'ok', 'data' => $page]);
    }

    /** PUT /api/v1/admin/pages/{key} — create or update */
    public function upsert(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'title'        => ['required', 'string', 'max:200'],
            'content_html' => ['required', 'string'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        if (!in_array($key, StaticPage::KEYS)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid page key. Allowed: ' . implode(', ', StaticPage::KEYS),
            ], 422);
        }

        $admin = JWTAuth::parseToken()->authenticate();

        $page = StaticPage::updateOrCreate(
            ['key' => $key],
            [
                'title'        => $request->input('title'),
                'content_html' => $request->input('content_html'),
                'is_published' => $request->input('is_published', true),
                'updated_by'   => $admin->id,
            ]
        );

        return response()->json(['status' => 'ok', 'data' => $page]);
    }
}
