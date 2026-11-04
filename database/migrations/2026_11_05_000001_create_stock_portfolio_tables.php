<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DSE stock portfolio: the inputs of the owner's "Stok Informations" workbook.
 * Holdings, P/L, cash balance and maturity are computed from these (App\Services\StockPortfolio).
 * source_row ("Trades!12") lets stocks:import re-run without duplicating rows.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 32)->unique();
            $table->string('company')->nullable();
            $table->string('category', 4)->default('A'); // DSE category: decides T+ settlement
            $table->decimal('price', 12, 4)->nullable(); // latest market price (LTP)
            $table->decimal('prev_close', 12, 4)->nullable(); // yesterday's close, for day change
            $table->date('price_date')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_trades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('date');
            $table->string('side', 4); // Buy / Sell
            $table->string('symbol', 32);
            $table->string('market', 8)->default('Public'); // Public / Spot
            $table->unsignedInteger('qty');
            $table->decimal('rate', 20, 10); // workbook rates are averages with up to 10 decimals
            $table->decimal('commission', 16, 6);
            $table->string('note')->nullable();
            $table->string('source_row', 32)->nullable()->unique();
            $table->timestamps();

            $table->index(['symbol', 'date']);
        });

        Schema::create('stock_cash', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('date');
            $table->string('type', 16); // Deposit / Withdraw / Fee / Dividend / Other
            $table->string('particulars')->nullable();
            $table->decimal('cash_in', 15, 4)->default(0);
            $table->decimal('cash_out', 15, 4)->default(0);
            $table->string('source_row', 32)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('stock_dividends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('symbol', 32);
            $table->string('bo_account')->nullable();
            $table->date('record_date')->nullable();
            $table->decimal('percent', 8, 2)->nullable();
            $table->unsignedInteger('qty')->nullable();
            $table->decimal('gross', 15, 4)->default(0);
            $table->decimal('tax', 15, 4)->default(0);
            $table->decimal('net', 15, 4)->default(0);
            $table->date('received_date')->nullable();
            $table->string('status', 16)->default('Receivable'); // Received / Receivable
            $table->string('source_row', 32)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_dividends');
        Schema::dropIfExists('stock_cash');
        Schema::dropIfExists('stock_trades');
        Schema::dropIfExists('stock_symbols');
    }
};
