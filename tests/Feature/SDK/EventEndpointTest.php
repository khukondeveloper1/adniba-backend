<?php

namespace Tests\Feature\SDK;

use App\Jobs\TrackAdEvent;
use App\Models\App;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventEndpointTest extends TestCase
{
    use RefreshDatabase;

    private App    $sdkApp;
    private string $apiKey = 'adniba_test_event_key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->sdkApp = App::create([
            'name'              => 'Event Test App',
            'package_name'      => 'com.test.events',
            'api_key'           => $this->apiKey,
            'app_status'        => 1,
            'global_ad_enabled' => 1,
        ]);
    }

    /** @test */
    public function it_accepts_a_valid_event_and_returns_202(): void
    {
        Queue::fake();

        $this->sdkPost([
            'network'    => 'admob',
            'ad_type'    => 'banner',
            'placement'  => 'home',
            'event_type' => 'impression',
        ])
        ->assertStatus(202)
        ->assertJson(['status' => 'queued']);
    }

    /** @test */
    public function it_dispatches_track_ad_event_job_to_events_queue(): void
    {
        Queue::fake();

        $this->sdkPost([
            'network'    => 'meta',
            'ad_type'    => 'interstitial',
            'placement'  => 'result',
            'event_type' => 'click',
        ])->assertStatus(202);

        Queue::assertPushedOn('events', TrackAdEvent::class);
    }

    /** @test */
    public function it_does_not_write_to_database_synchronously(): void
    {
        Queue::fake();

        $this->sdkPost([
            'network'    => 'admob',
            'ad_type'    => 'rewarded',
            'placement'  => 'splash',
            'event_type' => 'request',
        ])->assertStatus(202);

        // No DB row written — job is in the queue, not flushed
        $this->assertDatabaseCount('ad_events', 0);
    }

    /** @test */
    public function it_rejects_invalid_event_type(): void
    {
        $this->sdkPost([
            'network'    => 'admob',
            'ad_type'    => 'banner',
            'placement'  => 'home',
            'event_type' => 'invalid_type',
        ])->assertStatus(422);
    }

    /** @test */
    public function it_rejects_unknown_network(): void
    {
        $this->sdkPost([
            'network'    => 'tiktok',
            'ad_type'    => 'banner',
            'placement'  => 'home',
            'event_type' => 'impression',
        ])->assertStatus(422);
    }

    /** @test */
    public function it_rejects_missing_fields(): void
    {
        $this->sdkPost(['network' => 'admob'])
             ->assertStatus(422)
             ->assertJsonPath('status', 'error');
    }

    /** @test */
    public function it_returns_401_without_api_key(): void
    {
        $this->postJson('/api/v1/ads/event', [
            'network'    => 'admob',
            'ad_type'    => 'banner',
            'placement'  => 'home',
            'event_type' => 'impression',
        ])->assertStatus(401);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function sdkPost(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['x-api-key' => $this->apiKey])
                    ->postJson('/api/v1/ads/event', $payload);
    }
}
