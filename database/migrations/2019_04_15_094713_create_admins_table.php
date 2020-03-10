<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdminsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->increments('id')->index()->unsigned();
            $table->string('name');
            $table->string('email', 250)->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // insert admin
        DB::table('admins')->insert(
            array(
                'email' => 'admin@mail.com',
                'name' => 'admin',
                'password' => '$2b$10$J6eJfpqt3A8zWCBzD6P1eOHWyK26.f2DgtmASII.OCrgW2vzY7KsC'
            )
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('admins');
    }
}
