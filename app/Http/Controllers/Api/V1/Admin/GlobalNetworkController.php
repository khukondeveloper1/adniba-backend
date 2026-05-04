<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlobalAdNetwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin manages the GLOBAL list of available ad network types.
 * These appear in the Developer panel as "Available Networks".
 *
 * /api/v1/admin/global-networks
 */
class GlobalNetworkController extends Controller
{
    /** GET /api/v1/admin/global-networks */
    public function index(): JsonResponse
    {
        $networks = GlobalAdNetwork::orderBy('sort_order')->get();

        return response()->json(['status' => 'ok', 'data' => $networks]);
    }

    /** POST /api/v1/admin/global-networks */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'         => ['required', 'string', 'max:50', 'unique:global_ad_networks,name', 'regex:/^[a-z0-9_]+$/'],
            'display_name' => ['required', 'string', 'max:100'],
            'logo_url'     => ['nullable', 'url'],
            'description'  => ['nullable', 'string', 'max:500'],
            'website_url'  => ['nullable', 'url'],
            'is_active'    => ['sometimes', 'boolean'],
            'sort_order'   => ['sometimes', 'integer'],
        ]);

        $network = GlobalAdNetwork::create($request->only([
            'name', 'display_name', 'logo_url',
            'description', 'website_url', 'is_active', 'sort_order',
        ]));

        return response()->json(
            ['status' => 'ok', 'data' => $network],
            Response::HTTP_CREATED
        );
    }

    /** PUT /api/v1/admin/global-networks/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $network = GlobalAdNetwork::findOrFail($id);

        $request->validate([
            'display_name' => ['sometimes', 'string', 'max:100'],
            'logo_url'     => ['nullable', 'url'],
            'description'  => ['nullable', 'string', 'max:500'],
            'website_url'  => ['nullable', 'url'],
            'is_active'    => ['sometimes', 'boolean'],
            'sort_order'   => ['sometimes', 'integer'],
        ]);

        $network->update($request->only([
            'display_name', 'logo_url',
            'description', 'website_url', 'is_active', 'sort_order',
        ]));

        return response()->json(['status' => 'ok', 'data' => $network->fresh()]);
    }

    /** PATCH /api/v1/admin/global-networks/{id}/toggle */
    public function toggle(int $id): JsonResponse
    {
        $network = GlobalAdNetwork::findOrFail($id);
        $network->update(['is_active' => !$network->is_active]);

        return response()->json(['status' => 'ok', 'data' => $network->fresh()]);
    }

    /** DELETE /api/v1/admin/global-networks/{id} */
    public function destroy(int $id): JsonResponse
    {
        $network = GlobalAdNetwork::findOrFail($id);
        $network->delete();

        return response()->json(['status' => 'ok', 'message' => 'Network type removed.']);
    }
}
