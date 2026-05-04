<?php

namespace App\Http\Controllers\Api\V1\Developer;

use App\Http\Controllers\Controller;
use App\Services\AdEventService;
use App\Services\DeveloperAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AppController extends Controller
{
    public function __construct(
        private readonly DeveloperAppService $appService,
        private readonly AdEventService      $eventService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('developer');
        $apps = $this->appService->listMyApps($user);

        return response()->json([
            'status' => 'ok',
            'data'   => $apps,
            'meta'   => [
                'total'           => $apps->count(),
                'app_limit'       => $user->app_limit,
                'remaining_slots' => $user->remainingAppSlots(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('developer');

        try {
            $app = $this->appService->getMyApp($user, $id);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        return response()->json(['status' => 'ok', 'data' => $app]);
    }

    /**
     * POST /api/v1/developer/apps
     *
     * Supports two input modes:
     *   1. Manual: { name, package_name, app_logo? }
     *   2. With logo file: multipart/form-data with logo_file field
     *   3. With play_store_url: attach URL, name/package auto-filled
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'           => ['required_without:play_store_url', 'string', 'max:100'],
            'package_name'   => ['required_without:play_store_url', 'string', 'max:100', 'unique:apps,package_name'],
            'app_logo'       => ['nullable', 'string'],
            'logo_file'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'play_store_url' => ['nullable', 'url'],
            'app_store_url'  => ['nullable', 'url'],
        ]);

        $user = $request->attributes->get('developer');

        // Handle logo file upload
        $appLogo = $request->input('app_logo');
        if ($request->hasFile('logo_file')) {
            $path    = $request->file('logo_file')->store('app_logos', 'public');
            $appLogo = asset('storage/' . $path);
        }

        $data = [
            'name'           => $request->input('name'),
            'package_name'   => $request->input('package_name'),
            'app_logo'       => $appLogo,
            'play_store_url' => $request->input('play_store_url'),
            'app_store_url'  => $request->input('app_store_url'),
        ];

        try {
            $app = $this->appService->createApp($user, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(
            ['status' => 'ok', 'data' => $app],
            Response::HTTP_CREATED
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name'          => ['sometimes', 'string', 'max:100'],
            'app_logo'      => ['nullable', 'string'],
            'logo_file'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'play_store_url'=> ['nullable', 'url'],
            'app_store_url' => ['nullable', 'url'],
        ]);

        $user = $request->attributes->get('developer');

        $data = $request->only(['name', 'app_logo', 'play_store_url', 'app_store_url']);

        if ($request->hasFile('logo_file')) {
            $path         = $request->file('logo_file')->store('app_logos', 'public');
            $data['app_logo'] = asset('storage/' . $path);
        }

        try {
            $app = $this->appService->updateApp($user, $id, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        return response()->json(['status' => 'ok', 'data' => $app]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('developer');

        try {
            $this->appService->deleteApp($user, $id);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        return response()->json(['status' => 'ok', 'message' => 'App deleted.']);
    }

    public function rotateKey(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('developer');

        try {
            $app = $this->appService->rotateApiKey($user, $id);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        return response()->json(['status' => 'ok', 'api_key' => $app->api_key]);
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);

        $user = $request->attributes->get('developer');

        try {
            $app = $this->appService->setAppStatus($user, $id, (bool) $request->input('active'));
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['status' => 'ok', 'data' => $app]);
    }

    public function toggleAds(Request $request, int $id): JsonResponse
    {
        $request->validate(['enabled' => ['required', 'boolean']]);

        $user = $request->attributes->get('developer');

        try {
            $app = $this->appService->setAdEnabled($user, $id, (bool) $request->input('enabled'));
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        return response()->json(['status' => 'ok', 'data' => $app]);
    }

    public function stats(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('developer');

        try {
            $this->appService->getMyApp($user, $id);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }

        $from = $request->query('from', now()->subDays(30)->format('Y-m-d'));
        $to   = $request->query('to', now()->format('Y-m-d'));

        $data = $this->eventService->getAnalytics(
            $id,
            $from . ' 00:00:00',
            $to   . ' 23:59:59'
        );

        return response()->json(['status' => 'ok', 'data' => $data]);
    }
}
