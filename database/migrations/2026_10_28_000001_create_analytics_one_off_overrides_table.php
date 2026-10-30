<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Manual one-off / not-one-off flag for a ledger row; overrides LedgerSeries' detection.
        Schema::create('analytics_one_off_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_calculation_id')->unique(); // no FK: never cascades into ledger data
            $table->boolean('is_one_off');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('analytics_one_off_overrides');
    }
};
