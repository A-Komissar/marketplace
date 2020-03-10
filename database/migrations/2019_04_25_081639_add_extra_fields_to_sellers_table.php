<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddExtraFieldsToSellersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sellers', function (Blueprint $table) {
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
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->dropColumn('company_name');
            $table->dropColumn('website_link');
            $table->dropColumn('legal_address');
            $table->dropColumn('post_address');
            $table->dropColumn('checking_account');
            $table->dropColumn('telephone_fax');
            $table->dropColumn('MFO');
            $table->dropColumn('INN');
            $table->dropColumn('ЕGRPOU');
        });
    }
}
