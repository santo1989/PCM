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

        return view('backend.reports.income_expense_sheet', compact('year', 'years', 'periods', 'groups', 'rules'));
    }
}
