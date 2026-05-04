<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-managed list of available networks
        // Developers will see these as "available networks" to add to their apps
        Schema::create('global_ad_networks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('admob|meta|unity|etc');
            $table->string('display_name', 100);
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->tinyInteger('is_active')->default(1)->comment('1=visible to developers');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default networks
        DB::table('global_ad_networks')->insert([
            [
                'name'         => 'admob',
                'display_name' => 'AdMob',
                'description'  => 'Google AdMob - Mobile Advertising',
                'website_url'  => 'https://admob.google.com',
                'is_active'    => 1,
                'sort_order'   => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'name'         => 'meta',
                'display_name' => 'Meta Audience Network',
                'description'  => 'Facebook/Meta Audience Network',
                'website_url'  => 'https://www.facebook.com/audiencenetwork',
                'is_active'    => 1,
                'sort_order'   => 2,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'name'         => 'unity',
                'display_name' => 'Unity Ads',
                'description'  => 'Unity Technologies Advertising Platform',
                'website_url'  => 'https://unity.com/solutions/unity-ads',
                'is_active'    => 1,
                'sort_order'   => 3,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('global_ad_networks');
    }
};
