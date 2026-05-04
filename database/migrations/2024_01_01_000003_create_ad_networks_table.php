<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_networks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('app_id');
            $table->string('name', 20)->comment('admob|meta|unity');
            $table->tinyInteger('enabled')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['app_id', 'name'], 'uq_app_network');
            $table->index(['app_id', 'enabled'], 'idx_app_enabled');

            $table->foreign('app_id')
                  ->references('id')->on('apps')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_networks');
    }
};
