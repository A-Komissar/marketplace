<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSellerSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('seller_settings', function (Blueprint $table) {
            $table->string('name')->nullable(true)->default(null)->change();
            $table->string('email', 250)->nullable(true)->default(null)->change();
            $table->string('phone')->nullable(true)->default(null)->change();
            $table->string('company_name')->nullable(true)->default(null)->change();
            $table->string('website_link', 255)->nullable(true)->default(null)->change();
            $table->string('legal_address', 255)->nullable(true)->default(null)->change();
            $table->string('post_address', 255)->nullable(true)->default(null)->change();
            $table->string('checking_account', 255)->nullable(true)->default(null)->change();
            $table->string('telephone_fax')->nullable(true)->default(null)->change();
            $table->string('MFO')->nullable(true)->default(null)->change();
            $table->string('INN')->nullable(true)->default(null)->change();
            $table->string('ЕGRPOU', 255)->nullable(true)->default(null)->change();
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
