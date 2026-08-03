<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeys extends Migration
{
    public function up()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('site_content')->onDelete('cascade');
        });

        Schema::table('product_attributes', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('site_content')->onDelete('cascade');
        });

        Schema::table('product_variant_attributes', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('site_content')->onDelete('cascade');
        });

        Schema::table('product_presets', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('site_content')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
        Schema::table('product_variant_attributes', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
        Schema::table('product_presets', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
    }
}
