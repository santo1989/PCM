@if (empty($paceRows))
    <div class="alert alert-info">
        Nothing is running hot yet this month compared to its historical daily pace. This section updates
        live as new expenses are entered — check back after a few more transactions, or as soon as any
        category starts spending faster than its usual rate.
    </div>
@else
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card grad-2">
                <div class="small opacity-75">Projected Overspend If This Pace Continues</div>
                <div class="fs-3 fw-bold">{{ number_format($totalProjectedOverspend, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($paceRows as $row)
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="mb-0">{{ $row['category_name'] }}</h5>
                            <span class="badge bg-danger">+{{ number_format($row['variance_percent'], 0) }}% pace</span>
                        </div>
                        <p class="text-muted small mb-3">{{ $row['suggestion'] }}</p>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="small text-muted">Spent So Far ({{ $row['days_elapsed'] }}d)</div>
                                <div class="fw-bold">{{ number_format($row['month_to_date'], 2) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Projected Month Total</div>
                                <div class="fw-bold text-danger">{{ number_format($row['projected_month_total'], 2) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Usual Daily Rate</div>
                                <div class="fw-bold">{{ number_format($row['historical_daily_pace'], 2) }}/day</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
