<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductPresetsTable extends Migration
{
    public function up()
    {
        Schema::create('product_presets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id')->index();
            $table->foreignId('preset_id')->constrained('attribute_presets')->cascadeOnDelete();
            $table->timestamp('applied_at')->useCurrent();
            $table->unique(['product_id', 'preset_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_presets');
    }
}
