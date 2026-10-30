<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Results of `php artisan analytics:run`. Each run adds rows under a new run_id; reports read the latest.
    public function up()
    {
        Schema::create('analytics_model_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 20)->index();
            $table->string('series', 80);
            $table->string('model', 40);
            $table->unsignedSmallInteger('n_points');
            $table->decimal('mase', 10, 4)->nullable();
            $table->decimal('smape', 10, 4)->nullable();
            $table->decimal('rmse', 15, 2)->nullable();
            $table->decimal('cover80', 5, 4)->nullable();
            $table->decimal('cover95', 5, 4)->nullable();
            $table->boolean('chosen')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 20)->index();
            $table->string('series', 80);
            $table->char('month', 7);
            $table->string('model', 40);
            $table->decimal('mean', 15, 2);
            $table->decimal('lo80', 15, 2);
            $table->decimal('hi80', 15, 2);
            $table->decimal('lo95', 15, 2);
            $table->decimal('hi95', 15, 2);
            $table->timestamps();
        });

        Schema::create('analytics_decompositions', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 20)->index();
            $table->string('series', 80);
            $table->char('month', 7);
            $table->decimal('observed', 15, 2);
            $table->decimal('trend', 15, 2);
            $table->decimal('seasonal', 15, 2);
            $table->decimal('remainder', 15, 2);
            $table->timestamps();
        });

        Schema::create('analytics_anomalies', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 20)->index();
            $table->string('kind', 20); // month | changepoint | transaction
            $table->string('series', 80)->nullable();
            $table->char('month', 7)->nullable();
            $table->unsignedBigInteger('expense_calculation_id')->nullable(); // no FK: never cascades into ledger data
            $table->decimal('value', 15, 2)->nullable();
            $table->decimal('expected', 15, 2)->nullable();
            $table->decimal('score', 10, 4)->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        foreach (['analytics_anomalies', 'analytics_decompositions', 'analytics_forecasts', 'analytics_model_runs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
