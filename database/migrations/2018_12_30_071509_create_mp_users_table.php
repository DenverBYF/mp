<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMpUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mp_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('address', 255)->nullable();
            $table->string('phone', 20)->unique()->nullable();
            $table->string('email', 50)->unique()->nullable();
            $table->string('openid', 100)->unique();
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
        Schema::dropIfExists('mp_users');
    }
}
