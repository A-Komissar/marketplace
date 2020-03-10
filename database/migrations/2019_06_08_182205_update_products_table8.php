<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateProductsTable8 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->double('price_old')->default(0.01)->after('price');
            $table->integer('warranty')->default(0);
            $table->string('country_origin')->nullable(true);
            $table->string('country_brand')->nullable(true);
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
            $table->dropColumn('price_old');
            $table->dropColumn('warranty');
            $table->dropColumn('country_origin');
            $table->dropColumn('country_brand');
        });
    }
}
