<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Services\AdEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/external/stats
 * GET /api/v1/external/stats/daily
 *
 * Developer এর নিজস্ব panel থেকে তার app এর analytics দেখার জন্য।
 * Auth: x-api-key header (App API Key)
 */
class StatsController extends Controller
{
    public function __construct(
        private readonly AdEventService $eventService
    ) {}

    /**
     * Summary analytics — CTR, fill rate, by network, by placement
     *
     * GET /api/v1/external/stats?from=2024-01-01&to=2024-12-31
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to'   => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $app  = $request->attributes->get('external_app');
        $from = $request->query('from', now()->subDays(30)->format('Y-m-d'));
        $to   = $request->query('to', now()->format('Y-m-d'));

        $data = $this->eventService->getAnalytics(
            $app->id,
            $from . ' 00:00:00',
            $to   . ' 23:59:59'
        );

        return response()->json([
            'status' => 'ok',
            'app'    => [
                'id'   => $app->id,
                'name' => $app->name,
            ],
            'period' => ['from' => $from, 'to' => $to],
            'data'   => $data,
        ]);
    }

    /**
     * Day-by-day breakdown for charts
     *
     * GET /api/v1/external/stats/daily?from=2024-01-01&to=2024-01-31
     */
    public function daily(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to'   => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $app  = $request->attributes->get('external_app');
        $from = $request->query('from', now()->subDays(30)->format('Y-m-d'));
        $to   = $request->query('to', now()->format('Y-m-d'));

        $data = $this->eventService->getDailyBreakdown(
            $app->id,
            $from . ' 00:00:00',
            $to   . ' 23:59:59'
        );

        return response()->json([
            'status' => 'ok',
            'app'    => [
                'id'   => $app->id,
                'name' => $app->name,
            ],
            'period' => ['from' => $from, 'to' => $to],
            'data'   => $data,
        ]);
    }
}
