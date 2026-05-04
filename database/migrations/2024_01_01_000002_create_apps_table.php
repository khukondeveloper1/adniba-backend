<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('package_name', 100)->unique();
            $table->text('app_logo')->nullable();
            $table->string('api_key', 100)->unique();
            $table->tinyInteger('app_status')->default(1)->comment('1=active, 0=inactive');
            $table->tinyInteger('global_ad_enabled')->default(1)->comment('1=ads on, 0=ads off');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('app_status', 'idx_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
