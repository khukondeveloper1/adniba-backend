<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('app_id');
            $table->string('network', 20);
            $table->string('ad_type', 20);
            $table->string('placement', 50);
            $table->string('event_type', 20)
                  ->comment('request|load|impression|click|fail');
            $table->timestamp('created_at')->useCurrent();

            // Analytics: time + event filter
            $table->index(['app_id', 'created_at', 'event_type'], 'idx_analytics');

            // Network performance analysis
            $table->index(['app_id', 'network', 'created_at'], 'idx_network');

            // Placement-level performance
            $table->index(['app_id', 'ad_type', 'placement', 'created_at'], 'idx_placement');

            // Daily rollup queries
            $table->index(['app_id', 'event_type', 'created_at'], 'idx_daily');
        });

        // Apply ROW_FORMAT=COMPRESSED for disk efficiency on high-volume inserts
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ad_events ROW_FORMAT=COMPRESSED');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_events');
    }
};
