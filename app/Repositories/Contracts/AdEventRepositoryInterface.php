<?php

namespace App\Repositories\Contracts;

use App\Models\AdEvent;

interface AdEventRepositoryInterface
{
    /** Bulk-insert-safe single record write (called from the queue worker). */
    public function create(array $data): AdEvent;

    /**
     * Returns aggregated analytics for an app over a date range.
     * Result shape:
     * [
     *   'total_requests'  => int,
     *   'total_loads'     => int,
     *   'total_impressions' => int,
     *   'total_clicks'    => int,
     *   'total_fails'     => int,
     *   'ctr'             => float,   // clicks / impressions
     *   'fill_rate'       => float,   // impressions / requests
     *   'by_network'      => [...],
     *   'by_placement'    => [...],
     * ]
     */
    public function getAnalytics(int $appId, string $from, string $to): array;

    /**
     * Per-day breakdown for a given app + date range.
     */
    public function getDailyBreakdown(int $appId, string $from, string $to): array;
}
