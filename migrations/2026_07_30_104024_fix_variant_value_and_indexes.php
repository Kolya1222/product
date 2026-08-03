<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixVariantValueAndIndexes extends Migration
{
    public function up()
    {
        Schema::table('variant_attribute_values', function (Blueprint $table) {
            $table->text('value')->change();
        });

        Schema::table('product_presets', function (Blueprint $table) {
            $table->dropIndex('product_presets_product_id_index');
        });
    }

    public function down()
    {
        Schema::table('variant_attribute_values', function (Blueprint $table) {
            $table->string('value')->change();
        });

        Schema::table('product_presets', function (Blueprint $table) {
            $table->index('product_id');
        });
    }
}
