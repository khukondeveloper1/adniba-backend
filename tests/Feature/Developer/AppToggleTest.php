<?php

namespace Tests\Feature\Developer;

use App\Models\App;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AppToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->developer = User::create([
            'name' => 'Developer',
            'email' => 'developer@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'app_limit' => 3,
        ]);

        $this->token = JWTAuth::fromUser($this->developer);
    }

    /** @test */
    public function developer_can_toggle_owned_app_status_and_ads(): void
    {
        $app = App::create([
            'user_id' => $this->developer->id,
            'name' => 'Owned App',
            'package_name' => 'com.owned',
            'api_key' => 'adniba_owned_key',
            'status' => App::STATUS_ACTIVE,
            'global_ad_enabled' => 1,
        ]);

        $this->developerPatch("/api/v1/developer/apps/{$app->id}/status", ['active' => false])
             ->assertOk()
             ->assertJsonPath('data.status', App::STATUS_INACTIVE);

        $this->developerPatch("/api/v1/developer/apps/{$app->id}/ads-enabled", ['enabled' => false])
             ->assertOk()
             ->assertJsonPath('data.global_ad_enabled', false);

        $this->assertDatabaseHas('app_events', [
            'app_id' => $app->id,
            'event_type' => 'deactivated',
            'actor_type' => 'developer',
            'actor_id' => $this->developer->id,
        ]);

        $this->assertDatabaseHas('app_events', [
            'app_id' => $app->id,
            'event_type' => 'global_ads_disabled',
            'actor_type' => 'developer',
            'actor_id' => $this->developer->id,
        ]);
    }

    /** @test */
    public function developer_cannot_toggle_an_app_they_do_not_own(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
            'app_limit' => 3,
        ]);

        $app = App::create([
            'user_id' => $other->id,
            'name' => 'Other App',
            'package_name' => 'com.other',
            'api_key' => 'adniba_other_key',
            'status' => App::STATUS_ACTIVE,
            'global_ad_enabled' => 1,
        ]);

        $this->developerPatch("/api/v1/developer/apps/{$app->id}/status", ['active' => false])
             ->assertStatus(404);
    }

    /** @test */
    public function developer_cannot_deactivate_suspended_app_directly(): void
    {
        $app = App::create([
            'user_id' => $this->developer->id,
            'name' => 'Suspended App',
            'package_name' => 'com.dev.suspended',
            'api_key' => 'adniba_dev_suspended_key',
            'status' => App::STATUS_SUSPENDED,
            'global_ad_enabled' => 1,
            'suspension_reason' => 'Policy violation',
            'suspended_at' => now(),
        ]);

        $this->developerPatch("/api/v1/developer/apps/{$app->id}/status", ['active' => false])
             ->assertStatus(422)
             ->assertJsonPath('status', 'error');
    }

    private function developerPatch(string $uri, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->patchJson($uri, $data);
    }
}
