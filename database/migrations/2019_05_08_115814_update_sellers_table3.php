<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSellersTable3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('prefix', 4);
        });

        $sellers = \App\Models\Seller::where('prefix', '')->get();
        foreach($sellers as $seller) {
            while (true) {
                try {
                    $str = "";
                    $letters = array_merge(range('A','Z'));
                    $digits = array_merge(range('0','9'));
                    $max_letters = count($letters) - 1;
                    $max_digits = count($digits) - 1;
                    for ($i = 0; $i < 2; $i++) {
                        $rand = mt_rand(0, $max_letters);
                        $str .= $letters[$rand];
                    }
                    for ($i = 0; $i < 2; $i++) {
                        $rand = mt_rand(0, $max_digits);
                        $str .= $digits[$rand];
                    }
                    $seller->prefix = $str;
                    $seller->save();
                    break;
                } catch (Exception $e) { }
            }
        }

        Schema::table('sellers', function (Blueprint $table) {
            $table->string('prefix', 4)->unique()->change();
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
            $table->dropColumn('prefix');
        });
    }
}
