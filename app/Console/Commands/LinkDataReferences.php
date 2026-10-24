<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills user_id and the new reference columns on rows created before they
 * existed. Only ever fills NULLs (re-runnable, never deletes or overwrites),
 * and writes through the query builder so old rows keep their updated_at.
 */
class LinkDataReferences extends Command
{
    protected $signature = 'data:link-references
        {--user= : user id to stamp on old rows (default: the first user)}
        {--dry-run : report what would be linked without writing}';

    protected $description = 'Backfill user_id and expense/transfer/recurring links on existing rows';

    private const SAME_REQUEST_SECONDS = 5;
    private const LOOSE_SECONDS = 2 * 86400;

    public function handle()
    {
        $dry = (bool) $this->option('dry-run');
        $userId = (int) ($this->option('user') ?: User::orderBy('id')->value('id'));
        if (!$userId || !User::whereKey($userId)->exists()) {
            $this->error('No user to assign.');
            return self::FAILURE;
        }

        $report = [];
        DB::transaction(function () use ($dry, $userId, &$report) {
            foreach (['categories', 'expense_calculations', 'hand_cashes', 'projected_expenses', 'peti_cashes', 'recurring_transactions'] as $table) {
                $q = DB::table($table)->whereNull('user_id');
                $report["user_id on {$table}"] = $dry ? $q->count() : $q->update(['user_id' => $userId]);
            }

            $cash = DB::table('hand_cashes')
                ->whereNull('expense_calculation_id')->whereNull('transfer_pair_id')
                ->get(['id', 'name', 'date', 'amount', 'types', 'created_at'])->keyBy('id');
            $expenses = DB::table('expense_calculations')
                ->whereNotIn('id', DB::table('hand_cashes')->whereNotNull('expense_calculation_id')->select('expense_calculation_id'))
                ->orderBy('id')
                ->get(['id', 'name', 'date', 'amount', 'created_at']);

            $links = $this->matchExpenses($expenses, $cash, self::SAME_REQUEST_SECONDS);
            $pairs = $this->matchTransfers($cash);
            $links += $this->matchExpenses($expenses->whereNotIn('id', array_values($links)), $cash, self::LOOSE_SECONDS);

            $report['cash entry -> expense entry'] = count($links);
            $report['transfer pairs'] = count($pairs);
            if (!$dry) {
                foreach ($links as $cashId => $expenseId) {
                    DB::table('hand_cashes')->where('id', $cashId)->update(['expense_calculation_id' => $expenseId]);
                }
                foreach ($pairs as [$a, $b]) {
                    DB::table('hand_cashes')->where('id', $a)->update(['transfer_pair_id' => $b]);
                    DB::table('hand_cashes')->where('id', $b)->update(['transfer_pair_id' => $a]);
                }
            }

            $report['recurring-generated rows'] = $this->linkRecurring($dry);
        });

        $this->table(['What', $dry ? 'Would link' : 'Linked'], collect($report)->map(fn($n, $k) => [$k, $n])->values()->all());
        if ($dry) {
            $this->info('Dry run: nothing was written. Run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    private static function key($row): string
    {
        return $row->name . '|' . $row->date . '|' . round((float) $row->amount, 2);
    }

    /**
     * Pair each expense with the unclaimed cash entry of the same name/date/amount
     * created closest in time (within $maxSeconds). Claimed cash rows are removed
     * from $cash. Returns [cash id => expense id].
     */
    private function matchExpenses($expenses, $cash, int $maxSeconds): array
    {
        $byKey = $cash->groupBy(fn($c) => self::key($c));
        $links = [];
        foreach ($expenses as $e) {
            $best = null;
            $bestGap = $maxSeconds + 1;
            foreach ($byKey[self::key($e)] ?? [] as $c) {
                if (!$cash->has($c->id)) {
                    continue;
                }
                $gap = abs(strtotime($c->created_at) - strtotime($e->created_at));
                if ($gap < $bestGap) {
                    [$best, $bestGap] = [$c, $gap];
                }
            }
            if ($best) {
                $links[$best->id] = $e->id;
                $cash->forget($best->id);
            }
        }

        return $links;
    }

    /**
     * Transfers are saved as a WIDROWS + SAVE pair in one request: same
     * name/date/amount within a few seconds. Claimed rows are removed from $cash.
     */
    private function matchTransfers($cash): array
    {
        $pairs = [];
        foreach ($cash->groupBy(fn($c) => self::key($c)) as $group) {
            $out = $group->where('types', 'WIDROWS')->sortBy('id')->values();
            $in = $group->where('types', 'SAVE')->sortBy('id')->values();
            foreach ($out as $w) {
                foreach ($in as $i => $s) {
                    if (abs(strtotime($w->created_at) - strtotime($s->created_at)) <= self::SAME_REQUEST_SECONDS) {
                        $pairs[] = [$w->id, $s->id];
                        $cash->forget([$w->id, $s->id]);
                        $in->forget($i);
                        break;
                    }
                }
            }
        }

        return $pairs;
    }

    /** Rows a recurring item generated: its name, a date in its run window, created after the item. */
    private function linkRecurring(bool $dry): int
    {
        $total = 0;
        foreach (RecurringTransaction::whereNotNull('last_run_on')->get() as $rule) {
            $table = $rule->isTransfer() ? 'hand_cashes' : 'expense_calculations';
            $q = DB::table($table)
                ->whereNull('recurring_transaction_id')
                ->where('name', $rule->name)
                ->whereBetween('date', [$rule->start_date->toDateString(), $rule->last_run_on->toDateString()])
                ->where('created_at', '>=', Carbon::parse($rule->created_at)->subMinute());
            $total += $dry ? $q->count() : $q->update(['recurring_transaction_id' => $rule->id]);
        }

        return $total;
    }
}
