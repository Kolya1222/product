<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductAttributesTable extends Migration
{
    public function up()
    {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id')->index();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->unique(['product_id', 'attribute_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_attributes');
    }
}
