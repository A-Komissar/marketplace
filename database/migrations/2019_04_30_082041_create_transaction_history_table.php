<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_history', function (Blueprint $table) {
            $table->bigIncrements('id')->index()->unsigned();
            $table->integer('seller_id')->nullable(false);
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->integer('admin_id');
            $table->foreign('admin_id')->references('id')->on('admins');
            $table->text('description');
            $table->double('transaction_value');
            $table->double('balance_before');
            $table->double('balance_after');
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
        Schema::dropIfExists('transaction_history');
    }
}
