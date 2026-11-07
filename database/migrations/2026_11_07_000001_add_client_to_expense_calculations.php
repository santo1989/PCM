<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Client / project of a freelance or business (AUGUSTA IT) ledger row, for the Freelance & Business page. */
return new class extends Migration
{
    public function up()
    {
        Schema::table('expense_calculations', function (Blueprint $table) {
            $table->string('client', 120)->nullable()->after('name');
            $table->index('client');
        });
    }

    public function down()
    {
        Schema::table('expense_calculations', function (Blueprint $table) {
            $table->dropIndex(['client']);
            $table->dropColumn('client');
        });
    }
};
