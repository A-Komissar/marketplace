<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSellersTable4 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('bank_code');
            $table->string('legal_code');
            $table->dropColumn('MFO');
            $table->dropColumn('INN');
            $table->dropColumn('ЕGRPOU');
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
            $table->dropColumn('bank_code');
            $table->dropColumn('legal_code');
            $table->string('MFO');
            $table->string('INN');
            $table->string('ЕGRPOU', 255);
        });
    }
}
