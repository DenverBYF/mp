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
            $table->increments('id');
            $table->string('name', 50);
            $table->string('desc', 255);
            $table->integer('max_number')->default(50);
            $table->integer('gift_id')->unique();
            $table->integer('mp_user_id')->index();
            $table->integer('gift_number')->default(1);
            $table->timestamp('open_time');
            $table->integer('rule_id')->index()->default(1);
            $table->integer('pay_value')->default(0);
            $table->integer('status')->default(0);
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
        Schema::dropIfExists('acts');
    }
}
