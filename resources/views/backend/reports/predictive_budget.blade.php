<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Predictive Budget
    </x-slot>

    <div class="container-fluid pt-4">

        @include('backend.reports.partials.report_nav')

        <div class="gradient-header mb-4">
            <h2 class="mb-1">Predictive Budget</h2>
            <div class="small">
                Budget per category for {{ $targetMonth ?? 'the next month' }}, from the model that forecast each category best when
                tested on past months (one-off purchases left out; the old linear trend is shown for comparison) —
                an automatic alternative to
                <a href="{{ route('Budge_Projection') }}" class="text-white text-decoration-underline">Budget Projection</a>'s
                manual reduction-percent approach. Shown side by side so you can compare the two, not as a replacement.
            </div>
        </div>

        <x-backend.date-range-filter :start="$startDate" :end="$endDate" :min="$minDataDate"
            note="History window used for the forecast; it predicts the month right after the end date.">
                <button type="button" class="btn btn-outline-secondary flex-fill flex-md-grow-0" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print / PDF
                </button>
        </x-backend.date-range-filter>

        @if (empty($rows))
            <div class="alert alert-info">Not enough expense history yet to build a forecast.</div>
        @else
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card grad-1">
                        <div class="small opacity-75">Total predicted for {{ $targetMonth }}</div>
                        <div class="fs-3 fw-bold">{{ number_format($totalPredicted, 2) }}</div>
                        <div class="small opacity-75">previous linear method: {{ number_format($totalLinear, 2) }}</div>
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
                                    <th>History (oldest → newest)</th>
                                    <th>Predicted {{ $targetMonth }}</th>
                                    <th>Likely range (80%)</th>
                                    <th>Model · accuracy</th>
                                    <th>Previous linear method</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td data-label="Category">{{ $row['category'] }}</td>
                                        <td data-label="History">
                                            <span class="small text-muted">
                                                {{ implode(' → ', array_map(fn($v) => number_format($v, 0), $row['last_6_months'])) }}
                                            </span>
                                        </td>
                                        <td data-label="Predicted Next Month" class="fw-bold">{{ number_format($row['predicted_next_month'], 2) }}</td>
                                        <td data-label="Range">
                                            <span class="small text-muted">{{ number_format($row['lower'], 0) }} – {{ number_format($row['upper'], 0) }}</span>
                                        </td>
                                        <td data-label="Model">
                                            <span class="badge text-bg-light border">{{ $row['model'] }}@if ($row['mase'] !== null) · MASE {{ $row['mase'] }}@endif</span>
                                            @if ($row['note'])<div class="small text-muted">{{ $row['note'] }}</div>@endif
                                        </td>
                                        <td data-label="Previous linear method" class="text-muted">{{ number_format($row['linear_prediction'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mb-0">MASE below 1 means the model beat "same month last year" on months it had not seen. The forecast uses every completed month before {{ $targetMonth }}, not only the window above; the history column still shows the window. Full details on the <a href="{{ route('analytics.index') }}">Analytics</a> page.</p>
                </div>
            </div>
        @endif

        @include('backend.reports.partials._ai_insights_panel', [
            'aiInsights' => $aiInsights,
            'title' => 'AI Trend Explanation (' . $startDate . ' to ' . $endDate . ')',
            'icon' => 'bi-bar-chart-line',
            'headerClass' => 'bg-primary text-white',
        ])

    </div>
</x-backend.layouts.master>
