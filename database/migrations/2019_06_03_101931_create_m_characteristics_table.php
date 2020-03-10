<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMCharacteristicsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_characteristics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('market_id');
            $table->foreign('market_id')->references('id')->on('markets')->onDelete('cascade');
            $table->integer('category_id');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->bigInteger('characteristic_id')->nullable(true);
            $table->string('name');
            $table->string('attr_type')->nullable(true);
            $table->string('filter_type')->nullable(true);
            $table->bigInteger('value_id')->nullable(true);
            $table->string('value_name')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_characteristics');
    }
}
