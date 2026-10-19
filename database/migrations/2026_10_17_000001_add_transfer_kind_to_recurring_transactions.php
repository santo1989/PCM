<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            // income: one ExpenseCalculation row (INCOME category).
            // transfer: two HandCash rows, like the Transfer page (WIDROWS from_account, SAVE to_account).
            $table->string('kind', 20)->default('income')->after('name');
            $table->string('from_account', 50)->nullable()->after('category_id');
            $table->string('to_account', 50)->nullable()->after('from_account');
            $table->unsignedBigInteger('category_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropColumn(['kind', 'from_account', 'to_account']);
        });
    }
};
