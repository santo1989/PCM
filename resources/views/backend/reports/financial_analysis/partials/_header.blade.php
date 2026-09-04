@php
    $currentPeriod = $filters['period'] ?? 'this_month';
@endphp
<div class="gradient-header mb-4">
    <h2 class="mb-1"><i class="bi bi-clipboard-data"></i> Financial Analysis Dashboard</h2>
    <div class="small">
        Consolidated view across budget, income/expense, investments, and AI-powered insights.
        Last updated: <span id="lastUpdated">—</span>
    </div>
</div>

<div class="card mb-4 no-print">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Period</label>
                <select id="periodSelect" class="form-select form-select-sm select2">
                    <option value="this_month" {{ $currentPeriod === 'this_month' ? 'selected' : '' }}>This Month</option>
                    <option value="last_month" {{ $currentPeriod === 'last_month' ? 'selected' : '' }}>Last Month</option>
                    <option value="last_3_months" {{ $currentPeriod === 'last_3_months' ? 'selected' : '' }}>Last 3 Months</option>
                    <option value="last_6_months" {{ $currentPeriod === 'last_6_months' ? 'selected' : '' }}>Last 6 Months</option>
                    <option value="last_12_months" {{ $currentPeriod === 'last_12_months' ? 'selected' : '' }}>Last 12 Months</option>
                    <option value="this_year" {{ $currentPeriod === 'this_year' ? 'selected' : '' }}>This Year</option>
                    <option value="last_year" {{ $currentPeriod === 'last_year' ? 'selected' : '' }}>Last Year</option>
                    <option value="custom" {{ $currentPeriod === 'custom' ? 'selected' : '' }}>Custom Range</option>
                </select>
            </div>
            <div id="customRange" class="col-md-3 d-flex gap-2">
                <div class="flex-fill">
                    <label class="form-label small mb-1">Start</label>
                    <input type="date" id="startDateInput" class="form-control form-control-sm"
                        value="{{ $filters['start_date'] ?? '' }}" min="{{ $minDataDate }}" max="{{ now()->toDateString() }}">
                </div>
                <div class="flex-fill">
                    <label class="form-label small mb-1">End</label>
                    <input type="date" id="endDateInput" class="form-control form-control-sm"
                        value="{{ $filters['end_date'] ?? '' }}" min="{{ $minDataDate }}" max="{{ now()->toDateString() }}">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Category</label>
                <select id="categorySelect" class="form-select form-select-sm select2">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ ($filters['category_id'] ?? null) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Auto-Refresh</label>
                <select id="autoRefreshSelect" class="form-select form-select-sm select2">
                    <option value="0">Off</option>
                    <option value="30" selected>Every 30s</option>
                    <option value="60">Every 60s</option>
                    <option value="120">Every 120s</option>
                </select>
            </div>
            <div class="col-md-2 d-flex flex-wrap gap-2 justify-content-md-end">
                <a href="#" id="exportLink" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-excel"></i> Export
                </a>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button type="button" id="refreshBtn" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>
