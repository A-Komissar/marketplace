<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateActsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('acts', function (Blueprint $table) {
            $table->increments('id')->index()->unsigned();
            $table->integer('seller_id')->nullable(false);
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->string('file', 255)->nullable(true);
            $table->date('start_date')->nullable(true);
            $table->date('end_date')->nullable(true);
            $table->boolean('is_monthly_act')->default(true);
            $table->timestamps();
        });

        Schema::table('seller_extras', function (Blueprint $table) {
            $table->string('legal_name_short', 255)->nullable(true)->after('contract_date');
            $table->text('legal_name_long')->nullable(true)->after('legal_name_short');
            $table->text('legal_code_text')->nullable(true)->after('legal_name_long');
            $table->text('legal_info_text')->nullable(true)->after('legal_code_text');
            $table->string('act_signature_name', 255)->nullable(true)->default('ФОП')->after('legal_info_text');
            $table->string('act_signature_decoding', 255)->nullable(true)->after('act_signature_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('acts');
    }
}
