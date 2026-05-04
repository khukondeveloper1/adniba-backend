<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password_hash', 255);
            $table->timestamp('created_at')->useCurrent();
        });

        // Seed default admin (change password after deploy)
        DB::table('admin_users')->insert([
            'username'      => 'admin',
            'password_hash' => Hash::make('changeme123'),
            'created_at'    => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
