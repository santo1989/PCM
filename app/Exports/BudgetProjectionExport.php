<?php

namespace App\Exports;

use App\Models\Category;
use App\Services\BudgetPredictor;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BudgetProjectionExport implements FromView, ShouldAutoSize
{
    private ?string $startDate;
    private ?string $endDate;

    /**
     * Same columns as the Budge_Projection page (BudgetPredictor::historyColumns): the
     * average covers the given range, or all completed months when no range is given.
     */
    public function __construct(?string $startDate = null, ?string $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        $categories = Category::where('types', 'EXPENSE')->get();

        $columns = BudgetPredictor::historyColumns($this->startDate, $this->endDate);

        $rows = $categories->map(fn($category) => [
            'category' => $category->name,
            'avg' => $columns['avg'][$category->id] ?? 0,
            'last' => $columns['last'][$category->id] ?? 0,
            'projected' => $columns['saved'][$category->id] ?? 0,
        ])->filter(fn($row) => $row['avg'] > 0 || $row['last'] > 0);

        return view('backend.reports.exports.budget_projection', [
            'rows' => $rows,
            'avgRangeLabel' => $columns['label'] . ', ' . $columns['months'] . ' months',
        ]);
    }
}
