<?php

namespace App\Exports;

use App\Models\ExpenseCalculation;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class YearlyReportExport implements FromView, ShouldAutoSize
{
    private $year;

    public function __construct(int $year)
    {
        $this->year = $year;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        $monthlyData = [];

        for ($month = 1; $month <= 12; $month++) {
            $income = (float) ExpenseCalculation::where('types', 'INCOME')
                ->whereYear('date', $this->year)
                ->whereMonth('date', $month)
                ->sum('amount');

            $expense = (float) ExpenseCalculation::where('types', 'EXPENSE')
                ->whereYear('date', $this->year)
                ->whereMonth('date', $month)
                ->sum('amount');

            $needs = ExpenseCalculation::where('rules', 'NEEDS')->whereYear('date', $this->year)->whereMonth('date', $month)->sum('amount');
            $wants = ExpenseCalculation::where('rules', 'WANTS')->whereYear('date', $this->year)->whereMonth('date', $month)->sum('amount');
            $savings = ExpenseCalculation::where('rules', 'SAVINGS')->whereYear('date', $this->year)->whereMonth('date', $month)->sum('amount');

            $monthlyData[$month] = [
                'month' => date('F', mktime(0, 0, 0, $month, 1)),
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
                'needs' => $needs,
                'wants' => $wants,
                'savings' => $savings,
            ];
        }

        return view('backend.reports.exports.yearly', [
            'year' => $this->year,
            'monthlyData' => $monthlyData,
        ]);
    }
}
