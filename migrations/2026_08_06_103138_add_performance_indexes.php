<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPerformanceIndexes extends Migration
{
    public function up()
    {
        $prefix = DB::getTablePrefix();

        try { 
            DB::statement("ALTER TABLE `{$prefix}variant_attribute_values` MODIFY COLUMN `value` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"); 
        } catch (\Exception $e) {}
        
        try { 
            DB::statement("ALTER TABLE `{$prefix}product_attributes` MODIFY COLUMN `value` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"); 
        } catch (\Exception $e) {}

        if (!$this->indexExists('site_content', 'idx_sc_pub_del_parent')) {
            Schema::table('site_content', function (Blueprint $table) {
                $table->index(['published', 'deleted', 'parent'], 'idx_sc_pub_del_parent');
            });
        }

        if (!$this->indexExists('product_variants', 'idx_pv_product_active')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->index(['product_id', 'active'], 'idx_pv_product_active');
            });
        }

        if (!$this->indexExists('variant_attribute_values', 'idx_vav_attr_value')) {
            DB::statement("ALTER TABLE `{$prefix}variant_attribute_values` ADD INDEX `idx_vav_attr_value` (`attribute_id`, `value`(191))");
        }
        if (!$this->indexExists('variant_attribute_values', 'idx_vav_variant_attr')) {
            Schema::table('variant_attribute_values', function (Blueprint $table) {
                $table->index(['variant_id', 'attribute_id'], 'idx_vav_variant_attr');
            });
        }

        if (!$this->indexExists('product_attributes', 'idx_pa_product_id')) {
            Schema::table('product_attributes', function (Blueprint $table) {
                $table->index(['product_id'], 'idx_pa_product_id');
            });
        }
        if (!$this->indexExists('product_attributes', 'idx_pa_attr_value')) {
            DB::statement("ALTER TABLE `{$prefix}product_attributes` ADD INDEX `idx_pa_attr_value` (`attribute_id`, `value`(191))");
        }
    }

    public function down()
    {
        $prefix = DB::getTablePrefix();
        
        Schema::table('site_content', function (Blueprint $table) {
            $table->dropIndex('idx_sc_pub_del_parent');
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('idx_pv_product_active');
        });
        
        if ($this->indexExists('variant_attribute_values', 'idx_vav_attr_value')) {
            DB::statement("ALTER TABLE `{$prefix}variant_attribute_values` DROP INDEX `idx_vav_attr_value`");
        }
        Schema::table('variant_attribute_values', function (Blueprint $table) {
            $table->dropIndex('idx_vav_variant_attr');
        });
        
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropIndex('idx_pa_product_id');
        });
        if ($this->indexExists('product_attributes', 'idx_pa_attr_value')) {
            DB::statement("ALTER TABLE `{$prefix}product_attributes` DROP INDEX `idx_pa_attr_value`");
        }
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $prefix = DB::getTablePrefix();
        $fullTable = $prefix . $table;

        $result = DB::select(
            "SELECT COUNT(*) as aggregate FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $fullTable, $indexName]
        );

        return $result[0]->aggregate > 0;
    }
}