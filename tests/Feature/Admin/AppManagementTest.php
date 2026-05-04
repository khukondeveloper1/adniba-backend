<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\App;
use App\Models\AppEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AppManagementTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private string    $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::create([
            'username'      => 'app_admin',
            'password_hash' => Hash::make('password'),
        ]);

        $this->token = JWTAuth::fromUser($this->admin);
    }

    /** @test */
    public function it_can_list_all_apps(): void
    {
        App::create([
            'name' => 'App One', 'package_name' => 'com.one',
            'api_key' => 'key_one', 'app_status' => 1, 'global_ad_enabled' => 1,
        ]);

        $this->adminGet('/api/v1/admin/apps')
             ->assertOk()
             ->assertJsonPath('status', 'ok')
             ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function it_can_create_an_app_with_auto_generated_api_key(): void
    {
        $response = $this->adminPost('/api/v1/admin/apps', [
            'name'         => 'My New App',
            'package_name' => 'com.example.newapp',
        ])->assertStatus(201)
          ->assertJsonPath('status', 'ok');

        $data = $response->json('data');

        $this->assertEquals('My New App', $data['name']);
        $this->assertNotEmpty($data['api_key']);
        $this->assertStringStartsWith('adniba_', $data['api_key']);
        $this->assertDatabaseHas('apps', ['package_name' => 'com.example.newapp']);
    }

    /** @test */
    public function it_prevents_duplicate_package_names(): void
    {
        App::create([
            'name' => 'Existing', 'package_name' => 'com.duplicate',
            'api_key' => 'key_dup', 'app_status' => 1, 'global_ad_enabled' => 1,
        ]);

        $this->adminPost('/api/v1/admin/apps', [
            'name'         => 'Another',
            'package_name' => 'com.duplicate',
        ])->assertStatus(422);
    }

    /** @test */
    public function it_can_rotate_api_key(): void
    {
        $app = App::create([
            'name' => 'Key App', 'package_name' => 'com.keyapp',
            'api_key' => 'adniba_old_key', 'app_status' => 1, 'global_ad_enabled' => 1,
        ]);

        $response = $this->adminPost("/api/v1/admin/apps/{$app->id}/rotate-key")
                         ->assertOk();

        $newKey = $response->json('api_key');
        $this->assertNotEquals('adniba_old_key', $newKey);
        $this->assertStringStartsWith('adniba_', $newKey);
    }

    /** @test */
    public function it_can_toggle_global_ad_enabled(): void
    {
        $app = App::create([
            'name' => 'Toggle App', 'package_name' => 'com.toggle',
            'api_key' => 'adniba_toggle_key', 'app_status' => 1, 'global_ad_enabled' => 1,
        ]);

        $this->adminPatch("/api/v1/admin/apps/{$app->id}/ads-enabled", ['enabled' => false])
             ->assertOk()
             ->assertJsonPath('data.global_ad_enabled', false);

        $this->assertDatabaseHas('apps', [
            'id'                => $app->id,
            'global_ad_enabled' => 0,
        ]);

        $this->assertDatabaseHas('app_events', [
            'app_id'     => $app->id,
            'event_type' => 'global_ads_disabled',
            'actor_type' => 'admin',
            'actor_id'   => $this->admin->id,
        ]);
    }

    /** @test */
    public function it_can_transition_active_inactive_and_active_with_events(): void
    {
        $app = App::create([
            'name' => 'Status App', 'package_name' => 'com.status',
            'api_key' => 'adniba_status_key', 'status' => App::STATUS_ACTIVE, 'global_ad_enabled' => 1,
        ]);

        $this->adminPatch("/api/v1/admin/apps/{$app->id}/status", ['active' => false])
             ->assertOk()
             ->assertJsonPath('data.status', App::STATUS_INACTIVE);

        $this->assertDatabaseHas('apps', ['id' => $app->id, 'status' => App::STATUS_INACTIVE]);
        $this->assertDatabaseHas('app_events', [
            'app_id'      => $app->id,
            'event_type'  => 'deactivated',
            'from_status' => App::STATUS_ACTIVE,
            'to_status'   => App::STATUS_INACTIVE,
            'actor_type'  => 'admin',
        ]);

        $this->adminPatch("/api/v1/admin/apps/{$app->id}/status", ['active' => true])
             ->assertOk()
             ->assertJsonPath('data.status', App::STATUS_ACTIVE);

        $this->assertDatabaseHas('app_events', [
            'app_id'      => $app->id,
            'event_type'  => 'activated',
            'from_status' => App::STATUS_INACTIVE,
            'to_status'   => App::STATUS_ACTIVE,
        ]);
    }

    /** @test */
    public function it_can_suspend_unsuspend_and_reject_invalid_suspended_to_inactive_transition(): void
    {
        $app = App::create([
            'name' => 'Suspend App', 'package_name' => 'com.suspend',
            'api_key' => 'adniba_suspend_key', 'status' => App::STATUS_ACTIVE, 'global_ad_enabled' => 1,
        ]);

        $this->adminPost("/api/v1/admin/apps/{$app->id}/suspend", ['reason' => 'Policy violation found.'])
             ->assertOk()
             ->assertJsonPath('data.status', App::STATUS_SUSPENDED);

        $this->adminPatch("/api/v1/admin/apps/{$app->id}/status", ['active' => false])
             ->assertStatus(422)
             ->assertJsonPath('status', 'error');

        $this->adminPost("/api/v1/admin/apps/{$app->id}/unsuspend")
             ->assertOk()
             ->assertJsonPath('data.status', App::STATUS_ACTIVE);

        $this->assertDatabaseHas('app_events', [
            'app_id'      => $app->id,
            'event_type'  => 'suspended',
            'from_status' => App::STATUS_ACTIVE,
            'to_status'   => App::STATUS_SUSPENDED,
        ]);

        $this->assertDatabaseHas('app_events', [
            'app_id'      => $app->id,
            'event_type'  => 'unsuspended',
            'from_status' => App::STATUS_SUSPENDED,
            'to_status'   => App::STATUS_ACTIVE,
        ]);
    }

    /** @test */
    public function it_can_return_app_event_timeline(): void
    {
        $app = App::create([
            'name' => 'Timeline App', 'package_name' => 'com.timeline',
            'api_key' => 'adniba_timeline_key', 'status' => App::STATUS_ACTIVE, 'global_ad_enabled' => 1,
        ]);

        AppEvent::create([
            'app_id' => $app->id,
            'event_type' => 'activated',
            'from_status' => App::STATUS_INACTIVE,
            'to_status' => App::STATUS_ACTIVE,
            'actor_type' => 'admin',
            'actor_id' => $this->admin->id,
        ]);

        $this->adminGet("/api/v1/admin/apps/{$app->id}/events")
             ->assertOk()
             ->assertJsonPath('data.0.event_type', 'activated');
    }

    /** @test */
    public function it_returns_401_without_jwt(): void
    {
        $this->getJson('/api/v1/admin/apps')
             ->assertStatus(401);
    }

    /** @test */
    public function it_can_delete_an_app(): void
    {
        $app = App::create([
            'name' => 'Delete Me', 'package_name' => 'com.deleteme',
            'api_key' => 'adniba_del_key', 'app_status' => 1, 'global_ad_enabled' => 1,
        ]);

        $this->adminDelete("/api/v1/admin/apps/{$app->id}")
             ->assertOk()
             ->assertJsonPath('status', 'ok');

        $this->assertDatabaseMissing('apps', ['id' => $app->id]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function adminGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    private function adminPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    private function adminPatch(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->patchJson($uri, $data);
    }

    private function adminDelete(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->deleteJson($uri);
    }
}
