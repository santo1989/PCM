<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToExpenseCalculations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('expense_calculations', function (Blueprint $table) {
            $table->index('date');
            $table->index('category_id');
            $table->index('types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('expense_calculations', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['types']);
        });
    }
}
