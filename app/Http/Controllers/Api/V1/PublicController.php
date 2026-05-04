<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiDoc;
use App\Models\StaticPage;
use App\Models\GlobalAdNetwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public endpoints — no authentication required.
 * Frontend can call these freely.
 *
 * /api/v1/public/*
 */
class PublicController extends Controller
{
    /** GET /api/v1/public/pages — all published static pages */
    public function pages(): JsonResponse
    {
        $pages = StaticPage::where('is_published', 1)
            ->select('key', 'title', 'content_html', 'updated_at')
            ->orderBy('key')
            ->get();

        return response()->json(['status' => 'ok', 'data' => $pages]);
    }

    /** GET /api/v1/public/pages/{key} — single static page */
    public function page(string $key): JsonResponse
    {
        $page = StaticPage::where('key', $key)
            ->where('is_published', 1)
            ->first();

        if (!$page) {
            return response()->json(['status' => 'error', 'message' => 'Page not found.'], 404);
        }

        return response()->json(['status' => 'ok', 'data' => $page]);
    }

    /** GET /api/v1/public/api-docs — all published API docs */
    public function apiDocs(Request $request): JsonResponse
    {
        $docs = ApiDoc::published()
            ->when(
                $request->query('category'),
                fn ($q) => $q->where('category', $request->query('category'))
            )
            ->select('id', 'title', 'slug', 'category', 'content_html', 'sort_order', 'updated_at')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['status' => 'ok', 'data' => $docs]);
    }

    /** GET /api/v1/public/api-docs/{slug} — single doc by slug */
    public function apiDoc(string $slug): JsonResponse
    {
        $doc = ApiDoc::published()
            ->where('slug', $slug)
            ->first();

        if (!$doc) {
            return response()->json(['status' => 'error', 'message' => 'Doc not found.'], 404);
        }

        return response()->json(['status' => 'ok', 'data' => $doc]);
    }

    /** GET /api/v1/public/networks — available networks developers can add */
    public function availableNetworks(): JsonResponse
    {
        $networks = GlobalAdNetwork::active()->get();

        return response()->json(['status' => 'ok', 'data' => $networks]);
    }
}
