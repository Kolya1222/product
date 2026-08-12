<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateImportTablesAndHashes extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('product_import_configs')) {
            Schema::create('product_import_configs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('source_type')->default('csv');
                $table->json('mapping')->nullable();
                $table->json('transformers')->nullable();
                $table->string('sync_mode')->default('incremental');
                $table->boolean('create_attrs')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('product_variants', 'sync_hash')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->string('sync_hash', 40)->nullable()->index()->after('attrs_json');
            });
        }

        $prefix = DB::getTablePrefix();

        if (!Schema::hasColumn('product_attributes', 'value_hash')) {
            DB::statement("ALTER TABLE `{$prefix}product_attributes` ADD COLUMN `value_hash` CHAR(32) GENERATED ALWAYS AS (MD5(`value`)) STORED");
            DB::statement("ALTER TABLE `{$prefix}product_attributes` ADD INDEX `idx_pa_value_hash` (`attribute_id`, `value_hash`)");
        }

        if (!Schema::hasColumn('variant_attribute_values', 'value_hash')) {
            DB::statement("ALTER TABLE `{$prefix}variant_attribute_values` ADD COLUMN `value_hash` CHAR(32) GENERATED ALWAYS AS (MD5(`value`)) STORED");
            DB::statement("ALTER TABLE `{$prefix}variant_attribute_values` ADD INDEX `idx_vav_value_hash` (`attribute_id`, `value_hash`)");
        }
    }

    public function down()
    {
        $prefix = DB::getTablePrefix();

        if (Schema::hasColumn('product_attributes', 'value_hash')) {
            DB::statement("ALTER TABLE `{$prefix}product_attributes` DROP INDEX `idx_pa_value_hash`");
            DB::statement("ALTER TABLE `{$prefix}product_attributes` DROP COLUMN `value_hash`");
        }
        
        if (Schema::hasColumn('variant_attribute_values', 'value_hash')) {
            DB::statement("ALTER TABLE `{$prefix}variant_attribute_values` DROP INDEX `idx_vav_value_hash`");
            DB::statement("ALTER TABLE `{$prefix}variant_attribute_values` DROP COLUMN `value_hash`");
        }
        
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('sync_hash');
        });

        Schema::dropIfExists('product_import_configs');
    }
}