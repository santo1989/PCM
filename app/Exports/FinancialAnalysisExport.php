<?php

namespace App\Exports;

use App\DataTables\FinancialAnalysisDataTable;
use App\Services\FinancialAnalysisService;
use App\Services\FinancialAnalyticsService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * A data dump (KPIs, category totals, budget rows, transactions), not a
 * narrative artifact — deliberately excludes ai_insights, consistent with
 * the other 5 report exports which are all plain tabular dumps.
 */
class FinancialAnalysisExport implements FromView, ShouldAutoSize
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function view(): View
    {
        $service = new FinancialAnalysisService(new FinancialAnalyticsService());
        $range = $service->resolveDateRange($this->filters);
        $categoryId = !empty($this->filters['category_id']) ? (int) $this->filters['category_id'] : null;

        $transactions = (new FinancialAnalysisDataTable(array_merge($this->filters, [
            'start_date' => $range['start']->toDateString(),
            'end_date' => $range['end']->toDateString(),
        ])))->rows(1000);

        return view('backend.reports.exports.financial_analysis', [
            'startDate' => $range['start']->toDateString(),
            'endDate' => $range['end']->toDateString(),
            'label' => $range['label'],
            'kpis' => $service->getKpiSummary($this->filters),
            'categoryBreakdown' => $service->getCategoryBreakdown($range['start'], $range['end'], $categoryId),
            'budget' => $service->getBudgetWidget($range['year'], $range['month']),
            'transactions' => $transactions,
        ]);
    }
}
