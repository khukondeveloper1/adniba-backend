<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            // App suspension system
            $table->tinyInteger('is_suspended')->default(0)->after('global_ad_enabled')
                  ->comment('1=suspended by admin');
            $table->text('suspension_reason')->nullable()->after('is_suspended');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');

            // Play Store / App Store data
            $table->string('play_store_url')->nullable()->after('app_logo')
                  ->comment('Google Play Store URL');
            $table->string('app_store_url')->nullable()->after('play_store_url')
                  ->comment('Apple App Store URL');

            $table->index('is_suspended');
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn([
                'is_suspended',
                'suspension_reason',
                'suspended_at',
                'play_store_url',
                'app_store_url',
            ]);
        });
    }
};
