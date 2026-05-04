<?php

namespace Tests\Feature\SDK;

use App\Models\AdNetwork;
use App\Models\AdUnit;
use App\Models\App;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworksEndpointTest extends TestCase
{
    use RefreshDatabase;

    private App $sdkApp;
    private string $apiKey = 'adniba_test_key_networks';

    protected function setUp(): void
    {
        parent::setUp();

        $this->sdkApp = App::create([
            'name'              => 'Test App',
            'package_name'      => 'com.test.networks',
            'api_key'           => $this->apiKey,
            'app_status'        => 1,
            'global_ad_enabled' => 1,
        ]);
    }

    /** @test */
    public function it_returns_401_when_api_key_is_missing(): void
    {
        $this->getJson('/api/v1/ads/networks')
             ->assertStatus(401)
             ->assertJsonPath('status', 'error');
    }

    /** @test */
    public function it_returns_401_for_invalid_api_key(): void
    {
        $this->withHeaders(['x-api-key' => 'invalid_key'])
             ->getJson('/api/v1/ads/networks')
             ->assertStatus(401);
    }

    /** @test */
    public function it_returns_empty_networks_when_ads_globally_disabled(): void
    {
        $this->sdkApp->update(['global_ad_enabled' => 0]);

        $this->withHeaders(['x-api-key' => $this->apiKey])
             ->getJson('/api/v1/ads/networks')
             ->assertOk()
             ->assertJson(['status' => 'ok', 'networks' => []]);
    }

    /** @test */
    public function it_returns_active_networks_ordered_by_priority(): void
    {
        // Create two networks with ad units at different priorities
        $admob = AdNetwork::create(['app_id' => $this->sdkApp->id, 'name' => 'admob', 'enabled' => 1]);
        $meta  = AdNetwork::create(['app_id' => $this->sdkApp->id, 'name' => 'meta',  'enabled' => 1]);

        AdUnit::create([
            'app_id' => $this->sdkApp->id, 'network_id' => $admob->id,
            'ad_type' => 'banner', 'placement' => 'home',
            'unit_id' => 'ca-admob-xxx', 'priority' => 1, 'enabled' => 1,
        ]);
        AdUnit::create([
            'app_id' => $this->sdkApp->id, 'network_id' => $meta->id,
            'ad_type' => 'banner', 'placement' => 'home',
            'unit_id' => 'meta-xxx', 'priority' => 2, 'enabled' => 1,
        ]);

        $response = $this->withHeaders(['x-api-key' => $this->apiKey])
                         ->getJson('/api/v1/ads/networks')
                         ->assertOk()
                         ->assertJsonPath('status', 'ok');

        $networks = $response->json('networks');

        $this->assertCount(2, $networks);
        $this->assertEquals('admob', $networks[0]['name']);
        $this->assertEquals(1,       $networks[0]['priority']);
        $this->assertEquals('meta',  $networks[1]['name']);
        $this->assertEquals(2,       $networks[1]['priority']);
    }

    /** @test */
    public function it_excludes_disabled_networks(): void
    {
        $admob    = AdNetwork::create(['app_id' => $this->sdkApp->id, 'name' => 'admob', 'enabled' => 1]);
        $disabled = AdNetwork::create(['app_id' => $this->sdkApp->id, 'name' => 'unity', 'enabled' => 0]);

        AdUnit::create([
            'app_id' => $this->sdkApp->id, 'network_id' => $admob->id,
            'ad_type' => 'banner', 'placement' => 'home',
            'unit_id' => 'ca-admob-xxx', 'priority' => 1, 'enabled' => 1,
        ]);
        AdUnit::create([
            'app_id' => $this->sdkApp->id, 'network_id' => $disabled->id,
            'ad_type' => 'banner', 'placement' => 'home',
            'unit_id' => 'unity-xxx', 'priority' => 2, 'enabled' => 1,
        ]);

        $networks = $this->withHeaders(['x-api-key' => $this->apiKey])
                         ->getJson('/api/v1/ads/networks')
                         ->assertOk()
                         ->json('networks');

        $this->assertCount(1, $networks);
        $this->assertEquals('admob', $networks[0]['name']);
    }

    /** @test */
    public function it_returns_403_when_app_is_inactive(): void
    {
        $this->sdkApp->update(['app_status' => 0]);

        $this->withHeaders(['x-api-key' => $this->apiKey])
             ->getJson('/api/v1/ads/networks')
             ->assertStatus(403);
    }
}
