<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVariantAttributeValuesTable extends Migration
{
    public function up()
    {
        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->string('value');
            $table->decimal('value_numeric', 14, 2)->nullable();
            $table->unique(['variant_id', 'attribute_id']);
            $table->index(['attribute_id', 'value_numeric'], 'idx_vav_attr_numeric');
        });
    }

    public function down()
    {
        Schema::dropIfExists('variant_attribute_values');
    }
}
