<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiDoc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Admin creates and manages External API documentation pages.
 * Content is posted as HTML.
 *
 * /api/v1/admin/api-docs
 */
class ApiDocController extends Controller
{
    /** GET /api/v1/admin/api-docs */
    public function index(Request $request): JsonResponse
    {
        $docs = ApiDoc::with('creator')
            ->when($request->query('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['status' => 'ok', 'data' => $docs]);
    }

    /** GET /api/v1/admin/api-docs/{id} */
    public function show(int $id): JsonResponse
    {
        $doc = ApiDoc::with(['creator', 'editor'])->findOrFail($id);

        return response()->json(['status' => 'ok', 'data' => $doc]);
    }

    /** POST /api/v1/admin/api-docs */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'        => ['required', 'string', 'max:200'],
            'category'     => ['required', 'string', 'in:' . implode(',', ApiDoc::CATEGORIES)],
            'content_html' => ['required', 'string'],
            'sort_order'   => ['sometimes', 'integer'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $admin = JWTAuth::parseToken()->authenticate();
        $slug  = $this->generateUniqueSlug($request->input('title'));

        $doc = ApiDoc::create([
            'title'        => $request->input('title'),
            'slug'         => $slug,
            'category'     => $request->input('category'),
            'content_html' => $request->input('content_html'),
            'sort_order'   => $request->input('sort_order', 0),
            'is_published' => $request->input('is_published', true),
            'created_by'   => $admin->id,
            'updated_by'   => $admin->id,
        ]);

        return response()->json(
            ['status' => 'ok', 'data' => $doc],
            Response::HTTP_CREATED
        );
    }

    /** PUT /api/v1/admin/api-docs/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $doc = ApiDoc::findOrFail($id);

        $request->validate([
            'title'        => ['sometimes', 'string', 'max:200'],
            'category'     => ['sometimes', 'string', 'in:' . implode(',', ApiDoc::CATEGORIES)],
            'content_html' => ['sometimes', 'string'],
            'sort_order'   => ['sometimes', 'integer'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $admin = JWTAuth::parseToken()->authenticate();

        $updateData = $request->only([
            'title', 'category', 'content_html', 'sort_order', 'is_published',
        ]);
        $updateData['updated_by'] = $admin->id;

        if ($request->has('title')) {
            $updateData['slug'] = $this->generateUniqueSlug($request->input('title'), $id);
        }

        $doc->update($updateData);

        return response()->json(['status' => 'ok', 'data' => $doc->fresh()]);
    }

    /** DELETE /api/v1/admin/api-docs/{id} */
    public function destroy(int $id): JsonResponse
    {
        ApiDoc::findOrFail($id)->delete();

        return response()->json(['status' => 'ok', 'message' => 'Doc deleted.']);
    }

    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug     = Str::slug($title);
        $original = $slug;
        $counter  = 1;

        while (
            ApiDoc::where('slug', $slug)
                  ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                  ->exists()
        ) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
