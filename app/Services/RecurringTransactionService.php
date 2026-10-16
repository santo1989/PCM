<?php

namespace App\Services;

use App\Models\ExpenseCalculation;
use App\Models\RecurringTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringTransactionService
{
    /**
     * Create every entry that has come due up to $today (catching up missed
     * months), advance each rule's next_run_on, and return how many entries
     * were created. Safe to call repeatedly: each rule row is locked and only
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

                $rows = [];
                $run = $rule->next_run_on->copy();
                while ($run->lte($today) && (!$rule->end_date || $run->lte($rule->end_date))) {
                    $rows[] = [
                        'category_id' => $rule->category_id,
                        'name' => $rule->name,
                        'date' => $run->toDateString(),
                        'amount' => $rule->amount,
                        'types' => $rule->category ? strtoupper($rule->category->types) : null,
                        'rules' => $rule->category ? strtoupper($rule->category->rules) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $rule->last_run_on = $run->copy();
                    $run = RecurringTransaction::occurrenceAfter($run, $rule->day_of_month);
                }

                if ($rows) {
                    ExpenseCalculation::insert($rows);
                    $created += count($rows);
                    array_push($dates, ...array_column($rows, 'date'));
                }

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
