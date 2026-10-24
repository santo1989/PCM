<?php

namespace App\Exports;

use App\Models\ExpenseCalculation;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class MonthlyInvestExport implements FromView, ShouldAutoSize
{
    const INVESTMENT_START_YEAR = 2022;

    private $startDate;
    private $endDate;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        $incomes = ExpenseCalculation::where('category_id', 1)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                $amount = (float) $item->amount;
                $years = max(now()->year - self::INVESTMENT_START_YEAR, 0);
                $investmentPercent = min(0.3 * pow(1.1, $years), 0.8);

                return [
                    'date' => $item->date,
                    'amount' => $amount,
                    'investment' => $amount * $investmentPercent,
                    'needs' => $amount * 0.5,
                    'wants' => $amount * 0.1,
                    'future' => $amount * 0.1,
                ];
            });

        return view('backend.reports.exports.monthly_invest', [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'incomes' => $incomes,
        ]);
    }
}
