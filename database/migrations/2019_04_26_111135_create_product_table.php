<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::enableForeignKeyConstraints();

        Schema::create('product', function (Blueprint $table) {
            $table->increments('id')->index()->unsigned();
            $table->integer('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers');
            $table->string('model');
            $table->string('manufacturer');
            $table->float('price');
            $table->string('stock');
            $table->string('name');
            $table->text('description');
            $table->boolean('new')->default(true);
            $table->boolean('status')->default(false);
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
        Schema::table('product', function (Blueprint $table) {

            Schema::drop('product');

        });
    }
}
