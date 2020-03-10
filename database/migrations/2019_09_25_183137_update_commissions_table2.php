<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateCommissionsTable2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->integer('seller_id')->default(0)->nullable(true);
            $table->foreign('seller_id')->references('id')->on('sellers');
            $table->dropUnique(array('market_id', 'category_id'));
            $table->unique(array('market_id', 'category_id', 'seller_id'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
