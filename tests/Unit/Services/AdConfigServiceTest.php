<?php

namespace Tests\Unit\Services;

use App\Models\AdSetting;
use App\Models\App;
use App\Repositories\Contracts\AdSettingRepositoryInterface;
use App\Repositories\Contracts\AdUnitRepositoryInterface;
use App\Services\AdConfigService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AdConfigServiceTest extends TestCase
{
    private AdConfigService                $service;
    private AdSettingRepositoryInterface   $settingsRepo;
    private AdUnitRepositoryInterface      $unitsRepo;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->settingsRepo = Mockery::mock(AdSettingRepositoryInterface::class);
        $this->unitsRepo    = Mockery::mock(AdUnitRepositoryInterface::class);

        $this->service = new AdConfigService(
            $this->settingsRepo,
            $this->unitsRepo
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_fallback_mode_when_no_setting_exists(): void
    {
        $app = $this->fakeApp();

        $this->settingsRepo
             ->shouldReceive('getForPlacement')
             ->once()
             ->with($app->id, 'banner', 'home')
             ->andReturn(null);   // No setting row → default to fallback

        $units = collect([
            (object)['network' => 'admob', 'unit_id' => 'ca-admob', 'priority' => 1],
            (object)['network' => 'meta',  'unit_id' => 'meta-id',  'priority' => 2],
        ]);

        $this->unitsRepo
             ->shouldReceive('getConfigUnits')
             ->once()
             ->with($app->id, 'banner', 'home')
             ->andReturn($units);

        $config = $this->service->getConfig($app, 'banner', 'home');

        $this->assertTrue($config['fallback']);
        $this->assertCount(2, $config['ads']);
        $this->assertEquals('admob', $config['ads'][0]['network']);
    }

    /** @test */
    public function it_returns_force_mode_when_fallback_disabled(): void
    {
        $app = $this->fakeApp();

        $setting = new AdSetting([
            'fallback_enabled' => false,
            'network_id'       => 99,
        ]);

        $this->settingsRepo
             ->shouldReceive('getForPlacement')
             ->once()
             ->andReturn($setting);

        // getForcedUnit is NOT on the interface directly — service calls via repo
        // In force mode the service uses getForcedUnit from the AdUnitRepository
        $forcedUnit = new \App\Models\AdUnit([
            'unit_id'  => 'ca-force-admob',
            'priority' => 1,
        ]);
        $forcedUnit->setRelation('network', (object)['name' => 'admob']);

        $this->unitsRepo
             ->shouldReceive('getForcedUnit')
             ->once()
             ->with($app->id, 99, 'banner', 'home')
             ->andReturn($forcedUnit);

        $config = $this->service->getConfig($app, 'banner', 'home');

        $this->assertFalse($config['fallback']);
        $this->assertCount(1, $config['ads']);
        $this->assertEquals('admob', $config['ads'][0]['network']);
        $this->assertEquals('ca-force-admob', $config['ads'][0]['unit_id']);
    }

    /** @test */
    public function it_returns_empty_ads_when_forced_unit_not_found(): void
    {
        $app     = $this->fakeApp();
        $setting = new AdSetting(['fallback_enabled' => false, 'network_id' => 99]);

        $this->settingsRepo->shouldReceive('getForPlacement')->once()->andReturn($setting);
        $this->unitsRepo->shouldReceive('getForcedUnit')->once()->andReturn(null);

        $config = $this->service->getConfig($app, 'banner', 'home');

        $this->assertEmpty($config['ads']);
    }

    /** @test */
    public function it_caches_the_config_with_a_ttl_between_60_and_300_seconds(): void
    {
        $app = $this->fakeApp();

        $this->settingsRepo->shouldReceive('getForPlacement')->once()->andReturn(null);
        $this->unitsRepo->shouldReceive('getConfigUnits')->once()->andReturn(collect([]));

        $config = $this->service->getConfig($app, 'banner', 'home');

        $this->assertArrayHasKey('refresh_interval', $config);
        $this->assertGreaterThanOrEqual(60, $config['refresh_interval']);
        $this->assertLessThanOrEqual(300, $config['refresh_interval']);

        // Second call must use cache — repo should NOT be called again
        $this->settingsRepo->shouldNotReceive('getForPlacement');
        $this->service->getConfig($app, 'banner', 'home');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function fakeApp(int $id = 1): App
    {
        $app = new App([
            'name'              => 'Test App',
            'package_name'      => 'com.test',
            'api_key'           => 'key',
            'app_status'        => 1,
            'global_ad_enabled' => 1,
        ]);
        $app->id = $id;

        return $app;
    }
}
