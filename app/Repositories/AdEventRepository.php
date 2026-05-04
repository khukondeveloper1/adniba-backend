<?php

namespace App\Repositories;

use App\Models\AdEvent;
use App\Repositories\Contracts\AdEventRepositoryInterface;
use Illuminate\Support\Facades\DB;

class AdEventRepository implements AdEventRepositoryInterface
{
    public function create(array $data): AdEvent
    {
        return AdEvent::create($data);
    }

    /**
     * Single-pass aggregate using portable CASE/SUM to avoid multiple queries.
     * All sub-queries use the idx_analytics / idx_network / idx_placement indexes.
     */
    public function getAnalytics(int $appId, string $from, string $to): array
    {
        // ── Top-level aggregate ──────────────────────────────────────────────
        $totals = DB::table('ad_events')
            ->where('app_id', $appId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("
                SUM(CASE WHEN event_type = 'request' THEN 1 ELSE 0 END) AS total_requests,
                SUM(CASE WHEN event_type = 'load' THEN 1 ELSE 0 END) AS total_loads,
                SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) AS total_impressions,
                SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) AS total_clicks,
                SUM(CASE WHEN event_type = 'fail' THEN 1 ELSE 0 END) AS total_fails
            ")
            ->first();

        $requests    = (int) ($totals->total_requests    ?? 0);
        $loads       = (int) ($totals->total_loads       ?? 0);
        $impressions = (int) ($totals->total_impressions ?? 0);
        $clicks      = (int) ($totals->total_clicks      ?? 0);
        $fails       = (int) ($totals->total_fails       ?? 0);

        $ctr       = $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0;
        $fillRate  = $requests    > 0 ? round($impressions / $requests * 100, 2) : 0;
        // match_rate = loads that resulted in an impression
        $matchRate = $loads       > 0 ? round($impressions / $loads * 100, 2) : 0;

        // ── Per-network breakdown ────────────────────────────────────────────
        $byNetwork = DB::table('ad_events')
            ->where('app_id', $appId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("
                network,
                SUM(CASE WHEN event_type = 'request' THEN 1 ELSE 0 END) AS requests,
                SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) AS impressions,
                SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) AS clicks,
                SUM(CASE WHEN event_type = 'fail' THEN 1 ELSE 0 END) AS fails,
                ROUND(
                    CASE
                        WHEN SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) > 0
                        THEN SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) * 100.0 /
                             SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END)
                        ELSE 0
                    END, 2
                ) AS ctr
            ")
            ->groupBy('network')
            ->orderByDesc('impressions')
            ->get()
            ->toArray();

        // ── Per-placement breakdown ──────────────────────────────────────────
        $byPlacement = DB::table('ad_events')
            ->where('app_id', $appId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("
                ad_type,
                placement,
                SUM(CASE WHEN event_type = 'request' THEN 1 ELSE 0 END) AS requests,
                SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) AS impressions,
                SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) AS clicks,
                ROUND(
                    CASE
                        WHEN SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) > 0
                        THEN SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) * 100.0 /
                             SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END)
                        ELSE 0
                    END, 2
                ) AS ctr
            ")
            ->groupBy('ad_type', 'placement')
            ->orderByDesc('impressions')
            ->get()
            ->toArray();

        return [
            'total_requests'   => $requests,
            'total_loads'      => $loads,
            'total_impressions' => $impressions,
            'total_clicks'     => $clicks,
            'total_fails'      => $fails,
            'ctr'              => $ctr,
            'fill_rate'        => $fillRate,
            'match_rate'       => $matchRate,
            'by_network'       => $byNetwork,
            'by_placement'     => $byPlacement,
        ];
    }

    /**
     * Day-by-day event counts — hits idx_daily index.
     */
    public function getDailyBreakdown(int $appId, string $from, string $to): array
    {
        return DB::table('ad_events')
            ->where('app_id', $appId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("
                DATE(created_at)               AS date,
                SUM(CASE WHEN event_type = 'request' THEN 1 ELSE 0 END) AS requests,
                SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) AS impressions,
                SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) AS clicks,
                SUM(CASE WHEN event_type = 'fail' THEN 1 ELSE 0 END) AS fails
            ")
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->toArray();
    }
}
