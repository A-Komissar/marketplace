<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('orders');
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('market_id');
            $table->foreign('market_id')->references('id')->on('markets')->onDelete('cascade');
            $table->integer('order_id');
            $table->string('user_phone')->nullable(true)->default(null);
            $table->integer('status_id');
            $table->foreign('status_id')->references('id')->on('order_status');
            $table->string('ttn')->default('');
            $table->double('total_price');
            $table->boolean('new_admin')->default(true);
            $table->timestamps();
        });
        Schema::create('orders_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->integer('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->integer('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->integer('quantity');
            $table->double('price');
            $table->boolean('new_seller')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
