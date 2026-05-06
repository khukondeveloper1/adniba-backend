<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_logs')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE email_logs MODIFY type ENUM(
                    'limit_approved',
                    'limit_rejected',
                    'announcement',
                    'custom',
                    'welcome',
                    'email_verification',
                    'password_reset',
                    'password_reset_success'
                ) NOT NULL DEFAULT 'custom'"
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_logs')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE email_logs MODIFY type ENUM(
                    'limit_approved',
                    'limit_rejected',
                    'announcement',
                    'custom',
                    'welcome',
                    'email_verification',
                    'password_reset'
                ) NOT NULL DEFAULT 'custom'"
            );
        }
    }
};
