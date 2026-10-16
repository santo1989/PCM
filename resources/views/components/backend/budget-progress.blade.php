{{-- This month's spend vs. each category's monthly budget. --}}
@props(['limit' => null])
@php
    $rows = \App\Services\BudgetService::currentMonth();
    $shown = $limit ? $rows->take($limit) : $rows;
@endphp
<div {{ $attributes->class(['card']) }}>
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-bullseye me-1"></i> Budgets · {{ now()->format('F Y') }}</span>
        <a href="{{ route('categories.index') }}" class="small fw-normal">Manage</a>
    </div>
    <div class="card-body">
        @forelse ($shown as $row)
            @php($color = $row['percent'] >= 100 ? 'bg-danger' : ($row['percent'] >= \App\Services\BudgetService::WARN_AT ? 'bg-warning' : 'bg-success'))
            <div class="budget-row">
                <div class="d-flex justify-content-between small mb-1 gap-2">
                    <span class="fw-semibold text-truncate">{{ $row['name'] }}</span>
                    <span class="text-nowrap text-muted">
                        {{ number_format($row['spent'], 0) }} / {{ number_format($row['budget'], 0) }}
                    </span>
                </div>
                <div class="progress" role="progressbar" aria-label="{{ $row['name'] }} budget used"
                    aria-valuenow="{{ min($row['percent'], 100) }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar {{ $color }}" style="width: {{ min($row['percent'], 100) }}%"></div>
                </div>
                <div class="small mt-1 {{ $row['remaining'] < 0 ? 'text-danger' : 'text-muted' }}">
                    {{ $row['remaining'] < 0 ? number_format(-$row['remaining'], 2) . ' over' : number_format($row['remaining'], 2) . ' left' }}
                </div>
            </div>
        @empty
            <div class="text-center text-muted small py-2">
                No budgets yet. Add a monthly budget to an expense category to track it here.
                <div class="mt-2"><a href="{{ route('categories.index') }}" class="btn btn-sm btn-outline-primary">Set a budget</a></div>
            </div>
        @endforelse
        @if ($limit && $rows->count() > $limit)
            <a href="{{ route('categories.index') }}" class="d-block text-center small mt-3">View all {{ $rows->count() }} budgets</a>
        @endif
    </div>
</div>
