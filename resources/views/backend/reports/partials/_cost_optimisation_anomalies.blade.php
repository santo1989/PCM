@if (empty($suggestions))
    <div class="alert alert-light border">
        No categories have crossed their 90th-percentile <em>full month</em> historical spend yet. This check
        needs at least 4 completed months of history per category and looks at full-month totals — it's a
        stricter, longer-horizon signal than the live pace section above, so it naturally stays quiet more
        often, especially early in a month.
    </div>
@else
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card grad-4">
                <div class="small opacity-75">Total Potential Saving This Month</div>
                <div class="fs-3 fw-bold">{{ number_format(max($totalPotentialSaving, 0), 2) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @foreach ($suggestions as $row)
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="mb-0">{{ $row['category_name'] }}</h5>
                            <span class="badge bg-danger">+{{ number_format($row['over_percent'], 0) }}%</span>
                        </div>
                        <p class="text-muted small mb-3">{{ $row['suggestion'] }}</p>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="small text-muted">This Month</div>
                                <div class="fw-bold">{{ number_format($row['current'], 2) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Typical Month (P90)</div>
                                <div class="fw-bold">{{ number_format($row['p90_historical'], 2) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Potential Saving</div>
                                <div class="fw-bold text-success">{{ number_format(max($row['potential_saving'], 0), 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
