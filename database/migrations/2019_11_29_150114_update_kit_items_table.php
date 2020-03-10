<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateKitItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kit_items', function (Blueprint $table) {
            $table->integer('relative_discount')->nullable(true)->change();
            $table->integer('fixed_discount')->nullable(true)->change();
            $table->integer('fixed_amount')->nullable(true)->change();
            $table->integer('price_amount')->nullable(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
