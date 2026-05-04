<?php

namespace App\Http\Controllers\Api\V1\SDK;

use App\Http\Controllers\Controller;
use App\Http\Requests\SDK\TrackEventRequest;
use App\Services\AdEventService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/ads/event
 *
 * Receives SDK telemetry (request / load / impression / click / fail).
 * Validation is synchronous (fast), but the actual DB write is dispatched
 * to the Redis-backed "events" queue so this endpoint never blocks.
 *
 * Target: < 20 ms response time regardless of event volume.
 */
class EventController extends Controller
{
    public function __construct(
        private readonly AdEventService $eventService
    ) {}

    public function store(TrackEventRequest $request): JsonResponse
    {
        /** @var \App\Models\App $app */
        $app = $request->attributes->get('sdk_app');

        $this->eventService->dispatch(
            appId:     $app->id,
            network:   $request->input('network'),
            adType:    $request->input('ad_type'),
            placement: $request->input('placement'),
            eventType: $request->input('event_type')
        );

        // 202 Accepted — event has been queued, not yet persisted
        return response()->json(
            ['status' => 'queued'],
            Response::HTTP_ACCEPTED
        );
    }
}
