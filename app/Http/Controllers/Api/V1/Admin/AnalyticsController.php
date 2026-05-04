<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsRequest;
use App\Services\AdEventService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/admin/analytics
 * GET /api/v1/admin/analytics/daily
 *
 * Aggregated ad performance metrics.
 * All queries hit pre-built compound indexes — no full-table scans.
 */
class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AdEventService $eventService
    ) {}

    /**
     * Top-level summary: totals + CTR + fill rate + match rate,
     * broken down by network and by placement.
     */
    public function summary(AnalyticsRequest $request): JsonResponse
    {
        $data = $this->eventService->getAnalytics(
            (int) $request->input('app_id'),
            $request->input('from') . ' 00:00:00',
            $request->input('to')   . ' 23:59:59'
        );

        return response()->json([
            'status' => 'ok',
            'data'   => $data,
        ]);
    }

    /**
     * Day-by-day event counts for charting in the dashboard.
     */
    public function daily(AnalyticsRequest $request): JsonResponse
    {
        $breakdown = $this->eventService->getDailyBreakdown(
            (int) $request->input('app_id'),
            $request->input('from') . ' 00:00:00',
            $request->input('to')   . ' 23:59:59'
        );

        return response()->json([
            'status' => 'ok',
            'data'   => $breakdown,
        ]);
    }
}
