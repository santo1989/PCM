<?php

namespace App\Services;

use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use App\Models\RecurringTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringTransactionService
{
    /**
     * Create every entry that has come due up to $today (catching up missed
     * months), advance each rule's next_run_on, and return how many
     * occurrences were created (a transfer counts once, though it writes two
     * HandCash rows). Safe to call repeatedly: each rule row is locked and only
     * dates after its last run are generated.
     */
    public function runDue(?Carbon $today = null): int
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $created = 0;
        $dates = [];

        $ids = RecurringTransaction::where('is_active', true)
            ->whereNotNull('next_run_on')
            ->whereDate('next_run_on', '<=', $today)
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $today, &$created, &$dates) {
                $rule = RecurringTransaction::with('category')->lockForUpdate()->find($id);
                if (!$rule || !$rule->is_active || !$rule->next_run_on || $rule->next_run_on->gt($today)) {
                    return; // another request already handled it
                }

                $incomeRows = [];
                $cashRows = [];
                $occurrences = 0;
                $run = $rule->next_run_on->copy();
                while ($run->lte($today) && (!$rule->end_date || $run->lte($rule->end_date))) {
                    $base = [
                        'user_id' => $rule->user_id,
                        'recurring_transaction_id' => $rule->id,
                        'name' => $rule->name,
                        'date' => $run->toDateString(),
                        'amount' => $rule->amount,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($rule->isTransfer()) {
                        // Same pair of rows the Transfer page (handCashes_transfer) writes.
                        $cashRows[] = $base + ['rules' => $rule->from_account, 'types' => 'WIDROWS'];
                        $cashRows[] = $base + ['rules' => $rule->to_account, 'types' => 'SAVE'];
                    } else {
                        $incomeRows[] = $base + [
                            'category_id' => $rule->category_id,
                            'types' => $rule->category ? strtoupper($rule->category->types) : null,
                            'rules' => $rule->category ? strtoupper($rule->category->rules) : null,
                        ];
                    }

                    $occurrences++;
                    $dates[] = $base['date'];
                    $rule->last_run_on = $run->copy();
                    $run = RecurringTransaction::occurrenceAfter($run, $rule->day_of_month);
                }

                if ($incomeRows) {
                    ExpenseCalculation::insert($incomeRows);
                }
                // Transfers are created as linked pairs so editing one half updates the other.
                foreach (array_chunk($cashRows, 2) as [$from, $to]) {
                    $fromId = HandCash::insertGetId($from);
                    $toId = HandCash::insertGetId($to + ['transfer_pair_id' => $fromId]);
                    HandCash::whereKey($fromId)->update(['transfer_pair_id' => $toId]);
                }
                $created += $occurrences;

                $rule->next_run_on = $run;
                if ($rule->end_date && $run->gt($rule->end_date)) {
                    $rule->is_active = false; // schedule finished
                }
                $rule->save();
            });
        }

        if ($dates) {
            ExpenseCalculation::flushReportCaches($dates);
        }

        return $created;
    }
}
