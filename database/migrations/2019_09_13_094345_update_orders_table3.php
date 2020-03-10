<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateOrdersTable3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_service')->nullable(true)->after('ttn');
            $table->string('delivery_office')->nullable(true)->after('delivery_service');
            $table->string('delivery_recipient')->nullable(true)->after('delivery_office');
            $table->text('delivery_address')->nullable(true)->after('delivery_recipient');
            $table->string('delivery_city')->nullable(true)->after('delivery_address');
            $table->string('delivery_region')->nullable(true)->after('delivery_city');
            $table->string('delivery_cost')->nullable(true)->after('delivery_region');
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
