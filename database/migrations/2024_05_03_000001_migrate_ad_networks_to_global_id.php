<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // add nullable column to stage migration
        Schema::table('ad_networks', function (Blueprint $table) {
            $table->unsignedBigInteger('global_id')->nullable()->after('app_id');
        });

        // populate global_id by matching name -> global_ad_networks.name
        DB::statement(<<<'SQL'
            UPDATE ad_networks
            JOIN global_ad_networks ON ad_networks.name = global_ad_networks.name
            SET ad_networks.global_id = global_ad_networks.id
        SQL
        );

        // make global_id NOT NULL now that values are populated
        DB::statement('ALTER TABLE `ad_networks` MODIFY `global_id` BIGINT UNSIGNED NOT NULL');

        // drop old unique and replace with unique(app_id, global_id), add FK
        Schema::table('ad_networks', function (Blueprint $table) {
            // drop the previous unique index on (app_id, name)
            $table->dropUnique('uq_app_network');

            // add new unique index on (app_id, global_id)
            $table->unique(['app_id', 'global_id'], 'uq_app_network');

            // add foreign key to global_ad_networks with cascade on delete
            $table->foreign('global_id')
                  ->references('id')->on('global_ad_networks')
                  ->onDelete('cascade');
        });

        // finally remove the old name column
        Schema::table('ad_networks', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        // re-add name as nullable to restore values
        Schema::table('ad_networks', function (Blueprint $table) {
            $table->string('name', 20)->nullable()->after('app_id');
        });

        // populate name from global_ad_networks
        DB::statement(<<<'SQL'
            UPDATE ad_networks
            JOIN global_ad_networks ON ad_networks.global_id = global_ad_networks.id
            SET ad_networks.name = global_ad_networks.name
        SQL
        );

        // make name NOT NULL
        DB::statement('ALTER TABLE `ad_networks` MODIFY `name` VARCHAR(20) NOT NULL');

        // drop FK and unique on global_id, restore unique on (app_id, name)
        Schema::table('ad_networks', function (Blueprint $table) {
            $table->dropForeign(['global_id']);
            $table->dropUnique('uq_app_network');
            $table->unique(['app_id', 'name'], 'uq_app_network');
        });

        // remove global_id column
        Schema::table('ad_networks', function (Blueprint $table) {
            $table->dropColumn('global_id');
        });
    }
};
