<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSellerExtraEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seller_extra_emails', function (Blueprint $table) {
            $table->increments('id')->index()->unsigned();
            $table->integer('seller_id')->nullable(false);
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->string('name', 255)->nullable(true);
            $table->string('email', 255);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('seller_extra_emails');
    }
}
