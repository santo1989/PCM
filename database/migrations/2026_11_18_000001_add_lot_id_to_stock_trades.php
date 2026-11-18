<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A sell may name the buy lot it sells (the workbook's "Sell Against Lot ID"); empty = oldest lot first. */
class AddLotIdToStockTrades extends Migration
{
    public function up()
    {
        Schema::table('stock_trades', function (Blueprint $table) {
            $table->unsignedBigInteger('lot_id')->nullable()->after('symbol');
        });
    }

    public function down()
    {
        Schema::table('stock_trades', function (Blueprint $table) {
            $table->dropColumn('lot_id');
        });
    }
}
