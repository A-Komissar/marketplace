<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->increments('id')->index()->unsigned();
            $table->integer('chat_id')->nullable(true);
            $table->integer('market_id')->default(1);
            $table->foreign('market_id')->references('id')->on('markets')->onDelete('cascade');
            $table->integer('client_id')->nullable(true);
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->integer('order_id')->nullable(true);
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->integer('product_id')->nullable(true);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->text('subject');
            $table->integer('type')->nullable(true);
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
        Schema::dropIfExists('chats');
    }
}
