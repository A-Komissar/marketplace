<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateProductsTable5 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->boolean('disabled')->default(false);
            $table->string('brand')->nullable(false)->change();
            $table->string('article')->nullable(false)->change();
            $table->float('price')->default(0.01)->change();
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
            $table->integer('status');
            $table->dropColumn('disabled');
        });
    }
}
