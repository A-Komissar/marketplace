<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateKitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kits', function (Blueprint $table) {
            $table->increments('id')->index()->unsigned();
            $table->unsignedInteger('kit_id')->nullable(true);
            $table->integer('market_id')->default(1);
            $table->foreign('market_id')->references('id')->on('markets')->onDelete('cascade');
            $table->integer('seller_id')->nullable(false);
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->integer('product_id')->nullable(false);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->string('title', 255);
            $table->boolean('is_active')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kits');
    }
}
