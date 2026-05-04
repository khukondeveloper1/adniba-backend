<?php

namespace Tests\Feature\Admin;

use App\Models\AdEvent;
use App\Models\AdminUser;
use App\Models\App;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private string    $token;
    private App       $trackedApp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::create([
            'username'      => 'analytics_admin',
            'password_hash' => Hash::make('password'),
        ]);
        $this->token = JWTAuth::fromUser($this->admin);

        $this->trackedApp = App::create([
            'name'              => 'Analytics App',
            'package_name'      => 'com.analytics.test',
            'api_key'           => 'adniba_analytics_key',
            'app_status'        => 1,
            'global_ad_enabled' => 1,
        ]);

        // Seed 10 requests, 8 impressions, 2 clicks, 1 fail
        $events = [
            ['event_type' => 'request',    'count' => 10],
            ['event_type' => 'impression', 'count' => 8],
            ['event_type' => 'click',      'count' => 2],
            ['event_type' => 'fail',       'count' => 1],
        ];

        foreach ($events as ['event_type' => $type, 'count' => $count]) {
            for ($i = 0; $i < $count; $i++) {
                AdEvent::create([
                    'app_id'     => $this->trackedApp->id,
                    'network'    => 'admob',
                    'ad_type'    => 'banner',
                    'placement'  => 'home',
                    'event_type' => $type,
                    'created_at' => now()->subDays(rand(0, 6)),
                ]);
            }
        }
    }

    /** @test */
    public function it_returns_correct_aggregate_metrics(): void
    {
        $response = $this->withToken($this->token)
                         ->getJson("/api/v1/admin/analytics?app_id={$this->trackedApp->id}&from=" .
                                   now()->subDays(7)->format('Y-m-d') .
                                   '&to=' . now()->format('Y-m-d'))
                         ->assertOk()
                         ->assertJsonPath('status', 'ok');

        $data = $response->json('data');

        $this->assertEquals(10, $data['total_requests']);
        $this->assertEquals(8,  $data['total_impressions']);
        $this->assertEquals(2,  $data['total_clicks']);
        $this->assertEquals(1,  $data['total_fails']);

        // CTR = 2/8 * 100 = 25.0
        $this->assertEquals(25.0, $data['ctr']);

        // Fill rate = 8/10 * 100 = 80.0
        $this->assertEquals(80.0, $data['fill_rate']);
    }

    /** @test */
    public function it_returns_by_network_breakdown(): void
    {
        $data = $this->withToken($this->token)
                     ->getJson("/api/v1/admin/analytics?app_id={$this->trackedApp->id}&from=" .
                               now()->subDays(7)->format('Y-m-d') .
                               '&to=' . now()->format('Y-m-d'))
                     ->assertOk()
                     ->json('data');

        $this->assertNotEmpty($data['by_network']);
        $this->assertEquals('admob', $data['by_network'][0]->network ?? $data['by_network'][0]['network']);
    }

    /** @test */
    public function it_returns_daily_breakdown(): void
    {
        $response = $this->withToken($this->token)
                         ->getJson("/api/v1/admin/analytics/daily?app_id={$this->trackedApp->id}&from=" .
                                   now()->subDays(6)->format('Y-m-d') .
                                   '&to=' . now()->format('Y-m-d'))
                         ->assertOk();

        $days = $response->json('data');
        $this->assertNotEmpty($days);
    }

    /** @test */
    public function it_validates_date_range_is_required(): void
    {
        $this->withToken($this->token)
             ->getJson("/api/v1/admin/analytics?app_id={$this->trackedApp->id}")
             ->assertStatus(422);
    }

    /** @test */
    public function it_rejects_invalid_app_id(): void
    {
        $this->withToken($this->token)
             ->getJson('/api/v1/admin/analytics?app_id=99999&from=2024-01-01&to=2024-01-31')
             ->assertStatus(422);
    }
}
