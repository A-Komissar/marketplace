<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPromOrderStatuses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('order_status')->insert(
            array(
                'id' => 44,
                'market_id' => 2,
                'key' => 1,
                'value' => 'pending'
            )
        );
        DB::table('order_status')->insert(
            array(
                'id' => 45,
                'market_id' => 2,
                'key' => 2,
                'value' => 'received'
            )
        );
        DB::table('order_status')->insert(
            array(
                'id' => 46,
                'market_id' => 2,
                'key' => 3,
                'value' => 'in_progress'
            )
        );
        DB::table('order_status')->insert(
            array(
                'id' => 50,
                'market_id' => 2,
                'key' => 4,
                'value' => 'dispatched'
            )
        );
        DB::table('order_status')->insert(
            array(
                'id' => 47,
                'market_id' => 2,
                'key' => 5,
                'value' => 'paid'
            )
        );
        DB::table('order_status')->insert(
            array(
                'id' => 48,
                'market_id' => 2,
                'key' => 6,
                'value' => 'delivered'
            )
        );
        DB::table('order_status')->insert(
            array(
                'id' => 49,
                'market_id' => 2,
                'key' => 7,
                'value' => 'canceled'
            )
        );
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
