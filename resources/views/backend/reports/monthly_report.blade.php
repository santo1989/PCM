<x-backend.layouts.master>

    <x-slot name="pageTitle">
        Month Dashboard
    </x-slot>

    <div class="container">

        @include('backend.reports.partials.report_nav')

        <div class="row text-center p-2 no-print">
            <form action="{{ route('Monthly_report') }}" method="get" id="Monthly_report">
                @csrf
                <table class="table table-borderless table-responsive text-center text-dark font-weight-bold">
                    <tr>
                        <div class="col-sm-4">
                            <td>Start Date</td>
                            <td>
                                <input type="date" name="start_date" id="start_date" class="form-control" required
                                    value="{{ $startDate ?? '' }}">
                            </td>
                        </div>
                        <div class="col-sm-4">
                            <td>End Date</td>
                            <td>
                                <input type="date" name="end_date" id="end_date" class="form-control" required
                                    value="{{ $endDate ?? '' }}">
                            </td>
                        </div>
                        <div class="col-sm-4">
                            <td>
                                <button type="submit" class="btn btn-lg btn-outline-secondary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="{{ route('Monthly_report') }}" class="btn btn-outline-danger">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </a>
                            </td>
                        </div>
                    </tr>
                </table>
            </form>
        </div>

        @include('backend.reports.partials.export_toolbar', [
            'excelRoute' => 'Monthly_report.export_excel',
            'excelParams' => ['start_date' => $startDate, 'end_date' => $endDate],
        ])

        <div id="printable" class="row justify-content-center">
            <div class="col-md-12 text-center">
                <h2>Monthly Report</h2>
                <h3>{{ $startDate }} - {{ $endDate }}</h3>
            </div>

            {{-- Detailed analysis / insights --}}
            <div class="row justify-content-center pt-2 pb-2">
                <div class="col-md-2 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Savings Rate</div>
                            <div class="fw-bold">{{ number_format($analysis['savingsRate'], 1) }}%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Income vs Previous {{ $analysis['periodDays'] }} Days</div>
                            <div class="fw-bold">
                                @if (is_null($analysis['incomeChange']))
                                    <span class="text-muted">N/A</span>
                                @else
                                    <span class="{{ $analysis['incomeChange'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $analysis['incomeChange'] >= 0 ? '+' : '' }}{{ number_format($analysis['incomeChange'], 1) }}%
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Expense vs Previous {{ $analysis['periodDays'] }} Days</div>
                            <div class="fw-bold">
                                @if (is_null($analysis['expenseChange']))
                                    <span class="text-muted">N/A</span>
                                @else
                                    <span class="{{ $analysis['expenseChange'] <= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $analysis['expenseChange'] >= 0 ? '+' : '' }}{{ number_format($analysis['expenseChange'], 1) }}%
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Top Expense Category</div>
                            <div class="fw-bold">
                                @if ($analysis['topExpenseCategory'])
                                    {{ optional(App\Models\Category::find($analysis['topExpenseCategory']->category_id))->name }}
                                    <div class="small text-danger">{{ number_format($analysis['topExpenseCategory']->totalExpense, 2) }}</div>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Top Income Category</div>
                            <div class="fw-bold">
                                @if ($analysis['topIncomeCategory'])
                                    {{ optional(App\Models\Category::find($analysis['topIncomeCategory']->category_id))->name }}
                                    <div class="small text-success">{{ number_format($analysis['topIncomeCategory']->totalIncome, 2) }}</div>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card text-center h-100 border-success">
                        <div class="card-body p-2">
                            <div class="text-muted small"><i class="bi bi-piggy-bank"></i> Top Saving Category</div>
                            <div class="fw-bold">
                                @if ($analysis['topSavingCategory'])
                                    {{ $analysis['topSavingCategory'] }}
                                    <div class="small text-success">-{{ number_format($analysis['topSavingAmount'], 2) }} vs. previous period</div>
                                @else
                                    <span class="text-muted">No category decreased this period</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Same Period Last Year</div>
                            <div class="fw-bold small">
                                Income: {{ number_format($analysis['lastYearPeriodIncome'], 2) }}<br>
                                Expense: {{ number_format($analysis['lastYearPeriodExpense'], 2) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center pt-2 pb-2">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><i class="bi bi-bar-chart-line"></i> Daily Spending Pattern (by Day of Week)</div>
                        <div class="card-body">
                            <canvas id="dayOfWeekChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><i class="bi bi-arrow-left-right"></i> vs. Same Period Last Year</div>
                        <div class="card-body">
                            <canvas id="yoyChart" height="150"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center pt-4">
                <div class="col-md-6">
                    <h4>{{ $startDate }} - {{ $endDate }} (Selected Period)</h4>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th></th>
                                <th></th>
                                <th>Needs</th>
                                <th>Wants</th>
                                <th>Savings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Total Income</th>
                                <td>{{ number_format($thisMonthIncome->sum('amount'), 2) }}</td>
                                <td>
                                    @php
                                        $needs = $thisMonthIncome->sum('amount') * 0.5;
                                    @endphp
                                    {{ number_format($needs, 2) }}
                                </td>
                                <td>
                                    @php
                                        $wants = $thisMonthIncome->sum('amount') * 0.3;
                                    @endphp
                                    {{ number_format($wants, 2) }}
                                </td>
                                <td>
                                    @php
                                        $savings = $thisMonthIncome->sum('amount') * 0.2;
                                    @endphp
                                    {{ number_format($savings, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <th>Total Expense</th>
                                <td> {{ number_format($thisMonthExpense->sum('totalExpense'), 2) }}</td>
                                <td>{{ number_format($thisMonthneeds, 2) }}</td>
                                <td>{{ number_format($thisMonthwants, 2) }}</td>
                                <td>{{ number_format($thisMonthsavings, 2) }}</td>
                            </tr>
                            <tr class="bg-success">
                                <th>Net Income</th>
                                <td> {{ number_format($thisMonthIncome->sum('amount') - $thisMonthExpense->sum('totalExpense'), 2) }}
                                </td>
                                <td>{{ number_format($needs - $thisMonthneeds, 2) }}</td>
                                <td>{{ number_format($wants - $thisMonthwants, 2) }}</td>
                                <td>{{ number_format($savings - $thisMonthsavings, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h4>{{ $currentYear }} Year-to-Date</h4>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Total Income</th>
                                <th>Total Expense</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ number_format($thisYearIncome->sum('amount'), 2) }}</td>
                                <td>{{ number_format($thisYearExpense->sum('totalExpenseYear'), 2) }}</td>
                            </tr>
                            <tr class="bg-success">
                                <td colspan="2">Net Income:
                                    {{ number_format($thisYearIncome->sum('amount') - $thisYearExpense->sum('totalExpenseYear'), 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="row justify-content-center mt-5">

                <div class="col-md-3">
                    <h4>Income by Category (Selected Period)</h4>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($MonthlyIncomeCategorieswise as $item)
                                <tr>
                                    <td>
                                        @php
                                            $category = App\Models\Category::find($item->category_id);
                                        @endphp
                                        {{ $category->name ?? 'Unknown' }}
                                    </td>
                                    <td class="bg-info">
                                        {{ number_format($item->totalIncome, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-success">
                                <td colspan="3">Total Income: {{ number_format($MonthlyIncomeCategorieswise->sum('totalIncome'), 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-3">
                    <h4>Expense by Category (Selected Period)</h4>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($thisMonthExpense as $item)
                                <tr>
                                    <td>
                                        @php
                                            $category = App\Models\Category::find($item->category_id);
                                        @endphp
                                        {{ $category->name ?? 'Unknown' }}
                                    </td>
                                    <td class="bg-info">{{ number_format($item->totalExpense, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-success">
                                <td colspan="3">Total Expense:
                                    {{ number_format($thisMonthExpense->sum('totalExpense'), 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="col-md-3">
                    <h4>{{ $currentYear }} Income by Category (Year)</h4>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($thisYearIncomecategory as $item)
                                <tr>
                                    <td>
                                        @php
                                            $category = App\Models\Category::find($item->category_id);
                                        @endphp
                                        {{ $category->name ?? 'Unknown' }}
                                    </td>
                                    <td class="bg-info">{{ number_format($item->totalIncomeYear, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-success">
                                <td colspan="3">Total Income: {{ number_format($thisYearIncomecategory->sum('totalIncomeYear'), 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-3">
                    <h4>{{ $currentYear }} Expense by Category (Year)</h4>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($thisYearExpense as $item)
                                <tr>
                                    <td>
                                        @php
                                            $category = App\Models\Category::find($item->category_id);
                                        @endphp
                                        {{ $category->name ?? 'Unknown' }}
                                    </td>
                                    <td class="bg-info">{{ number_format($item->totalExpenseYear, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-success">
                                <td colspan="3">Total Expense:
                                    {{ number_format($thisYearExpense->sum('totalExpenseYear'), 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        new Chart(document.getElementById('dayOfWeekChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: @json($analysis['dayOfWeekLabels']),
                datasets: [{
                    label: 'Expense',
                    data: @json($analysis['dayOfWeekData']),
                    backgroundColor: '#f5576c',
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } },
            },
        });

        new Chart(document.getElementById('yoyChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Income', 'Expense'],
                datasets: [
                    {
                        label: '{{ $startDate }} – {{ $endDate }}',
                        data: [{{ $thisMonthIncome->sum('amount') }}, {{ $thisMonthExpense->sum('totalExpense') }}],
                        backgroundColor: '#667eea',
                    },
                    {
                        label: 'Same period last year',
                        data: [{{ $analysis['lastYearPeriodIncome'] }}, {{ $analysis['lastYearPeriodExpense'] }}],
                        backgroundColor: '#4facfe',
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } },
            },
        });
    </script>

</x-backend.layouts.master>
