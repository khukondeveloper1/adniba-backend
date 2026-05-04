<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            if (!Schema::hasColumn('apps', 'status')) {
                $table->enum('status', ['active', 'inactive', 'suspended'])
                    ->default('active')
                    ->after('api_key');
            }
        });

        $isSqlite = DB::getDriverName() === 'sqlite';

        if (Schema::hasColumn('apps', 'app_status') && Schema::hasColumn('apps', 'is_suspended')) {
            DB::table('apps')->update([
                'status' => DB::raw(
                    $isSqlite
                        ? "CASE WHEN is_suspended = 1 THEN 'suspended' WHEN app_status = 0 THEN 'inactive' ELSE 'active' END"
                        : "CASE WHEN is_suspended = 1 THEN 'suspended' WHEN app_status = 0 THEN 'inactive' ELSE 'active' END"
                ),
            ]);
        } elseif (Schema::hasColumn('apps', 'app_status')) {
            DB::table('apps')->update([
                'status' => DB::raw("CASE WHEN app_status = 0 THEN 'inactive' ELSE 'active' END"),
            ]);
        }

        Schema::table('apps', function (Blueprint $table) {
            $table->index('status', 'idx_apps_status');
        });

        Schema::create('app_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('reason')->nullable();
            $table->enum('actor_type', ['admin', 'developer', 'system']);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('app_id', 'idx_app_id');
            $table->index('event_type', 'idx_event_type');
        });

        Schema::table('apps', function (Blueprint $table) {
            if (Schema::hasColumn('apps', 'app_status')) {
                $table->dropIndex('idx_status');
                $table->dropColumn('app_status');
            }

            if (Schema::hasColumn('apps', 'is_suspended')) {
                $table->dropIndex(['is_suspended']);
                $table->dropColumn('is_suspended');
            }
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            if (!Schema::hasColumn('apps', 'app_status')) {
                $table->tinyInteger('app_status')->default(1)->after('api_key')
                    ->comment('1=active, 0=inactive');
            }

            if (!Schema::hasColumn('apps', 'is_suspended')) {
                $table->tinyInteger('is_suspended')->default(0)->after('global_ad_enabled')
                    ->comment('1=suspended by admin');
            }
        });

        DB::table('apps')->update([
            'app_status' => DB::raw("CASE WHEN status = 'inactive' THEN 0 ELSE 1 END"),
            'is_suspended' => DB::raw("CASE WHEN status = 'suspended' THEN 1 ELSE 0 END"),
        ]);

        Schema::dropIfExists('app_events');

        Schema::table('apps', function (Blueprint $table) {
            $table->index('app_status', 'idx_status');
            $table->index('is_suspended');

            if (Schema::hasColumn('apps', 'status')) {
                $table->dropIndex('idx_apps_status');
                $table->dropColumn('status');
            }
        });
    }
};
