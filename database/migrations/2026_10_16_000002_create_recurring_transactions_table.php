<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('category_id');
            $table->decimal('amount', 15, 2);
            $table->unsignedTinyInteger('day_of_month'); // 1-31, clamped to short months
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_on')->nullable();
            $table->date('last_run_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'next_run_on']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
