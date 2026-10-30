<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Phase 3 results of `php artisan analytics:run`, kept per run_id like the other analytics tables.
    public function up()
    {
        Schema::create('analytics_recurring', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 20)->index();
            $table->string('source', 10); // ledger | account
            $table->string('group_key', 40); // category id, or hand-cash type|account
            $table->string('name');
            $table->string('cadence', 10);
            $table->unsignedSmallInteger('occurrences');
            $table->decimal('median_gap_days', 6, 1);
            $table->decimal('amount', 15, 2);
            $table->decimal('monthly_equivalent', 15, 2);
            $table->date('last_date');
            $table->date('next_date');
            $table->json('calendar'); // expected dates over the next 12 months
            $table->timestamps();
        });

        Schema::create('analytics_simulations', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 20)->index();
            $table->string('kind', 20); // hand_cash | savings | events
            $table->json('result');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('analytics_simulations');
        Schema::dropIfExists('analytics_recurring');
    }
};
