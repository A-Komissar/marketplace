<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('approved')->default(false);
            $table->integer('status')->change();
            $table->string('article');
            $table->dropColumn('name');
            $table->dropColumn('description');
            $table->dropColumn('model');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('approved');
            $table->boolean('status')->change();
            $table->string('name');
            $table->text('description');
            $table->string('model');
        });
    }
}
