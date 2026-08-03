<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndex extends Migration
{
    public function up()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(['product_id', 'active'], 'idx_pv_product_active');
        });
    }

    public function down()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('idx_pv_product_active');
        });
    }
}
