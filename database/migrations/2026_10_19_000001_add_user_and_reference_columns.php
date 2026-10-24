<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who created each row, and which rows belong together. Every reference is
 * nullable and ON DELETE SET NULL: deleting a row only clears links to it,
 * it never deletes the related data. Old rows are filled in by
 * `php artisan data:link-references` (matches on name/date/amount/time).
 */
return new class extends Migration
{
    private array $userTables = ['categories', 'expense_calculations', 'hand_cashes', 'projected_expenses', 'peti_cashes', 'recurring_transactions'];

    public function up()
    {
        foreach ($this->userTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        Schema::table('hand_cashes', function (Blueprint $t) {
            // The expense/income entry this cash movement was entered with (Data Entry modal).
            $t->foreignId('expense_calculation_id')->nullable()->after('user_id')->constrained('expense_calculations')->nullOnDelete();
            // The other half of an account-to-account transfer.
            $t->foreignId('transfer_pair_id')->nullable()->after('expense_calculation_id')->constrained('hand_cashes')->nullOnDelete();
            $t->foreignId('recurring_transaction_id')->nullable()->after('transfer_pair_id')->constrained('recurring_transactions')->nullOnDelete();
        });

        Schema::table('expense_calculations', function (Blueprint $t) {
            $t->foreignId('recurring_transaction_id')->nullable()->after('category_id')->constrained('recurring_transactions')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('expense_calculations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('recurring_transaction_id');
        });

        Schema::table('hand_cashes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('recurring_transaction_id');
            $t->dropConstrainedForeignId('transfer_pair_id');
            $t->dropConstrainedForeignId('expense_calculation_id');
        });

        foreach (array_reverse($this->userTables) as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('user_id');
            });
        }
    }
};
