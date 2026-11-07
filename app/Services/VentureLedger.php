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
