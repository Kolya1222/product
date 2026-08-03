<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttributePresetAttributesTable extends Migration
{
    public function up()
    {
        Schema::create('attribute_preset_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preset_id')->constrained('attribute_presets')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->json('generation_config')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unique(['preset_id', 'attribute_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attribute_preset_attributes');
    }
}
