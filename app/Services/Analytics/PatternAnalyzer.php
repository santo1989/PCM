<?php

namespace App\Services\Analytics;

use App\Models\ExpenseCalculation;
use Carbon\Carbon;

/**
 * Day-of-week / needs-wants-savings / seasonal groupings — pure Laravel
 * Collection math over ExpenseCalculation (grouping + averaging only, per
 * the "built-in statistics only" decision, no new stats/ML beyond that).
 */
class PatternAnalyzer
{
    public static function dayOfWeekSpending(Carbon $start, Carbon $end): array
    {
        $rows = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get(['date', 'amount']);

        $order = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $totals = array_fill_keys($order, 0.0);
        $occurrences = array_fill_keys($order, 0);

        foreach ($rows as $row) {
            $day = Carbon::parse($row->date)->format('D');
            $totals[$day] += (float) $row->amount;
        }

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $occurrences[$cursor->format('D')]++;
            $cursor->addDay();
        }

        return collect($order)->map(fn($day) => [
            'day' => $day,
            'total' => $totals[$day],
            'average' => $occurrences[$day] > 0 ? $totals[$day] / $occurrences[$day] : 0,
        ])->values()->all();
    }

    /** Needs/Wants/Savings percent-of-total per month, trailing $months. */
    public static function ruleTrend(int $months = 6): array
    {
        $labels = [];
        $needs = [];
        $wants = [];
        $savings = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $cursor = now()->subMonths($i);
            $rows = ExpenseCalculation::where('types', 'EXPENSE')
                ->whereYear('date', $cursor->year)->whereMonth('date', $cursor->month)
                ->groupBy('rules')
                ->selectRaw('rules, SUM(amount) as total')
                ->pluck('total', 'rules');

            $total = (float) $rows->sum();

            $labels[] = $cursor->format('M Y');
            $needs[] = $total > 0 ? ((float) ($rows['NEEDS'] ?? 0) / $total) * 100 : 0;
            $wants[] = $total > 0 ? ((float) ($rows['WANTS'] ?? 0) / $total) * 100 : 0;
            $savings[] = $total > 0 ? ((float) ($rows['SAVINGS'] ?? 0) / $total) * 100 : 0;
        }

        return compact('labels', 'needs', 'wants', 'savings');
    }

    /** This calendar month vs. the same month in prior years — only emitted with >= 2 years of history. */
    public static function seasonalNotes(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $priorTotals = [];
        for ($i = 1; $i <= 3; $i++) {
            $total = (float) ExpenseCalculation::where('types', 'EXPENSE')
                ->whereYear('date', $currentYear - $i)->whereMonth('date', $currentMonth)
                ->sum('amount');

            if ($total > 0) {
                $priorTotals[] = $total;
            }
        }

        if (count($priorTotals) < 2) {
            return [];
        }

        $currentTotal = (float) ExpenseCalculation::where('types', 'EXPENSE')
            ->whereYear('date', $currentYear)->whereMonth('date', $currentMonth)
            ->sum('amount');

        $priorAverage = array_sum($priorTotals) / count($priorTotals);
        if ($priorAverage <= 0) {
            return [];
        }

        $change = (($currentTotal - $priorAverage) / $priorAverage) * 100;
        if (abs($change) < 15) {
            return [];
        }

        return [[
            'type' => $change > 0 ? 'warning' : 'success',
            'message' => sprintf(
                '%s spending is typically %s year-over-year — this %s is %s%.0f%% versus the average of the last %d years.',
                now()->format('F'),
                $change > 0 ? 'higher' : 'lower',
                now()->format('F'),
                $change > 0 ? '+' : '',
                $change,
                count($priorTotals)
            ),
        ]];
    }
}
