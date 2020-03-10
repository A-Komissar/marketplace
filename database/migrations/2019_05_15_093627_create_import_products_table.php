<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateImportProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('import_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->string('import_url')->nullable(true);
            $table->string('import_type')->default('XML');
            $table->string('category')->nullable(false);
            $table->string('article')->nullable(false);
            $table->string('name_ru')->nullable(false);
            $table->string('name_ua')->nullable(true);
            $table->string('description_ru')->nullable(true);
            $table->string('description_ua')->nullable(true);
            $table->string('price')->nullable(false);
            $table->string('stock')->nullable(false);
            $table->string('brand')->nullable(false);
            $table->string('photo')->nullable(false);
            $table->string('main_photo')->nullable(true);
            $table->text('additional_JSON')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('import_products');
    }
}
