<?php

namespace Tests\Feature\SDK;

use App\Models\AdNetwork;
use App\Models\AdSetting;
use App\Models\AdUnit;
use App\Models\App;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdConfigEndpointTest extends TestCase
{
    use RefreshDatabase;

    private App       $sdkApp;
    private AdNetwork $admob;
    private AdNetwork $meta;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->sdkApp = App::create([
            'name'              => 'Config Test App',
            'package_name'      => 'com.test.config',
            'api_key'           => 'adniba_test_config_key',
            'app_status'        => 1,
            'global_ad_enabled' => 1,
        ]);

        $this->admob = AdNetwork::create(['app_id' => $this->sdkApp->id, 'name' => 'admob', 'enabled' => 1]);
        $this->meta  = AdNetwork::create(['app_id' => $this->sdkApp->id, 'name' => 'meta',  'enabled' => 1]);

        AdUnit::create([
            'app_id' => $this->sdkApp->id, 'network_id' => $this->admob->id,
            'ad_type' => 'banner', 'placement' => 'home',
            'unit_id' => 'ca-app-pub-admob', 'priority' => 1, 'enabled' => 1,
        ]);
        AdUnit::create([
            'app_id' => $this->sdkApp->id, 'network_id' => $this->meta->id,
            'ad_type' => 'banner', 'placement' => 'home',
            'unit_id' => 'meta-banner-home', 'priority' => 2, 'enabled' => 1,
        ]);
    }

    /** @test */
    public function it_requires_ad_type_and_placement_query_params(): void
    {
        $this->sdkGet('/api/v1/ads/config')
             ->assertStatus(422)
             ->assertJsonPath('status', 'error');
    }

    /** @test */
    public function it_returns_fallback_mode_config_with_all_enabled_units(): void
    {
        // No AdSetting row → defaults to fallback=true
        $response = $this->sdkGet('/api/v1/ads/config?ad_type=banner&placement=home')
                         ->assertOk()
                         ->assertJsonPath('status', 'ok')
                         ->assertJsonPath('fallback', true)
                         ->assertJsonPath('ad_type', 'banner')
                         ->assertJsonPath('placement', 'home');

        $ads = $response->json('ads');
        $this->assertCount(2, $ads);
        $this->assertEquals('admob', $ads[0]['network']);
        $this->assertEquals(1,       $ads[0]['priority']);
        $this->assertEquals('meta',  $ads[1]['network']);
        $this->assertEquals(2,       $ads[1]['priority']);
    }

    /** @test */
    public function it_returns_force_mode_config_with_single_network(): void
    {
        AdSetting::create([
            'app_id'           => $this->sdkApp->id,
            'ad_type'          => 'banner',
            'placement'        => 'home',
            'fallback_enabled' => 0,
            'network_id'       => $this->admob->id,
        ]);

        $response = $this->sdkGet('/api/v1/ads/config?ad_type=banner&placement=home')
                         ->assertOk()
                         ->assertJsonPath('fallback', false);

        $ads = $response->json('ads');
        $this->assertCount(1, $ads);
        $this->assertEquals('admob', $ads[0]['network']);
    }

    /** @test */
    public function it_returns_empty_ads_when_global_ads_disabled(): void
    {
        $this->sdkApp->update(['global_ad_enabled' => 0]);
        Cache::flush();

        $ads = $this->sdkGet('/api/v1/ads/config?ad_type=banner&placement=home')
                    ->assertOk()
                    ->json('ads');

        $this->assertEmpty($ads);
    }

    /** @test */
    public function it_excludes_disabled_ad_units(): void
    {
        // Disable the meta unit
        AdUnit::where('network_id', $this->meta->id)->update(['enabled' => 0]);
        Cache::flush();

        $ads = $this->sdkGet('/api/v1/ads/config?ad_type=banner&placement=home')
                    ->assertOk()
                    ->json('ads');

        $this->assertCount(1, $ads);
        $this->assertEquals('admob', $ads[0]['network']);
    }

    /** @test */
    public function it_caches_the_config_response(): void
    {
        // First request — cache miss
        $this->sdkGet('/api/v1/ads/config?ad_type=banner&placement=home')->assertOk();

        $cacheKey = "adcfg:{$this->sdkApp->id}:banner:home";

        $this->assertTrue(Cache::has($cacheKey));
    }

    /** @test */
    public function it_includes_refresh_interval_in_response(): void
    {
        $response = $this->sdkGet('/api/v1/ads/config?ad_type=banner&placement=home')
                         ->assertOk();

        $interval = $response->json('refresh_interval');

        $this->assertIsInt($interval);
        $this->assertGreaterThanOrEqual(60, $interval);
        $this->assertLessThanOrEqual(300, $interval);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function sdkGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['x-api-key' => 'adniba_test_config_key'])
                    ->getJson($uri);
    }
}
