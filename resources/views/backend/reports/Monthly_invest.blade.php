<x-backend.layouts.master>
    <x-slot name="title">
        Projection Report
    </x-slot>
    <div class="container">

        @include('backend.reports.partials.report_nav')

        <h2 class="text-center mb-4">Monthly Financial Analysis</h2>

        <x-backend.date-range-filter :start="$startDate" :end="$endDate" :min="$minDate">
                <a href="{{ route('Monthly_invest.export_excel', ['start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn btn-outline-success flex-fill flex-md-grow-0">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </a>
                <button type="button" class="btn btn-outline-secondary flex-fill flex-md-grow-0" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print / PDF
                </button>
        </x-backend.date-range-filter>

        <x-analytics-context :start="$startDate" :end="$endDate" />

        <!-- Detailed Analysis Section -->
        <div class="row mb-4">
            <div class="col-md-2 col-6 mb-2">
                <div class="card text-center h-100">
                    <div class="card-body p-2">
                        <div class="text-muted small">Net Position</div>
                        <div class="fw-bold {{ $analysis['netPosition'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($analysis['netPosition'], 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-2">
                <div class="card text-center h-100">
                    <div class="card-body p-2">
                        <div class="text-muted small">Actual Savings Rate</div>
                        <div class="fw-bold">{{ number_format($analysis['actualSavingsRatePercent'], 1) }}%</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-2">
                <div class="card text-center h-100">
                    <div class="card-body p-2">
                        <div class="text-muted small">Investment Target</div>
                        <div class="fw-bold">{{ number_format($analysis['investmentRatePercent'], 1) }}%</div>
                        <div class="small text-muted">of salary</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card text-center h-100">
                    <div class="card-body p-2">
                        <div class="text-muted small">Total Invested (Period)</div>
                        <div class="fw-bold text-success">{{ number_format($analysis['totalInvestment'], 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card text-center h-100">
                    <div class="card-body p-2">
                        <div class="text-muted small">Biggest Expense Bucket</div>
                        <div class="fw-bold">{{ $analysis['topExpenseRule'] ?? 'N/A' }}</div>
                        <div class="small text-danger">{{ number_format($analysis['topExpenseRuleAmount'] ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 70/20/10 salary rule: target vs actual -->
        @php
            $buckets = [
                'needs' => ['Needs', 'of 70% expense: 50%'],
                'wants' => ['Wants', 'of 70% expense: 30%'],
                'installments' => ['FD / DPS installments', 'of 70% expense: 20%'],
                'savings' => ['Savings (Bank, DPS, FD)', ''],
                'invest' => ['Invest (Stocks)', ''],
            ];
            $t = $allocation['total'];
            // Over target is bad for spending (needs/wants), good for saving buckets (FD/DPS installments, savings, invest).
            $isGood = fn($key, $actual, $target) => in_array($key, ['installments', 'savings', 'invest'], true) ? $actual >= $target : $actual <= $target;
        @endphp
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="bi bi-pie-chart"></i> Salary rule: 70% expense · 20% savings · 10% invest</span>
                <span class="small text-muted">Salary in range: <strong>{{ number_format($t['salary'], 2) }}</strong></span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    @foreach ($buckets as $key => [$label, $hint])
                        @php
                            $target = $t['target'][$key];
                            $actual = $t['actual'][$key];
                            $pct = $t['salary'] > 0 ? $actual / $t['salary'] * 100 : 0;
                            $good = $isGood($key, $actual, $target);
                        @endphp
                        <div class="col-6 col-lg">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted">{{ $label }}</div>
                                <div class="small text-muted">Target {{ round($allocation['share'][$key] * 100) }}% {{ $hint ? '(' . $hint . ')' : '' }}</div>
                                <div class="fs-5 fw-bold {{ $good ? 'text-success' : 'text-danger' }}">{{ number_format($actual, 0) }}</div>
                                <div class="small">of {{ number_format($target, 0) }} · {{ number_format($pct, 1) }}% of salary</div>
                                <div class="progress mt-2" style="height: 6px;" role="progressbar" aria-label="{{ $label }} vs target"
                                    aria-valuenow="{{ $target > 0 ? min(round($actual / $target * 100), 100) : 0 }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar {{ $good ? 'bg-success' : 'bg-danger' }}"
                                        style="width: {{ $target > 0 ? max(min($actual / $target * 100, 100), 0) : 0 }}%"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 table-responsive-cards">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th class="text-end">Salary</th>
                                @foreach ($buckets as $key => [$label])
                                    <th class="text-end">{{ $label }}<div class="small text-muted fw-normal">actual / target</div></th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_merge($allocation['months'], ['total' => $t]) as $key => $row)
                                <tr class="{{ $key === 'total' ? 'fw-bold table-light' : '' }}">
                                    <td data-label="Month">{{ $row['label'] }}</td>
                                    <td data-label="Salary" class="text-end">{{ number_format($row['salary'], 0) }}</td>
                                    @foreach ($buckets as $bucket => [$label])
                                        @php
                                            $good = $isGood($bucket, $row['actual'][$bucket], $row['target'][$bucket]);
                                        @endphp
                                        <td data-label="{{ $label }}" class="text-end text-md-nowrap">
                                            <span class="{{ $row['salary'] > 0 ? ($good ? 'text-success' : 'text-danger') : '' }}">{{ number_format($row['actual'][$bucket], 0) }}</span>
                                            <span class="text-muted">/ {{ number_format($row['target'][$bucket], 0) }}</span>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted mt-2">
                    Actuals: Needs / Wants / FD-DPS installments = expense entries by 50/30/20 rule; Invest = net deposits into
                    the Investment (stocks) account; Savings = salary − all expenses − invest (what stayed in Bank/DPS/FD).
                    Green = on track: needs/wants at or under target, FD/DPS installments, savings and invest at or above it.
                </div>
            </div>
        </div>

        <!-- Detailed Breakdown -->
        <div class="row">
            <!-- Income Breakdown -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h4>Income Allocation Details</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-bordered">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Invest 10%</th>
                                    <th>Needs 35%</th>
                                    <th>Wants 21%</th>
                                    <th>FD/DPS 14%</th>
                                    <th>Savings 20%</th>
                                </tr> 
                            </thead>
                            <tbody>
                                @foreach ($incomes as $income)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($income['date'])->format('d-M-y') }}</td>
                                        <td>{{ number_format($income['amount'], 2) }}</td>
                                        <td class="bg-light">
                                            {{ number_format($income['investment'], 2) }}
                                        </td>
                                        <td>{{ number_format($income['needs'], 2) }}</td>
                                        <td>{{ number_format($income['wants'], 2) }}</td>
                                        <td>{{ number_format($income['future'], 2) }}</td>
                                        <td>{{ number_format($income['savings'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Expense Breakdown -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h4>Expense Distribution</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($expenses as $category => $items)
                                    <tr>
                                        <td>{{ ucfirst($category) }}</td>
                                        <td>{{ number_format($items->sum('amount'), 2) }}</td>
                                        <td>
                                            @php
                                                // Convert sums to floats
                                                $totalIncome = (float) $incomes->sum('amount');
                                                $expenseAmount = (float) $items->sum('amount'); // Already converted in controller

                                                // Safe percentage calculation
                                                $percentage =
                                                    $totalIncome > 0 ? ($expenseAmount / $totalIncome) * 100 : 0;
                                            @endphp
                                            {{ number_format($percentage, 2) }}%
                                        </td>
                                        @php
                                            // Calculate the total expense amount for the category
                                            $expenseAmount = (float) $items->sum('amount');
                                        @endphp
                                    </tr>
                                @endforeach
                                <tr>
                                    <td><strong>Total</strong></td>
                                   <td> <strong> 
                                        
                                        @php
                                            // Convert sums to floats
                                            // $totalExpense = (float) $expenses->sum('amount');
                                            // echo number_format($totalExpense, 2);
                                            echo number_format($totalExpenses, 2);
                                        @endphp
                                    </strong></td>

                                    <td><strong>100%</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Investment Growth Chart -->
        <!-- Investment Growth Chart Section -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4>Yearly Investment Growth</h4>
                        <p class="mb-0">10% Annual Increase on Salary Investment</p>
                    </div>
                    <div class="card-body">
                        @if (!empty($investmentGrowth['years']))
                            <canvas id="investmentChart"></canvas>
                        @else
                            <div class="alert alert-warning">
                                No investment growth data available
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Asset Allocation + Compound Growth + 4% Rule -->
        <div class="row mt-4 g-3">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-pie-chart"></i> Asset Allocation</div>
                    <div class="card-body">
                        @if (empty($analysis['assetAllocation']))
                            <div class="alert alert-info mb-0">No DPS/FD/Investment balances recorded yet.</div>
                        @else
                            <canvas id="assetAllocationChart"></canvas>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-graph-up-arrow"></i> Compound Growth Projection</div>
                    <div class="card-body">
                        <p class="small text-muted">
                            Assumes your current pace of investment continues and grows at
                            {{ number_format($analysis['assumedAnnualReturn'] * 100, 0) }}%/year — a common long-run
                            market-average <strong>assumption</strong>, not a guarantee (this app has no real market
                            valuation data to base it on).
                        </p>
                        <table class="table table-sm table-borderless mb-0">
                            @foreach ($analysis['compoundProjection'] as $years => $value)
                                <tr>
                                    <td>In {{ $years }} years</td>
                                    <td class="text-end fw-bold">{{ number_format($value, 2) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-hourglass-split"></i> Early Retirement (4% Rule)</div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            Target: 25x your current average annual expense — the portfolio size the "4% rule"
                            considers sustainable to live off indefinitely.
                        </p>
                        <div class="text-center">
                            <div class="small text-muted">Target Portfolio</div>
                            <div class="fs-5 fw-bold">{{ number_format($analysis['fourPercentTarget'], 2) }}</div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <div class="small text-muted">Estimated Time to Reach It</div>
                            <div class="fs-5 fw-bold">
                                @if ($analysis['yearsToFourPercentTarget'] !== null)
                                    {{ $analysis['yearsToFourPercentTarget'] }} years
                                @else
                                    <span class="text-muted">Not on pace within 100 years — increase investment rate</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart.js Script -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        @if (!empty($analysis['assetAllocation']))
            <script>
                new Chart(document.getElementById('assetAllocationChart').getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: @json(array_keys($analysis['assetAllocation'])),
                        datasets: [{
                            data: @json(array_values($analysis['assetAllocation'])),
                            backgroundColor: ['#667eea', '#f5576c', '#4facfe', '#fee140', '#fa709a'],
                        }],
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
                });
            </script>
        @endif
        @if (!empty($investmentGrowth['years']))
            <script>
                const ctx = document.getElementById('investmentChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($investmentGrowth['years']),
                        datasets: [{
                            label: 'Investment Amount',
                            data: @json($investmentGrowth['amounts']),
                            borderColor: '#4CAF50',
                            backgroundColor: 'rgba(76, 175, 80, 0.2)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return '৳ ' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Amount (BDT)'
                                },
                                ticks: {
                                    callback: function(value) {
                                        return '৳ ' + value.toLocaleString();
                                    }
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Year'
                                }
                            }
                        }
                    }
                });
            </script>
        @endif

        <div class="card mt-4 border-success">
            <div class="card-header bg-success text-white">
                <i class="bi bi-graph-up-arrow"></i> AI Investment Advisor
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Your Current Allocation</h6>
                        <ul>
                            @forelse ($analysis['assetAllocation'] as $rule => $value)
                                <li>{{ $rule }}: {{ number_format($value, 2) }}</li>
                            @empty
                                <li class="text-muted">No investment-like balances recorded yet.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>AI Recommendations</h6>
                        <ul>
                            @if (($analysis['assetAllocation']['INVESTMENT'] ?? 0) < ($analysis['assetAllocation']['FD'] ?? 0) * 0.5)
                                <li>Consider shifting some FD to higher-growth investments (e.g., equity funds) to boost long-term returns.</li>
                            @endif
                            @if (($analysis['assetAllocation']['DPS'] ?? 0) > ($analysis['assetAllocation']['ISLAMIC_DPS'] ?? 0) * 1.5)
                                <li>Your DPS allocation is significantly higher than Islamic DPS — you may want to balance for risk diversification.</li>
                            @endif
                            @if ($analysis['actualSavingsRatePercent'] > 20)
                                <li>Great savings rate! Consider increasing your investment contribution to accelerate wealth building.</li>
                            @endif
                            @if (empty($analysis['assetAllocation']))
                                <li class="text-muted">Once you have investment-like balances (DPS, Islamic DPS, FD, Investment), allocation advice will appear here.</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        @include('backend.reports.partials._ai_insights_panel', [
            'aiInsights' => $aiInsights,
            'title' => 'AI Insights for This Period',
            'icon' => 'bi-graph-up-arrow',
            'headerClass' => 'bg-success text-white',
        ])
    </div>


</x-backend.layouts.master>
