<x-backend.layouts.master>

    <x-slot name="pageTitle">
        Yearly Reports Dashboard
    </x-slot>

    <div class="container">

        @include('backend.reports.partials.report_nav')

        <div class="row justify-content-between align-items-center mb-3 no-print">
            <div class="col-md-6">
                <form method="GET" action="{{ route('Yearly_report') }}" class="d-flex align-items-center gap-2">
                    <label for="year" class="mb-0 fw-semibold">Year</label>
                    <select name="year" id="year" class="form-control form-select" style="max-width: 150px;"
                        onchange="this.form.submit()">
                        @forelse ($availableYears as $y)
                            <option value="{{ $y }}" {{ (int) $y === (int) $year ? 'selected' : '' }}>{{ $y }}</option>
                        @empty
                            <option value="{{ $year }}" selected>{{ $year }}</option>
                        @endforelse
                    </select>
                </form>
            </div>
            <div class="col-md-6">
                @include('backend.reports.partials.export_toolbar', [
                    'excelRoute' => 'Yearly_report.export_excel',
                    'excelParams' => ['year' => $year],
                ])
            </div>
        </div>

        <div id="printable">
            <h2 class="text-center">Yearly Report - {{ $year }}</h2>

            {{-- Detailed analysis / insights --}}
            <div class="row justify-content-center mb-4">
                <div class="col-md-2 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Total Income</div>
                            <div class="fw-bold text-success">{{ number_format($analysis['totalIncome'], 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Total Expense</div>
                            <div class="fw-bold text-danger">{{ number_format($analysis['totalExpense'], 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Net</div>
                            <div class="fw-bold {{ $analysis['netTotal'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($analysis['netTotal'], 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Avg Savings Rate</div>
                            <div class="fw-bold">{{ number_format($analysis['avgSavingsRate'], 1) }}%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Income vs {{ $analysis['prevYear'] }}</div>
                            <div class="fw-bold">
                                @if (is_null($analysis['incomeYoyChange']))
                                    <span class="text-muted">N/A</span>
                                @else
                                    <span class="{{ $analysis['incomeYoyChange'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $analysis['incomeYoyChange'] >= 0 ? '+' : '' }}{{ number_format($analysis['incomeYoyChange'], 1) }}%
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <div class="text-muted small">Expense vs {{ $analysis['prevYear'] }}</div>
                            <div class="fw-bold">
                                @if (is_null($analysis['expenseYoyChange']))
                                    <span class="text-muted">N/A</span>
                                @else
                                    <span class="{{ $analysis['expenseYoyChange'] <= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $analysis['expenseYoyChange'] >= 0 ? '+' : '' }}{{ number_format($analysis['expenseYoyChange'], 1) }}%
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <h5 class="text-center text-muted mb-3"><i class="bi bi-calendar-range"></i> Seasonal Trends</h5>
            <div class="row justify-content-center mb-4">
                <div class="col-md-3 col-6 mb-2">
                    <div class="alert alert-success mb-0 text-center py-2">
                        <div class="small">Best Income Month</div>
                        <strong>{{ $analysis['bestIncomeMonth'] ? date('F', mktime(0, 0, 0, $analysis['bestIncomeMonth'], 1)) : 'N/A' }}</strong>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="alert alert-warning mb-0 text-center py-2">
                        <div class="small">Weakest Income Month</div>
                        <strong>{{ $analysis['worstIncomeMonth'] ? date('F', mktime(0, 0, 0, $analysis['worstIncomeMonth'], 1)) : 'N/A' }}</strong>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="alert alert-danger mb-0 text-center py-2">
                        <div class="small">Highest Expense Month</div>
                        <strong>{{ $analysis['highestExpenseMonth'] ? date('F', mktime(0, 0, 0, $analysis['highestExpenseMonth'], 1)) : 'N/A' }}</strong>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="alert alert-info mb-0 text-center py-2">
                        <div class="small">Lowest Expense Month</div>
                        <strong>{{ $analysis['lowestExpenseMonth'] ? date('F', mktime(0, 0, 0, $analysis['lowestExpenseMonth'], 1)) : 'N/A' }}</strong>
                    </div>
                </div>
            </div>

            <!-- What-if slider: pure client-side arithmetic on data already on the page -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-sliders"></i> What-If Simulator</div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <label class="form-label small">Income change: <span id="whatIfIncomeLabel">0%</span></label>
                            <input type="range" class="form-range" id="whatIfIncome" min="-50" max="50" value="0" step="1">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small">Expense change: <span id="whatIfExpenseLabel">0%</span></label>
                            <input type="range" class="form-range" id="whatIfExpense" min="-50" max="50" value="0" step="1">
                        </div>
                        <div class="col-md-2 text-center">
                            <div class="small text-muted">Simulated Net</div>
                            <div class="fw-bold fs-5" id="whatIfNet">{{ number_format($analysis['netTotal'], 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 pt-2 pb-2">
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <div>
                        <canvas id="budgetChart" width="800" height="300"></canvas>
                    </div>

                    <script>
                        var monthlyData = @json($monthlyData);

                        var incomeData = Object.values(monthlyData).map(data => data.income);
                        var expenseData = Object.values(monthlyData).map(data => data.expense);
                        var netData = Object.values(monthlyData).map(data => data.net);

                        // What-if simulator: pure arithmetic on totals already rendered on this page.
                        (function () {
                            var baseIncome = {{ $analysis['totalIncome'] }};
                            var baseExpense = {{ $analysis['totalExpense'] }};
                            var incomeSlider = document.getElementById('whatIfIncome');
                            var expenseSlider = document.getElementById('whatIfExpense');
                            var incomeLabel = document.getElementById('whatIfIncomeLabel');
                            var expenseLabel = document.getElementById('whatIfExpenseLabel');
                            var netOutput = document.getElementById('whatIfNet');

                            function recalc() {
                                var incomePct = parseInt(incomeSlider.value, 10);
                                var expensePct = parseInt(expenseSlider.value, 10);
                                incomeLabel.textContent = (incomePct >= 0 ? '+' : '') + incomePct + '%';
                                expenseLabel.textContent = (expensePct >= 0 ? '+' : '') + expensePct + '%';

                                var simulatedIncome = baseIncome * (1 + incomePct / 100);
                                var simulatedExpense = baseExpense * (1 + expensePct / 100);
                                var net = simulatedIncome - simulatedExpense;

                                netOutput.textContent = net.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                netOutput.className = 'fw-bold fs-5 ' + (net >= 0 ? 'text-success' : 'text-danger');
                            }

                            incomeSlider.addEventListener('input', recalc);
                            expenseSlider.addEventListener('input', recalc);
                        })();

                        var ctx = document.getElementById('budgetChart').getContext('2d');
                        var budgetChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August',
                                    'September', 'October', 'November', 'December'
                                ],
                                datasets: [{
                                        label: 'Income',
                                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                                        data: incomeData
                                    },
                                    {
                                        label: 'Expenses',
                                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                                        data: expenseData
                                    },
                                    {
                                        label: 'Net',
                                        type: 'line',
                                        borderColor: '#0d6efd',
                                        backgroundColor: '#0d6efd',
                                        fill: false,
                                        data: netData
                                    }
                                ]
                            },
                            options: {
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    </script>
                </div>
            </div>

            {{-- Yearly Monthly Data Start --}}
            <h4 class="text-center">Month-by-Month Breakdown</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-hover text-center">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Income / Expense</th>
                            <th>Balance</th>
                            <th>Needs</th>
                            <th>Balance</th>
                            <th>Wants</th>
                            <th>Balance</th>
                            <th>Savings</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monthlyData as $month => $data)
                            <tr>
                                <td rowspan="2">
                                    {{ date('F', mktime(0, 0, 0, $month, 1)) }} </td>
                                <td>
                                    @if ($data['income'] > 0)
                                        <button type="button"
                                            class="btn btn-link p-0 text-decoration-none text-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#monthIncomeDetailsModal_m{{ $month }}">
                                            {{ number_format($data['income'], 2) }}
                                        </button>
                                    @else
                                        {{ number_format($data['income'], 2) }}
                                    @endif
                                </td>
                                <td rowspan="2" class="bg-info">
                                    {{ number_format($data['income'] - $data['expense'], 2) }}</td>
                                <td>{{ number_format($data['needs'], 2) }}</td>
                                <td rowspan="2" class="bg-info">
                                    {{ number_format($data['needs'] - $data['thisMonthneeds'], 2) }}</td>
                                <td>{{ number_format($data['wants'], 2) }}</td>
                                <td rowspan="2" class="bg-info">
                                    {{ number_format($data['wants'] - $data['thisMonthwants'], 2) }}</td>
                                <td>{{ number_format($data['savings'], 2) }}</td>
                                <td rowspan="2" class="bg-info">
                                    {{ number_format($data['savings'] - $data['thisMonthsavings'], 2) }}</td>
                            </tr>
                            <tr>
                                <td class="bg-danger">
                                    @if ($data['expense'] > 0)
                                        <button type="button"
                                            class="btn btn-link p-0 text-decoration-none text-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#monthExpenseDetailsModal_m{{ $month }}">
                                            {{ number_format($data['expense'], 2) }}
                                        </button>
                                    @else
                                        {{ number_format($data['expense'], 2) }}
                                    @endif
                                </td>
                                <td>{{ number_format($data['thisMonthneeds'], 2) }}</td>
                                <td>{{ number_format($data['thisMonthwants'], 2) }}</td>
                                <td>{{ number_format($data['thisMonthsavings'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-success">
                            <th rowspan="2">Total</th>
                            <td>{{ number_format(array_sum(array_column($monthlyData, 'income')), 2) }}</td>
                            <td rowspan="2">
                                {{ number_format(array_sum(array_column($monthlyData, 'income')) - array_sum(array_column($monthlyData, 'expense')), 2) }}
                            </td>
                            <td>{{ number_format(array_sum(array_column($monthlyData, 'needs')), 2) }}</td>
                            <td rowspan="2">
                                {{ number_format(array_sum(array_column($monthlyData, 'needs')) - array_sum(array_column($monthlyData, 'thisMonthneeds')), 2) }}
                            </td>
                            <td>{{ number_format(array_sum(array_column($monthlyData, 'wants')), 2) }}</td>
                            <td rowspan="2">
                                {{ number_format(array_sum(array_column($monthlyData, 'wants')) - array_sum(array_column($monthlyData, 'thisMonthwants')), 2) }}
                            </td>
                            <td>{{ number_format(array_sum(array_column($monthlyData, 'savings')), 2) }}</td>
                            <td rowspan="2">
                                {{ number_format(array_sum(array_column($monthlyData, 'savings')) - array_sum(array_column($monthlyData, 'thisMonthsavings')), 2) }}
                            </td>
                        </tr>
                        <tr class="bg-danger">
                            <td>{{ number_format(array_sum(array_column($monthlyData, 'expense')), 2) }}</td>
                            <td>{{ number_format(array_sum(array_column($monthlyData, 'thisMonthneeds')), 2) }}</td>
                            <td>{{ number_format(array_sum(array_column($monthlyData, 'thisMonthwants')), 2) }}</td>
                            <td>{{ number_format(array_sum(array_column($monthlyData, 'thisMonthsavings')), 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @php
                $categories = App\Models\Category::all();
            @endphp

            @foreach ($monthlyData as $month => $data)
                <div class="modal fade" id="monthIncomeDetailsModal_m{{ $month }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Income Details for {{ date('F', mktime(0, 0, 0, $month, 1)) }}, {{ $year }}</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead class="table-light">
                                            <tr><th>Category</th><th class="text-end">Total Income</th></tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $categoryIncomes = [];
                                                foreach ($categories as $category) {
                                                    $amount = App\Models\ExpenseCalculation::where('types', 'INCOME')->where('category_id', $category->id)->whereYear('date', $year)->whereMonth('date', $month)->sum('amount');
                                                    if ($amount != 0) {
                                                        $categoryIncomes[] = ['name' => $category->name, 'amount' => $amount];
                                                    }
                                                }
                                                usort($categoryIncomes, fn($a, $b) => $b['amount'] <=> $a['amount']);
                                            @endphp
                                            @foreach ($categoryIncomes as $categoryIncome)
                                                <tr>
                                                    <td>{{ $categoryIncome['name'] }}</td>
                                                    <td class="text-end fw-semibold text-success">{{ number_format($categoryIncome['amount'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="table-primary">
                                            <tr><td class="fw-bold">Total</td><td class="text-end fw-bold">{{ number_format($data['income'], 2) }}</td></tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="monthExpenseDetailsModal_m{{ $month }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-warning text-dark">
                                <h5 class="modal-title">Expense Details for {{ date('F', mktime(0, 0, 0, $month, 1)) }}, {{ $year }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead class="table-light">
                                            <tr><th>Category</th><th class="text-end">Total Expense</th></tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $categoryExpenses = [];
                                                foreach ($categories as $category) {
                                                    $amount = App\Models\ExpenseCalculation::where('types', 'EXPENSE')->where('category_id', $category->id)->whereYear('date', $year)->whereMonth('date', $month)->sum('amount');
                                                    if ($amount != 0) {
                                                        $categoryExpenses[] = ['name' => $category->name, 'amount' => $amount];
                                                    }
                                                }
                                                usort($categoryExpenses, fn($a, $b) => $b['amount'] <=> $a['amount']);
                                            @endphp
                                            @foreach ($categoryExpenses as $categoryExpense)
                                                <tr>
                                                    <td>{{ $categoryExpense['name'] }}</td>
                                                    <td class="text-end fw-semibold text-danger">{{ number_format($categoryExpense['amount'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="table-warning">
                                            <tr><td class="fw-bold">Total</td><td class="text-end fw-bold">{{ number_format($data['expense'], 2) }}</td></tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-backend.layouts.master>
