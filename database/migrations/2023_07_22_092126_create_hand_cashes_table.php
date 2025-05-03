<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHandCashesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hand_cashes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('types')->nullable();
            $table->string('amount')->nullable();
            $table->string('date')->nullable();
            $table->string('rules')->nullable();
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
        Schema::dropIfExists('hand_cashes');
    }
}
