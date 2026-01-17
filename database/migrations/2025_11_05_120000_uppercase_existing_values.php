<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UppercaseExistingValues extends Migration
{
    /**
     * Run the migrations.
     * This will uppercase string columns that we normalize across the app.
     *
     * NOTE: This operation is irreversible by this migration (down() is a noop).
     */
    public function up()
    {
        // Categories
        DB::statement("UPDATE categories SET name = UPPER(name) WHERE name IS NOT NULL");
        DB::statement("UPDATE categories SET types = UPPER(types) WHERE types IS NOT NULL");
        DB::statement("UPDATE categories SET rules = UPPER(rules) WHERE rules IS NOT NULL");

        // Expense calculations
        DB::statement("UPDATE expense_calculations SET name = UPPER(name) WHERE name IS NOT NULL");
        DB::statement("UPDATE expense_calculations SET types = UPPER(types) WHERE types IS NOT NULL");
        DB::statement("UPDATE expense_calculations SET rules = UPPER(rules) WHERE rules IS NOT NULL");

        // Hand cashes
        DB::statement("UPDATE hand_cashes SET name = UPPER(name) WHERE name IS NOT NULL");
        DB::statement("UPDATE hand_cashes SET types = UPPER(types) WHERE types IS NOT NULL");
        DB::statement("UPDATE hand_cashes SET rules = UPPER(rules) WHERE rules IS NOT NULL");

        // Peti cashes
        DB::statement("UPDATE peti_cashes SET name = UPPER(name) WHERE name IS NOT NULL");
        DB::statement("UPDATE peti_cashes SET types = UPPER(types) WHERE types IS NOT NULL");
        DB::statement("UPDATE peti_cashes SET rules = UPPER(rules) WHERE rules IS NOT NULL");
    }

    /**
     * Reverse the migrations.
     * Not implemented because original casing cannot be reliably restored.
     */
    public function down()
    {
        // no-op
    }
}
