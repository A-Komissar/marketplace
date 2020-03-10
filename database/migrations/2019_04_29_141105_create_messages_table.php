<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->bigIncrements('id')->index()->unsigned();
            $table->integer('seller_id')->nullable(false);
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            $table->integer('admin_id');
            $table->foreign('admin_id')->references('id')->on('admins');
            $table->text('message');
            $table->boolean('written_by_admin')->nullable(false)->default(false);
            $table->boolean('new')->nullable(false)->default(true);
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
        Schema::dropIfExists('messages');
    }
}
