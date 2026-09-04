<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class MonthlyReportExport implements FromView, ShouldAutoSize
{
    private $startDate;
    private $endDate;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        $startDate = $this->startDate;
        $endDate = $this->endDate;

        $totalIncome = (float) ExpenseCalculation::where('types', 'INCOME')
            ->whereBetween('date', [$startDate, $endDate])->sum('amount');

        $totalExpense = (float) ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$startDate, $endDate])->sum('amount');

        $incomeByCategory = ExpenseCalculation::where('types', 'INCOME')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->orderBy('total', 'desc')
            ->get()
            ->map(function ($row) {
                $row->category_name = optional(Category::find($row->category_id))->name ?? 'Unknown';
                return $row;
            });

        $expenseByCategory = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->orderBy('total', 'desc')
            ->get()
            ->map(function ($row) {
                $row->category_name = optional(Category::find($row->category_id))->name ?? 'Unknown';
                return $row;
            });

        return view('backend.reports.exports.monthly', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'incomeByCategory' => $incomeByCategory,
            'expenseByCategory' => $expenseByCategory,
        ]);
    }
}
