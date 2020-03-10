<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSellerExtrasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seller_extras', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('seller_id')->nullable(false);
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');

            $table->string('contract_number')->nullable(true);
            $table->date('contract_date')->nullable(true);

            $table->string('accountant_name')->nullable(true);
            $table->string('accountant_email')->nullable(true);
            $table->string('accountant_phone')->nullable(true);
            $table->string('accountant_viber')->nullable(true);
            $table->string('accountant_telegram')->nullable(true);

            $table->string('manager_name')->nullable(true);
            $table->string('manager_email')->nullable(true);
            $table->string('manager_phone')->nullable(true);
            $table->string('manager_viber')->nullable(true);
            $table->string('manager_telegram')->nullable(true);

            $table->string('warehouse_name')->nullable(true);
            $table->string('warehouse_email')->nullable(true);
            $table->string('warehouse_phone')->nullable(true);
            $table->string('warehouse_viber')->nullable(true);
            $table->string('warehouse_telegram')->nullable(true);

            $table->text('warehouse_address')->nullable(true);
            $table->text('np_address')->nullable(true);
            $table->text('pickup_address')->nullable(true);

            $table->string('own_post_service')->nullable(true);
            $table->double('shipping_price')->default(0.01);
            $table->time('shipping_today')->nullable(true);
            $table->string('ownership_type')->nullable(true);
            $table->string('funds_accepting')->nullable(true);
            $table->string('schedule')->nullable(true);

            $table->string('working_hours_weekdays')->nullable(true);
            $table->string('working_hours_weekends')->nullable(true);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('seller_extras');
    }
}
