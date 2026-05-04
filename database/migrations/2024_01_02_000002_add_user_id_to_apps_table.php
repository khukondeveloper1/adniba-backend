<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            // nullable so existing apps (admin-created) still work
            $table->unsignedBigInteger('user_id')
                  ->nullable()
                  ->after('id')
                  ->comment('NULL = admin-created app');

            $table->index('user_id', 'idx_apps_user');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex('idx_apps_user');
            $table->dropColumn('user_id');
        });
    }
};
