<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttrsJsonToProductVariantsTable extends Migration
{
    public function up()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('attrs_json')->nullable()->after('active');
        });
    }

    public function down()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('attrs_json');
        });
    }
}
