<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('app_id');
            $table->string('ad_type', 20);
            $table->string('placement', 50);
            $table->tinyInteger('fallback_enabled')->default(1)
                  ->comment('1=mediation/fallback, 0=force single network');
            $table->unsignedBigInteger('network_id')->nullable()
                  ->comment('NULL=auto mediation, set=forced network');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['app_id', 'ad_type', 'placement'], 'uq_override');
            $table->index(['app_id', 'ad_type', 'placement'], 'idx_lookup');

            $table->foreign('app_id')
                  ->references('id')->on('apps')
                  ->onDelete('cascade');

            $table->foreign('network_id')
                  ->references('id')->on('ad_networks')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_settings');
    }
};
