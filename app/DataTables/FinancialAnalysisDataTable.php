<?php

namespace App\DataTables;

use App\Models\ExpenseCalculation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Plain query-builder class for the dashboard's transaction table — no Yajra
 * DataTables package is installed in this app, so this is a small hand-rolled
 * equivalent shared by the server-rendered initial table and the JSON payload
 * (both call rows() so they stay in sync from one source of truth).
 */
class FinancialAnalysisDataTable
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        $query = ExpenseCalculation::with('category');

        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $query->whereBetween('date', [$this->filters['start_date'], $this->filters['end_date']]);
        }

        if (!empty($this->filters['category_id'])) {
            $query->where('category_id', (int) $this->filters['category_id']);
        }

        if (!empty($this->filters['types'])) {
            $query->where('types', strtoupper($this->filters['types']));
        }

        if (!empty($this->filters['rules'])) {
            $query->where('rules', strtoupper($this->filters['rules']));
        }

        if (!empty($this->filters['search'])) {
            $query->where('name', 'like', '%' . $this->filters['search'] . '%');
        }

        $sortable = ['date', 'amount', 'name'];
        $sort = in_array($this->filters['sort'] ?? null, $sortable, true) ? $this->filters['sort'] : 'date';
        $dir = ($this->filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $dir);
    }

    public function rows(int $limit = 500): Collection
    {
        return $this->query()->limit($limit)->get()->map(fn($row) => [
            'id' => $row->id,
            'date' => $row->date,
            'name' => $row->name,
            'category' => $row->category->name ?? 'Unknown',
            'category_id' => $row->category_id,
            'types' => $row->types,
            'rules' => $row->rules,
            'amount' => (float) $row->amount,
        ]);
    }
}
