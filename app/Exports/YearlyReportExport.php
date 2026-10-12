<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class YearlyReportExport implements FromView, ShouldAutoSize
{
    private $monthlyData;
    private $startDate;
    private $endDate;

    /**
     * @param array $monthlyData Built by HandCashController::buildYearlyMonthlyData()
     */
    public function __construct(array $monthlyData, string $startDate, string $endDate)
    {
        $this->monthlyData = $monthlyData;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        return view('backend.reports.exports.yearly', [
            'monthlyData' => $this->monthlyData,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }
}
