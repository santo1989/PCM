<?php

namespace App\Http\Controllers;

use App\DataTables\FinancialAnalysisDataTable;
use App\Exports\FinancialAnalysisExport;
use App\Http\Requests\FinancialAnalysisRequest;
use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Services\AI\MLPipeline;
use App\Services\FinancialAnalysisService;
use Maatwebsite\Excel\Facades\Excel;

class FinancialAnalysisDashboardController extends Controller
{
    private FinancialAnalysisService $service;

    public function __construct(FinancialAnalysisService $service)
    {
        $this->service = $service;
    }

    /** Initial page load: cheap server-rendered shell (filter options + transaction table). Everything else (KPIs, charts, insights) loads via fetch() from data(). */
    public function index(FinancialAnalysisRequest $request)
    {
        $filters = $request->validated();
        $range = $this->service->resolveDateRange($filters);

        $transactions = (new FinancialAnalysisDataTable(array_merge($filters, [
            'start_date' => $range['start']->toDateString(),
            'end_date' => $range['end']->toDateString(),
        ])))->rows();

        return view('backend.reports.financial_analysis.index', [
            'categories' => Category::where('types', 'EXPENSE')->orderBy('name')->get(),
            'range' => $range,
            'filters' => $filters,
            'transactions' => $transactions,
            'minDataDate' => ExpenseCalculation::min('date'),
        ]);
    }

    public function data(FinancialAnalysisRequest $request)
    {
        return response()->json($this->service->getDashboardData($request->validated()));
    }

    public function kpi(string $type, FinancialAnalysisRequest $request)
    {
        return response()->json($this->service->getKPI($type, $request->validated()));
    }

    /** POST alias of data() — lets the front end submit a custom date range / category via form body instead of a long query string. */
    public function analyze(FinancialAnalysisRequest $request)
    {
        return response()->json($this->service->getDashboardData($request->validated()));
    }

    public function insights(FinancialAnalysisRequest $request)
    {
        $filters = $request->validated();
        $range = $this->service->resolveDateRange($filters);
        $categoryId = !empty($filters['category_id']) ? (int) $filters['category_id'] : null;

        return response()->json(MLPipeline::run($range['start'], $range['end'], $categoryId));
    }

    public function export(FinancialAnalysisRequest $request)
    {
        return Excel::download(
            new FinancialAnalysisExport($request->validated()),
            'financial-analysis-' . now()->format('Ymd') . '.xlsx'
        );
    }
}
