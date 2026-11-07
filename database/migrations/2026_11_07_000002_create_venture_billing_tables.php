<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freelance & Business billing: invoices / bills / cash memos, recurring bill reminders, and the
 * ledger income rows that pay an invoice (expense_calculations.invoice_id → amount still due).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('venture_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('venture', 32); // key of config finance.ventures
            $table->string('type', 16)->default('Invoice'); // Invoice / Bill / Cash memo
            $table->string('number', 32)->unique();
            $table->string('client', 120); // same tag as expense_calculations.client
            $table->text('bill_to')->nullable(); // full name, email, address as printed
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->json('items'); // [{description, period, qty, rate}]; a negative rate is a discount line
            $table->decimal('total', 15, 2);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->timestamps();

            $table->index(['venture', 'client']);
        });

        Schema::create('venture_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('venture', 32);
            $table->string('client', 120);
            $table->text('bill_to')->nullable();
            $table->string('title'); // e.g. "Domain, hosting & maintenance"
            $table->json('items');
            $table->unsignedTinyInteger('every_months')->default(12);
            $table->date('next_due'); // date the bill should go out
            $table->unsignedSmallInteger('remind_days')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('expense_calculations', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->after('client');
            $table->index('invoice_id');
        });
    }

    public function down()
    {
        Schema::table('expense_calculations', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
            $table->dropColumn('invoice_id');
        });
        Schema::dropIfExists('venture_schedules');
        Schema::dropIfExists('venture_invoices');
    }
};
