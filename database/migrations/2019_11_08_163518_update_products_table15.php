<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateProductsTable15 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('state', 255)->default('new')->nullable(true)->after('description_ua');
            $table->unsignedBigInteger('rozetka_product_id')->nullable(true)->after('rozetka_category_id');
            $table->string('rozetka_product_url', 255)->nullable(true)->after('rozetka_product_id');
            $table->boolean('is_active_at_rozetka')->default(false)->after('rozetka_product_url');
            $table->unsignedInteger('prom_category_id')->nullable(true)->after('is_active_at_rozetka');
            $table->foreign('prom_category_id')->references('id')->on('categories')->onDelete('cascade');

            $table->unsignedInteger('rozetka_category_id')->change();
        });

        if(\App\Models\Market::where('market_code', 'prom')->count() < 1) {
            $prom = new \App\Models\Market();
            $prom->market_code = 'prom';
            $prom->market_name = 'Prom.ua';
            $prom->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('state');
            $table->dropColumn('rozetka_product_id');
            $table->dropColumn('rozetka_product_url');
            $table->dropColumn('prom_category_id');
            $table->dropColumn('is_active_at_rozetka');
        });
    }
}
