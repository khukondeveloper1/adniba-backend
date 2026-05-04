<?php

namespace App\Services;

use App\Models\App;
use App\Models\AppEvent;
use Illuminate\Support\Collection;

class AppEventService
{
    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_DEVELOPER = 'developer';
    public const ACTOR_SYSTEM = 'system';

    public function log(
        App $app,
        string $eventType,
        string $actorType,
        ?int $actorId = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $reason = null,
        ?array $metadata = null,
    ): AppEvent {
        return AppEvent::create([
            'app_id'      => $app->id,
            'event_type'  => $eventType,
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'reason'      => $reason,
            'actor_type'  => $actorType,
            'actor_id'    => $actorId,
            'metadata'    => $metadata,
        ]);
    }

    public function forApp(int $appId): Collection
    {
        return AppEvent::where('app_id', $appId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
