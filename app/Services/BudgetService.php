<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-category monthly budgets (categories.monthly_budget) vs. this month's
 * actual EXPENSE spend. Used by the dashboard widget, the category list, the
 * top-bar alert bell and the "you're over budget" flash after saving.
 */
class BudgetService
{
    /** A category alerts once spend reaches this share of its budget. */
    public const WARN_AT = 80;

    private static ?Collection $memo = null;

    /**
     * Every budgeted category for the current month, most-used first:
     * [id, name, budget, spent, remaining, percent].
     */
    public static function currentMonth(): Collection
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            $spent = ExpenseCalculation::query()
                ->where('types', 'EXPENSE')
                ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->groupBy('category_id')
                ->selectRaw('category_id, SUM(amount) as total')
                ->pluck('total', 'category_id');

            $rows = Category::query()
                ->where('monthly_budget', '>', 0)
                ->get(['id', 'name', 'monthly_budget'])
                ->map(function ($category) use ($spent) {
                    $budget = (float) $category->monthly_budget;
                    $used = (float) ($spent[$category->id] ?? 0);

                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'budget' => $budget,
                        'spent' => $used,
                        'remaining' => $budget - $used,
                        'percent' => (int) round($used / $budget * 100),
                    ];
                })
                ->sortByDesc('percent')
                ->values();
        } catch (\Illuminate\Database\QueryException $e) {
            // Column not migrated yet (or DB down): show no budgets rather than break every page.
            $rows = collect();
        }

        return self::$memo = $rows;
    }

    /** Categories at or over WARN_AT percent of budget this month. */
    public static function alerts(): Collection
    {
        return self::currentMonth()->filter(fn($row) => $row['percent'] >= self::WARN_AT)->values();
    }

    /** Flash a warning for any of the just-saved categories that are now near/over budget. */
    public static function flashWarnings(array $categoryIds): void
    {
        $ids = array_filter(array_map('intval', $categoryIds));
        if (!$ids) {
            return;
        }

        self::$memo = null;
        $hits = self::alerts()->whereIn('id', $ids)->values();
        if ($hits->isNotEmpty()) {
            session()->flash('budget_warnings', $hits->all());
        }
    }
}
