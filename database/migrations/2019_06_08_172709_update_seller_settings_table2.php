<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSellerSettingsTable2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('seller_settings', function (Blueprint $table) {
            $table->string('bank_code')->nullable(true)->default(null);
            $table->string('legal_code')->nullable(true)->default(null);
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
        Schema::table('seller_settings', function (Blueprint $table) {
            $table->dropColumn('bank_code');
            $table->dropColumn('legal_code');
            $table->string('MFO')->nullable(true)->default(null);
            $table->string('INN')->nullable(true)->default(null);
            $table->string('ЕGRPOU', 255)->nullable(true)->default(null);
        });
    }
}
