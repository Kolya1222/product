<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductVariantAttributesTable extends Migration
{
    public function up()
    {
        Schema::create('product_variant_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id')->index();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->unique(['product_id', 'attribute_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_variant_attributes');
    }
}
