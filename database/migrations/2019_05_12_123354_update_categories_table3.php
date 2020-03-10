<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateCategoriesTable3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name');
        });
        Schema::dropIfExists('categories_description');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('name');
        });
        Schema::create('categories_description', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('category_id');
            $table->foreign('category_id')->references('category_id')->on('categories')->onDelete('cascade');
            $table->string('name');
            $table->integer('lang_id');
            $table->foreign('lang_id')->references('id')->on('languages')->onDelete('cascade');
        });
    }
}
