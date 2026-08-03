<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingIndexes extends Migration
{
    public function up()
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->index('attribute_id');
        });

        Schema::table('product_variant_attributes', function (Blueprint $table) {
            $table->index('attribute_id');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('sort');
        });
    }

    public function down()
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropIndex(['attribute_id']);
        });

        Schema::table('product_variant_attributes', function (Blueprint $table) {
            $table->dropIndex(['attribute_id']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['sort']);
        });
    }
}
