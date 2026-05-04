<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('app_id');
            $table->unsignedBigInteger('network_id');
            $table->string('ad_type', 20)->comment('banner|interstitial|rewarded|native|app_open');
            $table->string('placement', 50)->comment('home|splash|result|...');
            $table->string('unit_id', 200);
            $table->integer('priority')->default(1)->comment('lower = higher priority');
            $table->tinyInteger('enabled')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Uniqueness: one ad unit per app+network+type+placement combo
            $table->unique(['app_id', 'network_id', 'ad_type', 'placement'], 'uq_unique');

            // Hot path index for getConfig queries
            $table->index(['app_id', 'ad_type', 'placement'], 'idx_fetch');

            // For priority-sorted config queries
            $table->index(['app_id', 'ad_type', 'placement', 'priority'], 'idx_priority');

            $table->foreign('network_id')
                  ->references('id')->on('ad_networks')
                  ->onDelete('cascade');

            $table->foreign('app_id')
                  ->references('id')->on('apps')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_units');
    }
};
