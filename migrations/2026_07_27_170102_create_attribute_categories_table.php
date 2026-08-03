<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttributeCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('attribute_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('attribute_categories')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('attribute_categories');
    }
}
