<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ExpenseCalculation;

/**
 * Freelance & Business (AUGUSTA IT): a venture is its income and cost ledger categories
 * (config finance.ventures). Rows are normal ExpenseCalculation entries, so every report counts them.
 */
class VentureLedger
{
    /** @return array{0: ?Category, 1: ?Category} income and cost categories (null until first used) */
    public static function categories(string $key): array
    {
        $v = config("finance.ventures.$key");
        $byName = Category::whereIn('name', [$v['income'], $v['cost']])->get()->keyBy('name');

        return [$byName[$v['income']] ?? null, $byName[$v['cost']] ?? null];
    }

    /** Ledger rows of one venture, newest first, with the account each was paid into / from. */
    public static function rows(string $key): array
    {
        [$income, $cost] = self::categories($key);
        $ids = array_filter([optional($income)->id, optional($cost)->id]);
        if (!$ids) return [];

        return ExpenseCalculation::with('handCashes:id,expense_calculation_id,rules,types')->whereIn('category_id', $ids)
            ->orderByDesc('date')->orderByDesc('id')->get()
            ->map(fn($e) => [
                'id' => $e->id, 'date' => (string) $e->date, 'name' => $e->name, 'client' => $e->client,
                'kind' => (int) $e->category_id === (int) optional($income)->id ? 'income' : 'cost',
                'amount' => (float) str_replace(',', '', $e->amount),
                'account' => optional($e->handCashes->first())->rules,
            ])->all();
    }

    /**
     * Save one venture income/cost as a normal ledger row plus its account entry (as Income & Expenses does).
     * $invoiceId marks the income as a payment of that invoice.
     */
    public static function record(string $key, string $kind, string $date, $amount, string $name, ?string $client, string $account, ?int $invoiceId = null): ExpenseCalculation
    {
        $v = config("finance.ventures.$key");
        $income = $kind === 'income';

        $expense = \Illuminate\Support\Facades\DB::transaction(function () use ($v, $kind, $income, $date, $amount, $name, $client, $account, $invoiceId) {
            // First entry of a new venture creates its categories, set up like the freelancing ones.
            $category = Category::firstOrCreate(['name' => $v[$kind]], ['types' => $income ? 'INCOME' : 'EXPENSE', 'rules' => $income ? '0' : 'NEEDS']);
            $expense = ExpenseCalculation::create([
                'category_id' => $category->id, 'types' => $category->types, 'rules' => $category->rules,
                'name' => strtoupper($name), 'client' => $client ? strtoupper(trim($client)) : null, 'invoice_id' => $invoiceId,
                'date' => $date, 'amount' => $amount,
            ]);
            \App\Models\HandCash::create(['expense_calculation_id' => $expense->id, 'name' => $expense->name, 'types' => $income ? 'SAVE' : 'WIDROWS',
                'rules' => $account, 'date' => $date, 'amount' => $amount]);

            return $expense;
        });
        ExpenseCalculation::flushReportCaches([$date]);
        BudgetService::flashWarnings([$expense->category_id]);

        return $expense;
    }

