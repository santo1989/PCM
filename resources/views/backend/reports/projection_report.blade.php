<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Budget Projection Report
    </x-slot>

    <div class="container">

        <x-backend.layouts.elements.message :message="session('message')" />
        <x-backend.layouts.elements.message :message="session('error_message')" type="error" />
        <x-backend.layouts.elements.errors />

        @include('backend.reports.partials.report_nav')

        <x-backend.date-range-filter :start="$startDate" :end="$endDate" :min="$minDataDate"
            note="Filters the Avg Expense column and the AI panel. This month's income, last month's expense and next month's projection always use the current/adjacent calendar month.">
                <a href="{{ route('Budge_Projection.export_excel', ['start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn btn-outline-success flex-fill flex-md-grow-0">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </a>
                <button type="button" class="btn btn-outline-secondary flex-fill flex-md-grow-0" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print / PDF
                </button>
        </x-backend.date-range-filter>

        <!-- Forecasted budget (linear regression on the last 6 completed months) -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up"></i> Forecasted Total Expense (Linear Regression)</span>
                <button type="button" id="compareActualBtn" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-clipboard-data"></i> Compare with Actual
                </button>
            </div>
            <div class="card-body">
                <canvas id="forecastChart" height="90"></canvas>
                <div class="small text-muted mt-2">
                    Shaded band is a naive +/- one residual-standard-error range around the trend line, not a
                    rigorous statistical confidence interval — treat it as "plausible range," not a guarantee.
                </div>

                <div id="compareActualPanel" class="mt-4" style="display: none;">
                    <h6>Actual vs. Projected — most recent month with a saved projection</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Projected</th>
                                    <th>Actual</th>
                                    <th>Utilization</th>
                                </tr>
                            </thead>
                            <tbody id="compareActualBody">
                                <tr><td colspan="4" class="text-center text-muted">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @include('backend.reports.partials._ai_insights_panel', [
                'aiInsights' => $aiInsights,
                'title' => 'AI-Powered Budget Insights (' . ($hasDateRange ? $startDate . ' to ' . $endDate : 'This Month') . ')',
            ])
        </div>
    </div>

    @php
        $methodNames = [
            'seasonalNaive' => 'Seasonal', 'wma' => 'Weighted avg', 'ses' => 'Exp. smoothing',
            'holtDamped' => 'Holt trend', 'holtWinters' => 'Holt-Winters', 'croston' => 'Croston', 'none' => '—',
        ];
        $a = $budgetAnalytics;
        $kpi = fn($label, $value, $hint = '', $cls = '') => '<div class="col-6 col-md-4 col-xl-3"><div class="border rounded p-2 h-100"><div class="small text-muted">' . e($label) . '</div><div class="fs-5 fw-semibold ' . $cls . '">' . $value . '</div>' . ($hint ? '<div class="small text-muted">' . $hint . '</div>' : '') . '</div></div>';
    @endphp
    <div class="container">
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-cpu"></i> Budget Analytics — {{ $a['label'] }} (local AI, from your expense &amp; hand-cash data)</div>
        <div class="card-body">
            <div class="row g-2">
                {!! $kpi('Expected spend', number_format($a['spend_forecast']), '80% range ' . number_format($a['spend_lo']) . '–' . number_format($a['spend_hi'])) !!}
                {!! $kpi('Spending cap', number_format($a['spend_cap']), '70% of ' . number_format($a['income']) . ' salary' . ($a['income_source'] === 'forecast' ? ' (forecast)' : '') . ' − counted DPS/FD') !!}
                {!! $kpi('Chance of breaking 70%', $a['overspend_risk'] === null ? '—' : $a['overspend_risk'] . '%', 'normal model of past misses', ($a['overspend_risk'] ?? 0) >= 50 ? 'text-danger' : 'text-success') !!}
                {!! $kpi('Spending trend', ($a['trend_per_month'] >= 0 ? '+' : '−') . number_format(abs($a['trend_per_month'])) . '/mo', 'Theil–Sen slope, last 12 months', $a['trend_per_month'] > 0 ? 'text-danger' : 'text-success') !!}
                {!! $kpi('Savings rate', $a['savings_rate'] === null ? '—' : $a['savings_rate'] . '%', 'median of last 6 months (salary − spend)', ($a['savings_rate'] ?? 0) < 20 ? 'text-danger' : 'text-success') !!}
                {!! $kpi('Cash runway', $a['runway_months'] === null ? '—' : $a['runway_months'] . ' months', 'liquid ' . number_format($a['liquid']) . ' ÷ monthly outflow', ($a['runway_months'] ?? 0) < 6 ? 'text-warning' : 'text-success') !!}
                {!! $kpi('Emergency fund', number_format($a['liquid']) . ' / ' . number_format($a['emergency_target']), 'liquid vs 6 months of needs', $a['liquid'] < $a['emergency_target'] ? 'text-warning' : 'text-success') !!}
                {!! $kpi('DPS / FD installments', number_format($a['installments']) . '/mo', '14% share ' . number_format($a['installments_target']) . ' + 20% savings ' . number_format($a['savings_target'])) !!}
                {!! $kpi('Investing', number_format($a['invest_monthly']) . '/mo', 'target 10% = ' . number_format($a['invest_target']), $a['invest_monthly'] < $a['invest_target'] ? 'text-warning' : 'text-success') !!}
                {!! $kpi('Long-term savings', number_format($a['long_term']), 'FD + DPS balances; stocks ' . number_format($a['invested_balance'])) !!}
                {!! $kpi('Model accuracy', $a['accuracy'] === null ? '—' : '±' . $a['accuracy'] . '%', 'back-test error, last 6 months') !!}
            </div>
            <details class="small text-muted mt-3">
                <summary>How the forecast is made</summary>
                Each category's monthly history is cleaned with a Hampel filter (one-off spikes removed, recurring
                seasonal peaks kept), then six models compete: seasonal naive, weighted moving average, exponential
                smoothing, damped-trend Holt, Holt-Winters (12-month season) and Croston/SBA for irregular spending.
                Every model is re-fitted and tested on each of the last 6 months; the forecast is their blend weighted by
                1/error², shifted by the median past miss (learned bias), with an 80% range from the back-test errors.
                Total-spend mix this month:
                @foreach ($a['weights'] as $m => $w)
                    {{ $methodNames[$m] ?? $m }} {{ $w }}%@if (!$loop->last), @endif
                @endforeach.
            </details>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span><i class="bi bi-calculator"></i> Projected Monthly Budget — {{ \Carbon\Carbon::parse($prediction['month'] . '-01')->format('F Y') }}</span>
            <button type="button" class="btn btn-sm btn-primary" id="budgetVsActualBtn">
                <i class="bi bi-bar-chart-steps"></i> Budget vs Actual (month-wise)
            </button>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3">
                Budget = each category's forecast, less your extra reduction, cut to the spending cap:
                wants first (up to 60%), then other (40%), then needs (20%). Savings-rule lines are never cut.
            </p>
            <form action="{{ route('calculate_and_save_budget') }}" method="post" id="projectionForm">
                @csrf
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-4">
                        <label for="reduction_percent" class="form-label small mb-1">Extra reduction target (0.10 = 10%)</label>
                        <input type="number" step="0.01" min="0" max="0.9" class="form-control"
                            name="reduction_percent" id="reduction_percent" value="{{ request('reduction_percent', 0) }}">
                    </div>
                    <div class="col-12 col-md-8 d-flex flex-wrap gap-2">
                        <button type="button" id="previewBtn" class="btn btn-secondary flex-fill flex-md-grow-0">Preview &amp; Edit</button>
                        <button type="button" id="saveBudgetBtn" class="btn btn-primary flex-fill flex-md-grow-0">Save Next Month's Budget</button>
                    </div>
                </div>
                <input type="hidden" name="date" value="{{ date('Y-m-d') }}">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Avg Monthly Expense <div class="small text-muted fw-normal">{{ $columns['label'] }} ({{ $columns['months'] }} months)</div></th>
                                <th>Last Month Expensed <div class="small text-muted fw-normal">{{ $columns['last_label'] }}</div></th>
                                <th>This Month Saved Budget <div class="small text-muted fw-normal">{{ $columns['saved_label'] }}{{ $columns['saved'] ? '' : ' — none saved' }}</div></th>
                                <th>AI Forecast <span class="small text-muted fw-normal">(80% range · best model)</span></th>
                                <th>Next Month Budget</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                @php
                                    $avg = $columns['avg'][$category->id] ?? 0;
                                    $last = $columns['last'][$category->id] ?? 0;
                                    $projectedAmount = $columns['saved'][$category->id] ?? null;
                                    $plan = $prediction['rows'][$category->id] ?? null;
                                @endphp

                                @if ($avg > 0 || $last > 0 || $plan)
                                    <tr>
                                        <td>{{ $category->name }}
                                            <span class="badge bg-light text-dark border">{{ $plan['rule'] ?? ($category->rules ?: 'OTHER') }}</span>
                                            <input type="hidden" name="category_id[]" value="{{ $category->id }}"></td>
                                        <td>{{ number_format($avg, 2) }}</td>
                                        <td>{{ number_format($last, 2) }}</td>
                                        <td>{{ $projectedAmount === null ? '—' : number_format($projectedAmount, 2) }}</td>
                                        <td>{{ number_format($plan['forecast'] ?? 0, 2) }}
                                            @if ($plan)
                                                <div class="small text-muted">{{ number_format($plan['lo']) }}–{{ number_format($plan['hi']) }} · {{ $methodNames[$plan['method']] ?? $plan['method'] }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="text" name="projectedExpense[{{ $category->id }}]"
                                                id="projectedExpense{{ $category->id }}"
                                                class="form-control form-control-sm projectedExpenseInput"
                                                value="{{ number_format($plan['budget'] ?? 0, 2, '.', '') }}" readonly
                                                data-forecast="{{ $plan['forecast'] ?? 0 }}" data-rule="{{ $plan['rule'] ?? 'OTHER' }}"
                                                data-avg="{{ $avg }}" data-last="{{ $last }}"
                                                data-cat="{{ $category->id }}" data-catname="{{ $category->name }}">
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2"><strong>Salary{{ $prediction['income_source'] === 'forecast' ? ' (forecast)' : '' }}:</strong>
                                    <strong id="monthlyIncomeOutput">{{ number_format($prediction['income'], 2) }}</strong>
                                    <div class="small text-muted">70% = {{ number_format($prediction['limit'], 2) }}</div></td>
                                <td colspan="2"><strong>Spending cap:</strong>
                                    <strong id="monthlyLimitOutput">{{ number_format($prediction['spend_cap'], 2) }}</strong>
                                    <div class="small text-muted">after {{ number_format($prediction['installments_counted']) }} of {{ number_format($prediction['installments']) }} DPS/FD installments</div></td>
                                <td><strong>{{ number_format($prediction['total_forecast'], 2) }}</strong></td>
                                <td><strong>Total:</strong> <strong id="totalProjectedOutput">0.00</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if ($prediction['gap'] > 0)
                    <div class="alert alert-danger small mb-0">
                        <strong>Structural gap: {{ number_format($prediction['gap']) }}.</strong>
                        Even after the maximum realistic cuts, next month's spending ({{ number_format($prediction['total_budget']) }})
                        is above the cap ({{ number_format($prediction['spend_cap']) }}). Closing it needs a bigger change:
                        @if ($prediction['installments'] > $prediction['installments_counted'])
                            DPS/FD installments ({{ number_format($prediction['installments']) }}/mo) already take
                            {{ $prediction['income'] > 0 ? round($prediction['installments'] / $prediction['income'] * 100) : '—' }}% of salary,
                        @endif
                        so raise income, cut a large NEEDS line (rent, medicine, freelancing cost) or pause discretionary spending.
                    </div>
                @elseif ($prediction['total_forecast'] > $prediction['spend_cap'])
                    <div class="alert alert-warning small mb-0">
                        At the current pace next month would cost {{ number_format($prediction['total_forecast']) }}.
                        The budget above trims {{ number_format($prediction['total_forecast'] - $prediction['total_budget']) }} to stay within 70% of salary.
                    </div>
                @endif
            </form>
        </div>
    </div>
    </div>

    <!-- Preview & edit modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">Preview & Edit Projected Expenses</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">You can tweak per-category projected amounts here before saving.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="previewTable">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>AI Forecast</th>
                                    <th>Projected</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- populated dynamically -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="text-right"><strong>Total</strong></td>
                                    <td><strong id="modalTotal">0.00</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="applyModalBtn" class="btn btn-primary">Apply & Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Budget vs Actual (month-wise) modal -->
    <div class="modal fade" id="budgetVsActualModal" tabindex="-1" aria-labelledby="budgetVsActualLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-fullscreen-md-down modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="budgetVsActualLabel"><i class="bi bi-bar-chart-steps"></i> Budget vs Actual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <button type="button" class="btn btn-outline-secondary" id="bvaPrev" aria-label="Previous month"><i class="bi bi-chevron-left"></i></button>
                        <input type="month" class="form-control w-auto" id="bvaMonth" value="{{ now()->format('Y-m') }}"
                            min="{{ $minDataDate ? \Carbon\Carbon::parse($minDataDate)->format('Y-m') : '' }}" max="{{ now()->format('Y-m') }}">
                        <button type="button" class="btn btn-outline-secondary" id="bvaNext" aria-label="Next month"><i class="bi bi-chevron-right"></i></button>
                        <span class="small text-muted" id="bvaSource"></span>
                    </div>
                    <div class="row g-2 mb-3" id="bvaCards"></div>
                    <div id="bvaAdvice"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Budget</th>
                                    <th class="text-end">Actual</th>
                                    <th class="text-end">Gap</th>
                                    <th style="min-width:120px">Used</th>
                                </tr>
                            </thead>
                            <tbody id="bvaBody">
                                <tr><td colspan="5" class="text-center text-muted">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Multi-Year Monthly Averages Section -->
    <div class="card mt-4">
        <div class="card-header">
            <h4 class="card-title">Multi-Year Monthly Averages</h4>
            <p class="card-subtitle">Historical spending patterns across all years</p>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs" id="multiYearTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="category-tab" data-bs-toggle="tab" data-bs-target="#category-tab-pane" type="button" role="tab">
                        Category-wise Averages
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="overall-tab" data-bs-toggle="tab" data-bs-target="#overall-tab-pane" type="button" role="tab">
                        Overall Averages
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary-tab-pane" type="button" role="tab">
                        Summary Insights
                    </button>
                </li>
            </ul>
            
            <div class="tab-content mt-3" id="multiYearTabContent">
                <!-- Category-wise Tab -->
                <div class="tab-pane fade show active" id="category-tab-pane" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    @for($month = 1; $month <= 12; $month++)
                                        <th class="text-center">{{ \Carbon\Carbon::create()->month($month)->format('M') }}</th>
                                    @endfor
                                    <th class="text-center">Yearly Avg</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($categories as $category)
                                    @php
                                        $categoryAverages = $formattedMultiYearAverages[$category->id] ?? [];
                                        $yearlyTotal = 0;
                                        $monthCount = 0;
                                    @endphp
                                    @if(!empty($categoryAverages))
                                        <tr>
                                            <td><strong>{{ $category->name }}</strong></td>
                                            @for($month = 1; $month <= 12; $month++)
                                                <td class="text-center">
                                                    @if(isset($categoryAverages[$month]))
                                                        @php
                                                            $yearlyTotal += $categoryAverages[$month]['avg_amount'];
                                                            $monthCount++;
                                                        @endphp
                                                        <span class="badge bg-info" 
                                                              data-bs-toggle="tooltip" 
                                                              title="Years: {{ $categoryAverages[$month]['years_count'] }}
Transactions: {{ $categoryAverages[$month]['transaction_count'] }}
Total: {{ number_format($categoryAverages[$month]['total_amount'], 0) }}">
                                                            {{ number_format($categoryAverages[$month]['avg_amount'], 0) }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                            @endfor
                                            <td class="text-center bg-light">
                                                @if($monthCount > 0)
                                                    <strong>{{ number_format($yearlyTotal / $monthCount, 0) }}</strong>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Overall Averages Tab -->
                <div class="tab-pane fade" id="overall-tab-pane" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Month</th>
                                            <th>Avg Amount</th>
                                            <th>Total Amount</th>
                                            <th>Years Data</th>
                                            <th>Transactions</th>
                                            <th>Trend</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $totalAvg = 0;
                                            $monthCount = 0;
                                        @endphp
                                        @for($month = 1; $month <= 12; $month++)
                                            @if(isset($formattedOverallMultiYearAverages[$month]))
                                                @php
                                                    $data = $formattedOverallMultiYearAverages[$month];
                                                    $totalAvg += $data['avg_amount'];
                                                    $monthCount++;
                                                @endphp
                                                <tr>
                                                    <td><strong>{{ $data['month_name'] }}</strong></td>
                                                    <td class="text-right">
                                                        <span class="badge bg-primary">
                                                            {{ number_format($data['avg_amount'], 0) }}
                                                        </span>
                                                    </td>
                                                    <td class="text-right">
                                                        {{ number_format($data['total_amount'], 0) }}
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info">{{ $data['years_count'] }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        {{ $data['transaction_count'] }}
                                                    </td>
                                                    <td>
                                                        @php
                                                            $maxAvg = collect($formattedOverallMultiYearAverages)->max('avg_amount');
                                                            $percentage = $maxAvg > 0 ? ($data['avg_amount'] / $maxAvg) * 100 : 0;
                                                        @endphp
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-{{ $percentage > 80 ? 'danger' : ($percentage > 50 ? 'warning' : 'success') }}" 
                                                                 role="progressbar" 
                                                                 style="width: {{ $percentage }}%"
                                                                 aria-valuenow="{{ $percentage }}" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100">
                                                                {{ round($percentage) }}%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endfor
                                        @if($monthCount > 0)
                                            <tr class="table-primary">
                                                <td><strong>Average</strong></td>
                                                <td class="text-right">
                                                    <strong>{{ number_format($totalAvg / $monthCount, 0) }}</strong>
                                                </td>
                                                <td colspan="4"></td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Monthly Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="monthlyAveragesChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Insights Tab -->
                <div class="tab-pane fade" id="summary-tab-pane" role="tabpanel">
                    <div class="row">
                        @php
                            $monthsWithData = collect($formattedOverallMultiYearAverages)->filter(function($item) {
                                return $item['avg_amount'] > 0;
                            });
                            $highestMonth = $monthsWithData->sortByDesc('avg_amount')->first();
                            $lowestMonth = $monthsWithData->sortBy('avg_amount')->first();
                            $totalMonthsData = $monthsWithData->count();
                            $averagePerMonth = $monthsWithData->avg('avg_amount');
                        @endphp
                        
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">Key Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="text-center p-3 border rounded">
                                                <h2 class="text-primary">{{ $totalMonthsData }}</h2>
                                                <p class="mb-0">Months with Data</p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center p-3 border rounded">
                                                <h2 class="text-success">{{ $formattedOverallMultiYearAverages ? count(array_unique(array_column($formattedOverallMultiYearAverages, 'years_count'))) : 0 }}</h2>
                                                <p class="mb-0">Years Analyzed</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <h6>Average Monthly Spending:</h6>
                                        <div class="display-4 text-center text-success">
                                            {{ number_format($averagePerMonth, 0) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-info text-white">
                                    <h5 class="card-title mb-0">Highest & Lowest Months</h5>
                                </div>
                                <div class="card-body">
                                    @if($highestMonth)
                                        <div class="alert alert-danger">
                                            <h6>📈 Highest Spending Month:</h6>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong>{{ $highestMonth['month_name'] }}</strong>
                                                <span class="badge bg-danger">
                                                    {{ number_format($highestMonth['avg_amount'], 0) }}
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                Based on {{ $highestMonth['years_count'] }} years of data
                                            </small>
                                        </div>
                                    @endif
                                    
                                    @if($lowestMonth)
                                        <div class="alert alert-success">
                                            <h6>📉 Lowest Spending Month:</h6>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong>{{ $lowestMonth['month_name'] }}</strong>
                                                <span class="badge bg-success">
                                                    {{ number_format($lowestMonth['avg_amount'], 0) }}
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                Based on {{ $lowestMonth['years_count'] }} years of data
                                            </small>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Recommendations</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        @if($highestMonth && $lowestMonth)
                                            <li class="list-group-item">
                                                💡 Consider reducing spending in <strong>{{ $highestMonth['month_name'] }}</strong> 
                                                ({{ number_format($highestMonth['avg_amount'], 0) }}) by allocating some expenses 
                                                to <strong>{{ $lowestMonth['month_name'] }}</strong> 
                                                ({{ number_format($lowestMonth['avg_amount'], 0) }})
                                            </li>
                                        @endif
                                        
                                        <li class="list-group-item">
                                            💡 Use these historical averages to set more accurate monthly budgets
                                        </li>
                                        
                                        <li class="list-group-item">
                                            💡 Note that averages are based on {{ $monthsWithData->first()['years_count'] ?? 0 }} 
                                            years of data, providing reliable patterns
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function calculateTotal() {
            var total = 0;
            var projectedExpenseInputs = document.getElementsByClassName('projectedExpenseInput');
            for (var i = 0; i < projectedExpenseInputs.length; i++) {
                var value = parseFloat(projectedExpenseInputs[i].value);
                total += isNaN(value) ? 0 : value;
            }
            document.getElementById('totalProjectedOutput').innerText = total.toFixed(2);
        }

        function previewProjection() {
            var inputs = document.getElementsByClassName('projectedExpenseInput');
            var bases = [];
            for (var i = 0; i < inputs.length; i++) {
                bases.push({ el: inputs[i], base: parseFloat(inputs[i].dataset.forecast) || 0 });
            }

            // Populate modal table for editing
            var tbody = document.querySelector('#previewTable tbody');
            tbody.innerHTML = '';
            var modalTotal = 0;
            for (var i = 0; i < bases.length; i++) {
                var inp = bases[i].el;
                var cat = inp.dataset.cat;
                var catname = inp.dataset.catname || ('Category ' + cat);
                var baseVal = bases[i].base;
                var projVal = parseFloat(inp.value) || 0;

                var tr = document.createElement('tr');
                var tdName = document.createElement('td');
                tdName.textContent = catname;
                var tdBase = document.createElement('td');
                tdBase.textContent = baseVal.toFixed(2);
                var tdProj = document.createElement('td');
                var modalInput = document.createElement('input');
                modalInput.type = 'number';
                modalInput.step = '0.01';
                modalInput.min = '0';
                modalInput.className = 'form-control modalProjectedInput';
                modalInput.value = projVal.toFixed(2);
                modalInput.dataset.cat = cat;
                tdProj.appendChild(modalInput);

                tr.appendChild(tdName);
                tr.appendChild(tdBase);
                tr.appendChild(tdProj);
                tbody.appendChild(tr);

                modalTotal += projVal;
            }
            document.getElementById('modalTotal').innerText = modalTotal.toFixed(2);

            // show modal (support Bootstrap 4 & 5)
            try {
                if (window.jQuery && typeof jQuery('#previewModal').modal === 'function') {
                    jQuery('#previewModal').modal('show');
                } else if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
                    var m = new bootstrap.Modal(document.getElementById('previewModal'));
                    m.show();
                }
            } catch (e) {
                console.warn('Could not show modal', e);
            }

            calculateTotal();
        }

        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
            // The budget is computed server-side; a new reduction target re-runs it.
            document.getElementById('reduction_percent').addEventListener('change', function() {
                var url = new URL(window.location.href);
                url.searchParams.set('reduction_percent', this.value || 0);
                window.location.href = url.toString();
            });

            document.getElementById('previewBtn').addEventListener('click', function(e) {
                e.preventDefault();
                previewProjection();
            });

            document.getElementById('saveBudgetBtn').addEventListener('click', function() {
                Swal.fire({
                    title: "Save next month's budget?",
                    text: 'This will overwrite any existing projected budget for next month.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, save it',
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('projectionForm').submit();
                    }
                });
            });

            // Apply modal edits back to main inputs
            document.getElementById('applyModalBtn').addEventListener('click', function() {
                var modalInputs = document.getElementsByClassName('modalProjectedInput');
                var modalSum = 0;
                for (var i = 0; i < modalInputs.length; i++) {
                    var m = modalInputs[i];
                    var cat = m.dataset.cat;
                    var val = parseFloat(m.value) || 0;
                    modalSum += val;
                    var mainInp = document.querySelector('input.projectedExpenseInput[data-cat="' + cat +
                        '"]');
                    if (mainInp) mainInp.value = val.toFixed(2);
                }
                document.getElementById('modalTotal').innerText = modalSum.toFixed(2);
                calculateTotal();
                // hide modal
                try {
                    if (window.jQuery && typeof jQuery('#previewModal').modal === 'function') {
                        jQuery('#previewModal').modal('hide');
                    } else if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
                        var m = bootstrap.Modal.getInstance(document.getElementById('previewModal'));
                        if (m) m.hide();
                    }
                } catch (e) {
                    console.warn('Could not hide modal', e);
                }
            });

            // Initialize tooltips for the multi-year averages section
            if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip === 'function') {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }

            // Initialize chart for monthly averages
            initializeMonthlyAveragesChart();
        });

        function initializeMonthlyAveragesChart() {
            var ctx = document.getElementById('monthlyAveragesChart');
            if (!ctx) return;

            // Prepare data from PHP
            var labels = [];
            var data = [];
            var colors = [];
            
            @for($month = 1; $month <= 12; $month++)
                @if(isset($formattedOverallMultiYearAverages[$month]))
                    labels.push("{{ \Carbon\Carbon::create()->month($month)->format('M') }}");
                    data.push({{ $formattedOverallMultiYearAverages[$month]['avg_amount'] }});
                    
                    // Color coding based on spending level
                    @php
                        $maxAvg = collect($formattedOverallMultiYearAverages)->max('avg_amount');
                        $percentage = $maxAvg > 0 ? ($formattedOverallMultiYearAverages[$month]['avg_amount'] / $maxAvg) * 100 : 0;
                        $color = $percentage > 80 ? '#dc3545' : ($percentage > 50 ? '#ffc107' : '#28a745');
                    @endphp
                    colors.push("{{ $color }}");
                @endif
            @endfor

            if (data.length === 0) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Monthly Average Spending',
                        data: data,
                        backgroundColor: colors,
                        borderColor: '#495057',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Average: ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        async function initializeForecastChart() {
            const res = await fetch('{{ route('Budge_Projection.data.forecast') }}');
            const data = await res.json();

            const historyLabels = data.history.map(h => h.label);
            const historyValues = data.history.map(h => h.total);
            const labels = historyLabels.concat(data.forecast_labels);

            // Align forecast/band series so they only have values from where history ends.
            const pad = (arr) => Array(historyValues.length - 1).fill(null).concat([historyValues[historyValues.length - 1]], arr);

            new Chart(document.getElementById('forecastChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Actual',
                            data: historyValues.concat(Array(data.forecast_labels.length).fill(null)),
                            borderColor: '#667eea',
                            backgroundColor: '#667eea',
                            tension: 0.3,
                        },
                        {
                            label: 'Forecast',
                            data: pad(data.forecast.forecast),
                            borderColor: '#f5576c',
                            borderDash: [6, 4],
                            backgroundColor: 'transparent',
                            tension: 0.3,
                        },
                        {
                            label: 'Upper bound',
                            data: pad(data.forecast.upper),
                            borderColor: 'rgba(245, 87, 108, 0.25)',
                            backgroundColor: 'rgba(245, 87, 108, 0.1)',
                            fill: '+1',
                            pointRadius: 0,
                        },
                        {
                            label: 'Lower bound',
                            data: pad(data.forecast.lower),
                            borderColor: 'rgba(245, 87, 108, 0.25)',
                            backgroundColor: 'rgba(245, 87, 108, 0.1)',
                            fill: false,
                            pointRadius: 0,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true } },
                },
            });
        }

        document.getElementById('compareActualBtn').addEventListener('click', async function () {
            const panel = document.getElementById('compareActualPanel');
            const body = document.getElementById('compareActualBody');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            if (panel.style.display === 'none') return;

            const res = await fetch('{{ route('Budge_Projection.data.compare_actual') }}');
            const data = await res.json();

            if (!data.rows.length) {
                body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No saved budget projection found for the current month yet — use "Calculate &amp; Save Next Month\'s Budget" above first.</td></tr>';
                return;
            }

            body.innerHTML = data.rows.map(r => `
                <tr>
                    <td>${r.category_name}</td>
                    <td>${r.projected.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td>${r.actual.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td>
                        <span class="badge ${r.utilization_percent > 100 ? 'bg-danger' : 'bg-success'}">
                            ${r.utilization_percent.toFixed(0)}%
                        </span>
                    </td>
                </tr>
            `).join('');
        });

        initializeForecastChart();

        (function () {
            var url = '{{ route('Budge_Projection.data.budget_vs_actual') }}';
            var monthInput = document.getElementById('bvaMonth');
            var fmt = function (n) { return Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }); };
            var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
            var card = function (label, value, cls, hint) {
                return '<div class="col-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">' + label +
                    '</div><div class="fs-5 fw-semibold ' + (cls || '') + '">' + value + '</div>' +
                    (hint ? '<div class="small text-muted">' + hint + '</div>' : '') + '</div></div>';
            };

            function shift(delta) {
                var p = monthInput.value.split('-').map(Number);
                var d = new Date(p[0], p[1] - 1 + delta, 1);
                var v = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                if ((monthInput.min && v < monthInput.min) || (monthInput.max && v > monthInput.max)) return;
                monthInput.value = v;
                load();
            }

            async function load() {
                var body = document.getElementById('bvaBody');
                body.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Loading…</td></tr>';
                var res = await fetch(url + '?month=' + encodeURIComponent(monthInput.value), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { body.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Could not load this month.</td></tr>'; return; }
                var d = await res.json();

                document.getElementById('bvaSource').textContent = d.budget_source === 'saved'
                    ? 'Budget: saved projection for ' + d.label
                    : 'Budget: what the model predicted before ' + d.label + ' began (no saved budget)';

                var pct = d.actual_percent_of_income;
                var pctCls = pct === null ? '' : (pct > 70 ? 'text-danger' : 'text-success');
                document.getElementById('bvaCards').innerHTML =
                    card('Salary' + (d.income_source === 'estimated' ? ' (est.)' : ''), fmt(d.income)) +
                    card('70% limit', fmt(d.limit), '', 'DPS/FD counted: ' + fmt(d.installments_counted) + ' of ' + fmt(d.installments)) +
                    card('Budget / Actual', fmt(d.total_budget) + ' / ' + fmt(d.total_actual), d.total_actual > d.total_budget ? 'text-danger' : 'text-success') +
                    card('Spent of salary', pct === null ? '—' : pct + '%', pctCls, 'expenses + counted DPS/FD');

                var advice = [];
                if (d.over_limit > 0) {
                    advice.push('<div class="alert alert-danger small mb-2"><strong>' + fmt(d.over_limit) + ' over the 70% limit' +
                        (d.is_current ? ' so far' : '') + '.</strong> To get back under it, cut: ' +
                        (d.cuts.length ? d.cuts.map(function (c) { return esc(c.name) + ' <strong>' + fmt(c.amount) + '</strong> <span class="text-muted">(' + c.rule + ')</span>'; }).join(', ')
                            : 'savings/DPS lines alone exceed the limit — raise income or lower installments.') + '</div>');
                } else if (d.income > 0) {
                    advice.push('<div class="alert alert-success small mb-2">Within the 70% limit — ' + fmt(d.limit - d.total_actual) +
                        ' to spare' + (d.is_current ? ' for the rest of the month' : '') + '.</div>');
                }
                var repeat = d.rows.filter(function (r) { return r.overruns >= 3 && r.rule !== 'SAVINGS'; });
                if (repeat.length) {
                    advice.push('<div class="alert alert-warning small mb-2"><strong>Repeat overspending</strong> (beat the model budget in 3+ of the last 6 months): ' +
                        repeat.map(function (r) { return esc(r.name) + ' (' + r.overruns + '/6)'; }).join(', ') +
                        '. Set a fixed weekly cap for these or move them to a separate account.</div>');
                }
                var unusual = d.rows.filter(function (r) { return r.unusual; });
                if (unusual.length) {
                    advice.push('<div class="alert alert-info small mb-2"><strong>Unusual this month</strong> (above the model\'s 80% expected range): ' +
                        unusual.map(function (r) { return esc(r.name) + ' ' + fmt(r.actual) + ' vs ≤' + fmt(r.expected_hi); }).join(', ') +
                        '. Check whether these were one-offs or a new normal.</div>');
                }
                if (d.accuracy !== null) {
                    advice.push('<div class="small text-muted mb-2">Model back-test: total forecast was off by ±' + d.accuracy +
                        '% on average over the previous 6 months; models and weights are re-fitted every month.</div>');
                }
                document.getElementById('bvaAdvice').innerHTML = advice.join('');

                if (!d.rows.length) {
                    body.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No expenses or budget for ' + esc(d.label) + '.</td></tr>';
                    return;
                }
                body.innerHTML = d.rows.map(function (r) {
                    var used = r.used_percent;
                    var bar = used === null ? '<span class="badge bg-secondary">no budget</span>'
                        : '<div class="progress" style="height:18px" role="progressbar" aria-valuenow="' + used + '" aria-valuemin="0" aria-valuemax="100">' +
                          '<div class="progress-bar ' + (used > 100 ? 'bg-danger' : used > 80 ? 'bg-warning' : 'bg-success') +
                          '" style="width:' + Math.min(used, 100) + '%">' + used + '%</div></div>';
                    return '<tr><td>' + esc(r.name) + ' <span class="badge bg-light text-dark border">' + r.rule + '</span>' +
                        (r.unusual ? ' <span class="badge bg-danger" title="Above the 80% expected range (' + fmt(r.expected_lo) + '–' + fmt(r.expected_hi) + ')">unusual</span>' : '') + '</td>' +
                        '<td class="text-end">' + fmt(r.budget) + '</td>' +
                        '<td class="text-end">' + fmt(r.actual) + '</td>' +
                        '<td class="text-end ' + (r.gap < 0 ? 'text-danger fw-semibold' : 'text-success') + '">' + (r.gap < 0 ? '−' : '+') + fmt(Math.abs(r.gap)) + '</td>' +
                        '<td>' + bar + '</td></tr>';
                }).join('');
            }

            document.getElementById('budgetVsActualBtn').addEventListener('click', function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('budgetVsActualModal')).show();
                load();
            });
            monthInput.addEventListener('change', load);
            document.getElementById('bvaPrev').addEventListener('click', function () { shift(-1); });
            document.getElementById('bvaNext').addEventListener('click', function () { shift(1); });
        })();
    </script>
</x-backend.layouts.master>