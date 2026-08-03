<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveDuplicateProductVariantsIndex extends Migration
{
    public function up()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('product_variants_product_id_index');
        });
    }

    public function down()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('product_id');
        });
    }
}
