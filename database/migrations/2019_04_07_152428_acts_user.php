<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ActsUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('act_user', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('act_id');
            $table->integer('mp_user_id');
            $table->integer('status')->default(0);  // 0:为开奖 1:未中奖 2:已中奖
            $table->index(['act_id', 'status', 'mp_user_id']);
            $table->index(['mp_user_id', 'status']);
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
        //
        Schema::drop('act_user');
    }
}
