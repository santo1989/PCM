<?php

namespace App\Console\Commands;

use App\Services\Analytics\LedgerSeries;
use App\Services\Analytics\PeriodContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only: proves the analytics data layer ties back to the raw ledgers.
 * Run instead of a DB phpunit test (the test suite can hit the real database).
 */
class ReconcileAnalytics extends Command
{
    protected $signature = 'analytics:reconcile';

    protected $description = 'Check analytics series against direct SQL on the ledgers (read-only)';

    private int $failed = 0;

    public function handle()
    {
        $cutoff = LedgerSeries::cutoff();
        $amt = LedgerSeries::AMOUNT;
        $monthly = LedgerSeries::monthly();

        // 1. Monthly income/expense == direct SQL; buckets add up.
        $sql = DB::table('expense_calculations')->where('date', '<=', $cutoff)
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') ym, types, SUM($amt) total, SUM(amount) plain")
            ->groupBy('ym', 'types')->get();
        $bad = [];
        $plainDiff = [];
        foreach ($sql as $r) {
            $key = $r->types === 'INCOME' ? 'income' : 'expense';
            if (abs(($monthly[$r->ym][$key] ?? 0) - $r->total) > 0.005) {
                $bad[] = "$r->ym $key";
            }
            if (abs($r->total - $r->plain) > 0.005) {
                $plainDiff[] = sprintf('%s %s: real %s, plain SUM %s', $r->ym, $r->types, number_format($r->total), number_format($r->plain));
            }
        }
        $this->check(!$bad, count($monthly) . ' months: income/expense match SQL' . ($bad ? ' — off: ' . implode(', ', $bad) : ''));
        $split = array_filter($monthly, fn($m) => abs($m['needs'] + $m['wants'] + $m['savings'] + $m['other'] - $m['expense']) > 0.005
            || abs($m['core'] + $m['one_off'] - $m['needs'] - $m['wants']) > 0.005);
        $this->check(!$split, 'needs+wants+savings+other = expense, core+one-off = needs+wants');

        // 2. Category series add up to the monthly totals.
        $cats = LedgerSeries::categoryMonthly()['categories'];
        $off = [];
        foreach ($monthly as $ym => $m) {
            $byType = ['INCOME' => 0, 'EXPENSE' => 0];
            foreach ($cats as $c) {
                $byType[$c['type']] += $c['months'][$ym];
            }
            if (abs($byType['INCOME'] - $m['income']) > 0.005 || abs($byType['EXPENSE'] - $m['expense']) > 0.005) {
                $off[] = $ym;
            }
        }
        $this->check(!$off, 'category series sum to monthly totals' . ($off ? ' — off: ' . implode(', ', $off) : ''));

        // 3. Account balances: opening + in - out = closing each month, and closing == SQL.
        $accounts = LedgerSeries::accounts();
        $sqlBal = DB::table('hand_cashes')->where('date', '<=', $cutoff)
            ->selectRaw("rules, SUM(CASE WHEN types = 'SAVE' THEN $amt ELSE -$amt END) bal")->groupBy('rules')->pluck('bal', 'rules');
        $broken = [];
        foreach ($accounts as $name => $a) {
            foreach ($a['months'] as $ym => $m) {
                if (abs($m['opening'] + $m['in'] + $m['transfer_in'] - $m['out'] - $m['transfer_out'] - $m['closing']) > 0.005) {
                    $broken[] = "$name $ym";
                }
            }
            if (abs($a['closing'] - ($sqlBal[$name] ?? 0)) > 0.005) {
                $broken[] = "$name closing";
            }
        }
        $this->check(!$broken, count($accounts) . ' accounts: monthly roll-forward holds and closing balance matches SQL' . ($broken ? ' — off: ' . implode(', ', $broken) : ''));
        $this->line(sprintf('     hand cash (%s) closing: %s', LedgerSeries::CASH_ACCOUNT, number_format($accounts[LedgerSeries::CASH_ACCOUNT]['closing'] ?? 0, 2)));

        // 4. Transfers: each pair is reciprocal, same amount, opposite direction, and not in the income/expense ledger.
        $pairs = DB::table('hand_cashes as a')->leftJoin('hand_cashes as b', 'b.id', '=', 'a.transfer_pair_id')->whereNotNull('a.transfer_pair_id')
            ->selectRaw("a.id, b.transfer_pair_id back, a.types, b.types btypes, REPLACE(a.amount, ',', '') + 0 amt, REPLACE(b.amount, ',', '') + 0 bamt, a.expense_calculation_id ledger")->get();
        $badPairs = $pairs->filter(fn($p) => $p->back != $p->id || $p->types === $p->btypes || abs($p->amt - $p->bamt) > 0.005 || $p->ledger)->pluck('id');
        $net = DB::table('hand_cashes')->whereNotNull('transfer_pair_id')->where('date', '<=', $cutoff)
            ->selectRaw("SUM(CASE WHEN types = 'SAVE' THEN $amt ELSE -$amt END) n")->value('n');
        $this->check($badPairs->isEmpty() && abs((float) $net) < 0.005, $pairs->count() . " transfer rows net to zero ($net) and none touch income/expense" . ($badPairs->isEmpty() ? '' : ' — bad ids: ' . $badPairs->implode(', ')));

        // 4b. Report-period totals (PeriodContext, shown on report pages) == direct SQL, incl. a range not aligned to months.
        $off = [];
        foreach ([['2024-12-01', '2024-12-31'], ['2025-01-01', '2025-12-31'], ['2025-03-10', '2025-06-17']] as [$a, $b]) {
            $t = PeriodContext::totals(Carbon::parse($a), Carbon::parse($b));
            foreach (['INCOME' => 'income', 'EXPENSE' => 'expense'] as $type => $key) {
                $sum = (float) DB::table('expense_calculations')->where('types', $type)->whereBetween('date', [$a, $b])->sum(DB::raw($amt));
                if (abs($t[$key] - $sum) > 0.005) {
                    $off[] = "$a..$b $key";
                }
            }
        }
        $this->check(!$off, 'report-period totals match SQL' . ($off ? ' — off: ' . implode(', ', $off) : ''));

        // 5. Ledger <-> hand cash links (information: shows where the two books disagree).
        $mismatch = DB::table('hand_cashes as h')->join('expense_calculations as e', 'e.id', '=', 'h.expense_calculation_id')
            ->whereRaw("REPLACE(h.amount, ',', '') + 0 <> REPLACE(e.amount, ',', '') + 0")->pluck('h.id');
        $unlinked = DB::table('expense_calculations as e')->where('e.date', '<=', $cutoff)
            ->whereNotExists(fn($q) => $q->from('hand_cashes')->whereColumn('expense_calculation_id', 'e.id'))->count();
        $this->line('INFO linked hand-cash rows whose amount differs from the ledger: ' . ($mismatch->isEmpty() ? 'none' : $mismatch->implode(', ')));
        $this->line("INFO ledger rows with no hand-cash row: $unlinked (entered before linking existed, or never paid from an account)");

        // 6. Where the existing reports' plain SUM(amount) disagrees with the real amount.
        $this->line('INFO months where a plain SUM(amount) undercounts (comma amounts): ' . ($plainDiff ? '' : 'none'));
        foreach ($plainDiff as $d) {
            $this->line("     $d");
        }
        $this->line('INFO one-off ledger rows: ' . count(LedgerSeries::oneOffIds()) . '; rows after today left out: '
            . DB::table('expense_calculations')->where('date', '>', $cutoff)->count() . ' ledger, '
            . DB::table('hand_cashes')->where('date', '>', $cutoff)->count() . ' hand cash');

        $this->line($this->failed ? "\n{$this->failed} check(s) FAILED" : "\nALL CHECKS PASS");
        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function check(bool $ok, string $label): void
    {
        $this->failed += $ok ? 0 : 1;
        $this->line(($ok ? 'PASS ' : 'FAIL ') . $label);
    }
}
