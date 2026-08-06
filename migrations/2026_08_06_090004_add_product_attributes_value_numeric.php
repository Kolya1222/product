<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddProductAttributesValueNumeric extends Migration

{
    public function up()
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->decimal('value_numeric', 14, 2)->nullable()->after('value');
            $table->index(['attribute_id', 'value_numeric'], 'idx_pa_attr_numeric');
        });
        
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->index([DB::raw('`value`(191)')], 'idx_pa_attr_value_text');
        });
    }

    public function down()
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropIndex('idx_pa_attr_numeric');
            $table->dropIndex('idx_pa_attr_value_text');
            $table->dropColumn('value_numeric');
        });
    }
}
