<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Models\ProjectedExpense;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BudgetProjectionExport implements FromView, ShouldAutoSize
{
    private ?string $startDate;
    private ?string $endDate;

    /**
     * Mirrors HandCashController::Budge_Projection()'s own date-range handling: narrows
     * the "Avg Expense" column when a range is given, defaults to an all-time average
     * (not "current month") when not — a single month isn't a meaningful average, and
     * this matches the same default already shown on the report page itself.
     */
    public function __construct(?string $startDate = null, ?string $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        $categories = Category::where('types', 'EXPENSE')->get();

        $allYearExpensesQuery = ExpenseCalculation::where('types', 'EXPENSE');
        if ($this->startDate && $this->endDate) {
            $allYearExpensesQuery->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $allYearExpenses = $allYearExpensesQuery
            ->groupBy('category_id')
            ->select('category_id', DB::raw('sum(amount) as totalExpense'), DB::raw('count(distinct MONTH(date)) as totalMonths'))
            ->get()
            ->keyBy('category_id');

        $lastMonth = date('m') == '01' ? '12' : str_pad(date('m') - 1, 2, '0', STR_PAD_LEFT);
        $lastYear = date('m') == '01' ? date('Y') - 1 : date('Y');

        $lastMonthExpense = ExpenseCalculation::whereYear('date', $lastYear)
            ->whereMonth('date', $lastMonth)
            ->where('types', 'EXPENSE')
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as totalExpense'))
            ->get()
            ->keyBy('category_id');

        $thisMonthProjectedExpenses = ProjectedExpense::whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->get()
            ->keyBy('category_id');

        $rows = $categories->map(function ($category) use ($allYearExpenses, $lastMonthExpense, $thisMonthProjectedExpenses) {
            $yearRow = $allYearExpenses->get($category->id);
            $avg = $yearRow ? ceil($yearRow->totalExpense / max($yearRow->totalMonths, 1)) : 0;
            $last = $lastMonthExpense->get($category->id)->totalExpense ?? 0;
            $projected = $thisMonthProjectedExpenses->get($category->id)->amount ?? 0;

            return [
                'category' => $category->name,
                'avg' => $avg,
                'last' => $last,
                'projected' => $projected,
            ];
        })->filter(fn($row) => $row['avg'] > 0 || $row['last'] > 0);

        return view('backend.reports.exports.budget_projection', [
            'rows' => $rows,
            'avgRangeLabel' => ($this->startDate && $this->endDate)
                ? $this->startDate . ' to ' . $this->endDate
                : 'All Time',
        ]);
    }
}
