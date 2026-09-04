<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class InteractiveDashboardExport implements FromView, ShouldAutoSize
{
    private $year;
    private $month;

    public function __construct(int $year, ?int $month = null)
    {
        $this->year = $year;
        $this->month = $month;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        $totalIncome = (float) ExpenseCalculation::where('types', 'INCOME')->whereYear('date', $this->year)->sum('amount');
        $totalExpense = (float) ExpenseCalculation::where('types', 'EXPENSE')->whereYear('date', $this->year)->sum('amount');

        $q = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereYear('date', $this->year)
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'));

        if ($this->month) {
            $q->whereMonth('date', $this->month);
        }

        $categoryBreakdown = $q->orderBy('total', 'desc')->get()->map(function ($row) {
            $row->category_name = optional(Category::find($row->category_id))->name ?? 'Unknown';
            return $row;
        });

        $recentTransactions = ExpenseCalculation::with('category')
            ->whereYear('date', $this->year)
            ->when($this->month, fn($query) => $query->whereMonth('date', $this->month))
            ->orderBy('date', 'desc')
            ->limit(200)
            ->get();

        return view('backend.reports.exports.interactive_dashboard', [
            'year' => $this->year,
            'month' => $this->month,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'categoryBreakdown' => $categoryBreakdown,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
