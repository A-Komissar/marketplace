<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateKitItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kit_items', function (Blueprint $table) {
            $table->increments('id')->index()->unsigned();

            $table->integer('kit_id')->nullable(false);
            $table->foreign('kit_id')->references('id')->on('kits')->onDelete('cascade');

            $table->integer('product_id')->nullable(false);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            $table->decimal('relative_discount', 10, 2)->nullable(true);
            $table->decimal('fixed_discount', 10,2)->nullable(true);
            $table->decimal('fixed_amount', 10, 2)->nullable(true);
            $table->decimal('price_amount', 10, 2)->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kit_items');
    }
}
