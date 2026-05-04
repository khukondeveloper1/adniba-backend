<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('name')
                  ->comment('Profile picture URL or path');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('company', 100)->nullable()->after('phone');
            $table->string('website')->nullable()->after('company');
            $table->string('password_reset_token')->nullable()->after('remember_token');
            $table->timestamp('password_reset_expires_at')->nullable()->after('password_reset_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar',
                'phone',
                'company',
                'website',
                'password_reset_token',
                'password_reset_expires_at',
            ]);
        });
    }
};
