<?php

namespace App\Services;

use App\Jobs\TrackAdEvent;
use App\Models\AdEvent;
use App\Repositories\Contracts\AdEventRepositoryInterface;

class AdEventService
{
    public function __construct(
        private readonly AdEventRepositoryInterface $events
    ) {}

    /**
     * Dispatch event to queue — never blocks the SDK response.
     * This is the only entry-point the SDK controller should call.
     */
    public function dispatch(
        int    $appId,
        string $network,
        string $adType,
        string $placement,
        string $eventType
    ): void {
        TrackAdEvent::dispatch([
            'app_id'     => $appId,
            'network'    => $network,
            'ad_type'    => $adType,
            'placement'  => $placement,
            'event_type' => $eventType,
        ])->onQueue('events');
    }

    /**
     * Called from within the queue worker — actually writes to DB.
     */
    public function record(array $data): AdEvent
    {
        return $this->events->create($data);
    }

    // ─── Analytics ────────────────────────────────────────────────────────────

    public function getAnalytics(int $appId, string $from, string $to): array
    {
        return $this->events->getAnalytics($appId, $from, $to);
    }

    public function getDailyBreakdown(int $appId, string $from, string $to): array
    {
        return $this->events->getDailyBreakdown($appId, $from, $to);
    }
}
