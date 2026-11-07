<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Income | expense | hand-cash savings | difference sheet, laid out like the
 * owner's spreadsheet, for all years (default) or one year. Read-only.
 */
class IncomeExpenseSheetController extends Controller
{
    private const AMOUNT = "CAST(REPLACE(amount, ',', '') AS DECIMAL(15,2))";

    // Income/expense pairs from the owner's sheet; every other category goes in the first block.
    private const PAIRS = [
        [['INTERNET BILL PAYMENT REFUND BY MOTHER'], ['PHONE AND INTERNET BILL']],
        [['FREELANCING INCOME'], ['FREELANCING COST']],
        [['AUGUSTA IT INCOME'], ['AUGUSTA IT COST']],
        [['STOCK INCOME FROM DSE'], ['STOCK COMM. FROM DSE', 'STOCK UNREALIZE LOSS FROM DSE', 'STOCK LOSS FROM DSE']],
        [['BANK INTEREST'], ['ZAKAT 2.5%']],
    ];

    public function index(Request $request)
    {
        $years = DB::table('expense_calculations')->selectRaw('DISTINCT YEAR(date) y')->whereNotNull('date')
            ->union(DB::table('hand_cashes')->selectRaw('DISTINCT LEFT(date, 4) y')->whereNotNull('date'))
            ->pluck('y')->map(fn($y) => (int) $y)->unique()->sortDesc()->values();

        // All years side by side by default; a single year breaks down by month instead.
        $year = $years->contains((int) $request->get('year')) ? (int) $request->get('year') : 'all';
        $all = $year === 'all';
        $periods = $all
            ? $years->sort()->mapWithKeys(fn($y) => [$y => (string) $y])->all()
            : array_combine(range(1, 12), array_map(fn($m) => date('M', mktime(0, 0, 0, $m, 1)), range(1, 12)));
        $keys = array_keys($periods);
        $filled = fn(array $v) => array_replace(array_fill_keys($keys, 0.0), $v);

        $byCategory = [];
        foreach (DB::table('expense_calculations')->selectRaw('category_id, ' . ($all ? 'YEAR(date)' : 'MONTH(date)') . ' p, SUM(' . self::AMOUNT . ') total')
            ->when(!$all, fn($q) => $q->whereYear('date', $year))->groupBy('category_id', 'p')->get() as $r) {
            $byCategory[$r->category_id][$r->p] = (float) $r->total;
        }
        $item = fn($c) => ['name' => $c->name, 'months' => $filled($byCategory[$c->id] ?? [])];

        $categories = Category::orderBy('id')->get(['id', 'name', 'types']);
        $byName = $categories->keyBy('name');
        $paired = collect(self::PAIRS)->flatten()->all();
        $main = $categories->reject(fn($c) => in_array($c->name, $paired, true));
        $groups = [[
            'income' => $main->where('types', 'INCOME')->map($item)->values()->all(),
            'expense' => $main->where('types', 'EXPENSE')->map($item)->values()->all(),
        ]];
        foreach (self::PAIRS as [$in, $out]) {
            $pick = fn($names) => collect($names)->map(fn($n) => $byName->get($n))->filter()->map($item)->values()->all();
            $groups[] = ['income' => $pick($in), 'expense' => $pick($out)];
        }
        foreach ($groups as $i => &$g) {
            $g['label'] = $i === 0 ? 'Main income / living costs' : implode(' / ', array_column($g['income'], 'name'));
            foreach ($keys as $p) {
                $g['months'][$p] = array_sum(array_column(array_column($g['income'], 'months'), $p))
                    - array_sum(array_column(array_column($g['expense'], 'months'), $p));
            }
        }
        unset($g);

        // Hand cash per account (rules): SAVE adds, WIDROWS takes out.
        $rules = [];
        foreach (DB::table('hand_cashes')->selectRaw('rules, ' . ($all ? 'LEFT(date, 4)' : 'MONTH(date)') . " p,
                SUM(CASE WHEN types = 'SAVE' THEN " . self::AMOUNT . " ELSE 0 END) inn,
                SUM(CASE WHEN types = 'WIDROWS' THEN " . self::AMOUNT . " ELSE 0 END) outt")
            ->when(!$all, fn($q) => $q->where('date', 'like', $year . '-%'))->groupBy('rules', 'p')->orderBy('rules')->get() as $r) {
            $rules[$r->rules]['name'] = $r->rules;
            $rules[$r->rules]['in'][(int) $r->p] = (float) $r->inn;
            $rules[$r->rules]['out'][(int) $r->p] = (float) $r->outt;
        }
        foreach ($rules as &$r) {
            $r['in'] = $filled($r['in']);
            $r['out'] = $filled($r['out']);
            foreach ($keys as $p) {
                $r['months'][$p] = $r['in'][$p] - $r['out'][$p];
            }
        }
        unset($r);
        $rules = array_values($rules);
        $advice = self::advice($groups, $rules, $periods, $all ? 'all years' : (string) $year);

        return view('backend.reports.income_expense_sheet', compact('year', 'years', 'periods', 'groups', 'rules', 'advice'));
    }

    /** Pure: figures for the AI and rule-based recommendations for the sheet. */
    public static function advice(array $groups, array $rules, array $periods, string $scope): array
    {
        $sum = fn(array $items) => array_sum(array_map(fn($i) => array_sum($i['months']), $items));
        $income = $expense = 0.0;
        $blocks = [];
        $categories = [];
        foreach ($groups as $g) {
            [$in, $out] = [$sum($g['income']), $sum($g['expense'])];
            [$income, $expense] = [$income + $in, $expense + $out];
            $blocks[] = ['block' => $g['label'], 'income' => round($in), 'expense' => round($out), 'difference' => round($in - $out)];
            foreach ($g['expense'] as $e) $categories[$e['name']] = array_sum($e['months']);
        }
        arsort($categories);
        $top = [];
        foreach (array_slice($categories, 0, 5, true) as $name => $v) {
            $top[] = ['category' => $name, 'amount' => round($v), 'share_percent' => $expense > 0 ? round($v / $expense * 100, 1) : 0];
        }
        $byPeriod = [];
        foreach ($periods as $k => $label) {
            $pi = array_sum(array_map(fn($g) => array_sum(array_map(fn($i) => $i['months'][$k], $g['income'])), $groups));
            $pe = array_sum(array_map(fn($g) => array_sum(array_map(fn($i) => $i['months'][$k], $g['expense'])), $groups));
            if ($pi || $pe) $byPeriod[$label] = ['income' => round($pi), 'expense' => round($pe), 'difference' => round($pi - $pe)];
        }
        $savings = round(array_sum(array_map(fn($r) => array_sum($r['months']), $rules)));
        $m = ['scope' => $scope, 'income' => round($income), 'expense' => round($expense), 'difference' => round($income - $expense),
            'kept_percent_of_income' => $income > 0 ? round(($income - $expense) / $income * 100, 1) : 0,
            'added_to_hand_cash_accounts' => $savings, 'blocks' => $blocks, 'top_expense_categories' => $top, 'by_period' => $byPeriod];

        $f = fn($v) => number_format($v);
        $ins = [sprintf('For %s: income %s, expenses %s, difference %s (%s%% of income kept).', $scope, $f($m['income']), $f($m['expense']), $f($m['difference']), $m['kept_percent_of_income'])];
        $act = [];
        if ($top) {
            $ins[] = sprintf('%s is the largest expense at %s (%s%% of all expenses).', $top[0]['category'], $f($top[0]['amount']), $top[0]['share_percent']);
            $act[] = sprintf('Set or tighten a monthly budget for %s, the biggest cost at %s%%.', $top[0]['category'], $top[0]['share_percent']);
        }
        foreach ($blocks as $b) {
            if ($b['difference'] < 0 && $b['income'] > 0) $ins[] = sprintf('%s spends more than it earns: %s.', $b['block'], $f($b['difference']));
        }
        if (count($byPeriod) > 1) {
            $labels = array_keys($byPeriod);
            $diffs = array_column($byPeriod, 'difference');
            $ins[] = sprintf('Best period: %s (%s); weakest: %s (%s).', $labels[array_search(max($diffs), $diffs)], $f(max($diffs)), $labels[array_search(min($diffs), $diffs)], $f(min($diffs)));
        }
        $ins[] = sprintf('Hand-cash accounts grew by %s in this period.', $f($savings));
        if ($m['difference'] > 0 && $savings < $m['difference']) {
            $act[] = sprintf('Only %s of the %s left over reached savings accounts: move the rest on salary day.', $f($savings), $f($m['difference']));
        }
        if ($m['difference'] < 0) $act[] = sprintf('Expenses exceed income by %s: cut the top categories first.', $f(abs($m['difference'])));
        if (!$act) $act[] = 'Keep tagging every entry to the right category so these blocks stay accurate.';

        return ['metrics' => $m, 'template' => ['insights' => array_slice($ins, 0, 5), 'actions' => array_slice($act, 0, 3)]];
    }
}