    /**
     * Pure: figures for the AI and rule-based recommendations for one venture. Client names are
     * replaced by "Client 1…" before anything leaves the app; the aliases map them back on screen.
     * $due: [client => [due, overdue]], $soon: [[client, date, amount]], $year: current year.
     */
    public static function advice(string $label, array $summary, array $due, array $soon, string $year): array
    {
        $aliases = [];
        $alias = function (string $client) use (&$aliases) {
            $k = array_search($client, $aliases, true);
            if ($k === false) $aliases[$k = 'Client ' . (count($aliases) + 1)] = $client;

            return $k;
        };
        $t = $summary['total'];
        $pct = fn($v) => $v === null ? null : round($v * 100, 1);
        $clients = [];
        foreach ($summary['clients'] as $name => $c) {
            $clients[] = ['client' => $name === 'Not tagged' ? 'untagged entries' : $alias($name), 'income' => round($c['income']), 'cost' => round($c['cost']),
                'profit' => round($c['profit']), 'margin_percent' => $pct($c['margin']), 'share_of_income_percent' => $t['income'] > 0 ? $pct($c['income'] / $t['income']) : 0, 'last_entry' => $c['last']];
        }
        $years = array_map(fn($y) => ['income' => round($y['income']), 'cost' => round($y['cost']), 'profit' => round($y['profit'])], $summary['years']);
        $m = ['venture' => $label, 'income' => round($t['income']), 'cost' => round($t['cost']), 'profit' => round($t['profit']), 'margin_percent' => $pct($t['margin']),
            'by_year' => $years, 'clients' => $clients,
            'due_from_clients' => round(array_sum(array_column($due, 'due'))), 'overdue' => round(array_sum(array_column($due, 'overdue'))),
            'bills_to_send_soon' => array_map(fn($s) => ['client' => $alias($s[0]), 'date' => $s[1], 'amount' => round($s[2])], $soon)];

        $f = fn($v) => number_format($v);
        $ins = [$m['income'] > 0
            ? sprintf('%s earned %s with %s of costs: profit %s (%s%% margin).', $label, $f($m['income']), $f($m['cost']), $f($m['profit']), $m['margin_percent'])
            : sprintf('%s has spent %s so far and has no income yet.', $label, $f($m['cost']))];
        $act = [];
        $paying = array_values(array_filter($clients, fn($c) => $c['income'] > 0));
        if ($paying) {
            $ins[] = sprintf('%s is the biggest client: %s (%s%% of income).', $paying[0]['client'], $f($paying[0]['income']), $paying[0]['share_of_income_percent']);
            if ($paying[0]['share_of_income_percent'] >= 50 && count($paying) > 1) $act[] = sprintf('%s brings %s%% of income: win one more client so a single loss does not hurt as much.', $paying[0]['client'], $paying[0]['share_of_income_percent']);
            $low = array_reduce($paying, fn($a, $c) => $a === null || $c['margin_percent'] < $a['margin_percent'] ? $c : $a);
            if ($low['margin_percent'] !== null && $low['margin_percent'] < 60) $ins[] = sprintf('%s has the thinnest margin at %s%%.', $low['client'], $low['margin_percent']);
        }
        if (isset($years[$year])) {
            $ins[] = sprintf('This year so far: income %s, profit %s.', $f($years[$year]['income']), $f($years[$year]['profit']));
        }
        if ($m['due_from_clients'] > 0) {
            $ins[] = sprintf('Clients owe %s%s.', $f($m['due_from_clients']), $m['overdue'] > 0 ? sprintf(', of which %s is overdue', $f($m['overdue'])) : '');
            $act[] = $m['overdue'] > 0 ? sprintf('Chase the %s overdue before sending new bills.', $f($m['overdue'])) : sprintf('Send a polite reminder for the %s due.', $f($m['due_from_clients']));
        }
        foreach (array_slice($m['bills_to_send_soon'], 0, 2) as $s) $act[] = sprintf('Send %s the %s bill by %s.', $s['client'], $f($s['amount']), $s['date']);
        if ($m['income'] == 0 && $m['cost'] > 0) $act[] = sprintf('Plan the first paying job so income starts covering the %s already spent.', $f($m['cost']));
        if (!$act) $act[] = 'Tag every entry with its client so profit per client stays accurate.';

        return ['metrics' => $m, 'template' => ['insights' => array_slice($ins, 0, 5), 'actions' => array_slice($act, 0, 3)], 'aliases' => $aliases];
    }

    /** Pure: totals, per client and per year from rows of {date, kind, amount, client}. */
    public static function summarize(array $rows): array
    {
        $blank = ['income' => 0.0, 'cost' => 0.0];
        $total = $blank;
        $clients = [];
        $years = [];
        foreach ($rows as $r) {
            $client = $r['client'] ?: 'Not tagged';
            $year = substr($r['date'], 0, 4);
            $total[$r['kind']] += $r['amount'];
            $clients[$client] ??= $blank + ['first' => $r['date'], 'last' => $r['date'], 'entries' => 0];
            $clients[$client][$r['kind']] += $r['amount'];
            $clients[$client]['first'] = min($clients[$client]['first'], $r['date']);
            $clients[$client]['last'] = max($clients[$client]['last'], $r['date']);
            $clients[$client]['entries']++;
            $years[$year] ??= $blank;
            $years[$year][$r['kind']] += $r['amount'];
        }
        $withProfit = fn(array $x) => $x + ['profit' => $x['income'] - $x['cost'], 'margin' => $x['income'] > 0 ? ($x['income'] - $x['cost']) / $x['income'] : null];
        $clients = array_map($withProfit, $clients);
        uasort($clients, fn($a, $b) => $b['income'] <=> $a['income'] ?: $b['cost'] <=> $a['cost']);
        krsort($years);

        return ['total' => $withProfit($total), 'clients' => $clients, 'years' => array_map($withProfit, $years)];
    }
}
