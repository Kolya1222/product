<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddTextIndexToVariantValues extends Migration
{
    public function up()
    {
        Schema::table('variant_attribute_values', function (Blueprint $table) {
            $table->index([DB::raw('`value`(191)')], 'idx_vav_attr_value_text');
        });
    }

    public function down()
    {
        Schema::table('variant_attribute_values', function (Blueprint $table) {
            $table->dropIndex('idx_vav_attr_value_text');
        });
    }
}