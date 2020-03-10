<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateProductsTable2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('manufacturer');
            $table->integer('brand_id');
            $table->foreign('brand_id')->references('id')->on('product_brands')->onDelete('cascade');
            $table->float('price')->default(0)->change();
            $table->integer('stock')->default(0)->change();
            $table->string('article')->default('')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('manufacturer');
            $table->dropColumn('brand_id');
            $table->string('stock')->change();
            $table->string('article')->change();
        });
    }
}
