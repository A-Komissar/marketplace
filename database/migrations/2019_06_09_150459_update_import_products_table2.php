<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateImportProductsTable2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('import_products', function (Blueprint $table) {
            $table->string('price_old')->nullable(true)->after('price');
            $table->string('country_origin')->nullable(true)->after('brand');
            $table->string('country_brand')->nullable(true)->after('country_origin');
            $table->string('warranty')->nullable(true)->after('country_brand');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('import_products', function (Blueprint $table) {
            $table->dropColumn('price_old');
            $table->dropColumn('warranty');
            $table->dropColumn('country_origin');
            $table->dropColumn('country_brand');
        });
    }
}
