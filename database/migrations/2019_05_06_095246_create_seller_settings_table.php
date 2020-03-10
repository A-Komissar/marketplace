<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSellerSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seller_settings', function (Blueprint $table) {
            $table->increments('id')->index()->unsigned();
            $table->integer('seller_id')->nullable(false)->unique();
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->string('name');
            $table->string('email', 250);
            $table->string('phone');
            $table->string('company_name');
            $table->string('website_link', 255);
            $table->string('legal_address', 255);
            $table->string('post_address', 255);
            $table->string('checking_account', 255);
            $table->string('telephone_fax');
            $table->string('MFO');
            $table->string('INN');
            $table->string('ЕGRPOU', 255);
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
        Schema::dropIfExists('seller_settings');
    }
}
