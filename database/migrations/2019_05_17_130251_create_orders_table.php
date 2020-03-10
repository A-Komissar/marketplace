<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->integer('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->integer('market_id');
            $table->foreign('market_id')->references('id')->on('markets')->onDelete('cascade');
            $table->string('payment_type');
            $table->integer('order_no');
            $table->string('status');
            $table->double('quantity');
            $table->double('price');
            $table->double('total_price');
            $table->double('funds_num');
            $table->boolean('new_seller')->default(true);
            $table->boolean('new_admin')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
