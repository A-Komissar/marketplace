<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateProductsTable6 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('languages');
        Schema::dropIfExists('product_descriptions');
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_ru')->nullable(false);
            $table->string('name_ua')->nullable(false);
            $table->text('description_ru')->nullable(true);
            $table->text('description_ua')->nullable(true);
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
            $table->dropColumn('name_ru');
            $table->dropColumn('name_ua');
            $table->dropColumn('description_ru');
            $table->dropColumn('description_ua');
        });
        Schema::create('languages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('language');
        });
        DB::table('languages')->insert(['language' => 'ua']);
        DB::table('languages')->insert(['language' => 'ru']);
        Schema::create('product_descriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->string('name')->nullable(false);
            $table->text('description')->nullable(true);
            $table->integer('lang_id');
            $table->foreign('lang_id')->references('id')->on('languages')->onDelete('cascade');
        });
    }
}
