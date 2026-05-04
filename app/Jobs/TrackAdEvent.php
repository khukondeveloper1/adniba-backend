<?php

namespace App\Jobs;

use App\Services\AdEventService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TrackAdEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Max attempts before the job is moved to the failed_jobs table.
     * 3 attempts with exponential back-off is sufficient for a DB write.
     */
    public int $tries = 3;

    /**
     * Back-off strategy in seconds (attempt 1 → 5s, attempt 2 → 30s, attempt 3 → 60s).
     */
    public array $backoff = [5, 30, 60];

    /**
     * Prevent the queue from holding stale events for too long.
     * If not processed within 10 minutes, discard silently.
     */
    public int $timeout = 30;

    public function __construct(
        private readonly array $payload
    ) {}

    public function handle(AdEventService $service): void
    {
        $service->record($this->payload);
    }

    /**
     * Log failed events so they can be replayed / audited.
     */
    public function failed(\Throwable $e): void
    {
        Log::channel('events')->error('TrackAdEvent failed', [
            'payload'   => $this->payload,
            'exception' => $e->getMessage(),
        ]);
    }
}
