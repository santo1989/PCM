<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Predictive Budget
    </x-slot>

    <div class="container-fluid pt-4">

        @include('backend.reports.partials.report_nav')

        <div class="gradient-header mb-4">
            <h2 class="mb-1">Predictive Budget</h2>
            <div class="small">
                Next month's budget per category, projected from a linear trend over each category's last 6
                completed months — an automatic alternative to
                <a href="{{ route('Budge_Projection') }}" class="text-white text-decoration-underline">Budget Projection</a>'s
                manual reduction-percent approach. Shown side by side so you can compare the two, not as a replacement.
            </div>
        </div>

        @if (empty($rows))
            <div class="alert alert-info">Not enough expense history yet to build a forecast.</div>
        @else
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card grad-1">
                        <div class="small opacity-75">Total Predicted Next Month</div>
                        <div class="fs-3 fw-bold">{{ number_format($totalPredicted, 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-responsive-cards">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Last 6 Months (oldest → newest)</th>
                                    <th>Predicted Next Month</th>
                                    <th>Range</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td data-label="Category">{{ $row['category'] }}</td>
                                        <td data-label="Last 6 Months">
                                            <span class="small text-muted">
                                                {{ implode(' → ', array_map(fn($v) => number_format($v, 0), $row['last_6_months'])) }}
                                            </span>
                                        </td>
                                        <td data-label="Predicted Next Month" class="fw-bold">{{ number_format($row['predicted_next_month'], 2) }}</td>
                                        <td data-label="Range">
                                            <span class="small text-muted">{{ number_format($row['lower'], 0) }} – {{ number_format($row['upper'], 0) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-backend.layouts.master>
