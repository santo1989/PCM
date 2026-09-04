<x-backend.layouts.master>

    <x-slot name="pageTitle">
        Dashboard
    </x-slot>

    <div class="container-fluid pt-4">

        <div class="gradient-header mb-4">
            <h2 class="mb-1">Welcome back{{ auth()->user()->name ? ', ' . auth()->user()->name : '' }}</h2>
            <div class="text-muted small">Here's how {{ date('F', mktime(0, 0, 0, $currentMonth, 1)) }} {{ $currentYear }} is looking so far.</div>
        </div>

        @include('backend.reports.partials.report_nav')

        <div class="card mb-4 no-print">
            <div class="card-body">
                <form method="GET" action="{{ route('home') }}" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ $startDate }}"
                            min="{{ $minDataDate }}" max="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ $endDate }}"
                            min="{{ $minDataDate }}" max="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-6 d-flex flex-wrap gap-2 justify-content-md-end">
                        <button type="submit" class="btn btn-outline-info">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="{{ route('home') }}" class="btn btn-outline-danger">
                            <i class="fas fa-rotate-right"></i> Reset
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print / PDF
                        </button>
                        <button type="button" id="refreshKpisBtn" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                        <span class="small text-muted align-self-center" id="kpisUpdatedAt"></span>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">
                            "Yearly Monthly Data" below shows the full calendar year the End Date falls in
                            ({{ $currentYear }}); the "Monthly Income &amp; Expense" and "Category ways Monthly" tabs
                            use the End Date's specific month ({{ date('F', mktime(0, 0, 0, $currentMonth, 1)) }}).
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @php
            $monthIncomeTotal = (float) $thisMonthtotalIncome;
            $monthExpenseTotal = (float) $thisMonthExpense->sum('totalExpense');
            $monthNet = $monthIncomeTotal - $monthExpenseTotal;
            $savingsRate = $monthIncomeTotal > 0 ? ($monthNet / $monthIncomeTotal) * 100 : 0;
        @endphp

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card grad-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small opacity-75">This Month Income</div>
                            <div class="fs-4 fw-bold" id="kpiMonthIncome">{{ number_format($monthIncomeTotal, 2) }}</div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card grad-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small opacity-75">This Month Expense</div>
                            <div class="fs-4 fw-bold" id="kpiMonthExpense">{{ number_format($monthExpenseTotal, 2) }}</div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-cart-fill"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card grad-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small opacity-75">Net This Month</div>
                            <div class="fs-4 fw-bold" id="kpiMonthNet">{{ number_format($monthNet, 2) }}</div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card grad-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small opacity-75">Savings Rate</div>
                            <div class="fs-4 fw-bold" id="kpiSavingsRate">{{ number_format($savingsRate, 1) }}%</div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-piggy-bank-fill"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Health widget: populated on load and refreshed via /home/data/kpis polling -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-heart-pulse"></i> Financial Health</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3 text-center border-end">
                        <div class="small text-muted">Current Cash Balance</div>
                        <div class="fs-3 fw-bold" id="kpiBalance">—</div>
                    </div>
                    <div class="col-md-5 border-end">
                        <div class="small text-muted mb-2">Top 3 Spending Categories</div>
                        <div id="kpiTopCategories">
                            <div class="text-muted small">Loading…</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted mb-2"><i class="bi bi-lightbulb"></i> Tip of the Day</div>
                        <div id="kpiTip" class="small fst-italic">Loading…</div>
                    </div>
                </div>
            </div>
        </div>

        <x-backend.insights-panel :insights="$insights ?? []" title="This Month's Insights" />

        <ul class="nav nav-tabs" id="contractTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="imports_tab_data" data-bs-toggle="tab" href="#imports_data">
                    Yearly Monthly Data
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="exports_tab_data" data-bs-toggle="tab" href="#exports_data">
                    Monthly Income & Expense
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="CategoryMonthlyData" data-bs-toggle="tab" href="#CategoryMonthly">Category ways
                    Monthly</a>
            </li>
        </ul>

        <div class="tab-content" id="contractTabsContent">
            <!-- Imports Tab -->
            <div class="tab-pane fade show active" id="imports_data">
                <div class="row justify-content-center pt-4">

                    <!-- Yearly Monthly Data Start -->
                    <div class="col-md-12">
                        <h2><strong class="text-center"> Last 12 Month Income & Expense With 50% Needs, 30% Wants,
                                20% Savings Rule </strong>
                        </h2>
                        {{-- monthlyData is composed in AppServiceProvider via a View::composer --}}

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

                        <!-- Monthly Income & Expense Detail Modals -->
                        @foreach ($monthlyData as $month => $data)
                            <!-- Income Details Modal for {{ date('F', mktime(0, 0, 0, $month, 1)) }} -->
                            <div class="modal fade" id="monthIncomeDetailsModal_m{{ $month }}" tabindex="-1"
                                aria-labelledby="incomeDetailsModalLabel{{ $month }}" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title" id="incomeDetailsModalLabel{{ $month }}">
                                                <i class="fas fa-money-bill-wave me-2"></i>Income Details for {{ date('F', mktime(0, 0, 0, $month, 1)) }} {{ $currentYear }}
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover table-striped">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Category</th>
                                                            <th class="text-end">Total Income</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php
                                                            $categoryIncomes = [];
                                                            foreach ($categories as $category) {
                                                                $amount = App\Models\ExpenseCalculation::where('types', 'INCOME')
                                                                    ->where('category_id', $category->id)
                                                                    ->whereMonth('date', $month)
                                                                    ->whereYear('date', $currentYear)
                                                                    ->sum('amount');
                                                                if ($amount != 0) {
                                                                    $categoryIncomes[] = [
                                                                        'name' => $category->name,
                                                                        'amount' => $amount,
                                                                    ];
                                                                }
                                                            }
                                                            usort($categoryIncomes, fn($a, $b) => $b['amount'] <=> $a['amount']);
                                                        @endphp

                                                        @foreach ($categoryIncomes as $categoryIncome)
                                                            <tr>
                                                                <td>{{ $categoryIncome['name'] }}</td>
                                                                <td class="text-end fw-semibold text-success">
                                                                    {{ number_format($categoryIncome['amount'], 2) }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                    <tfoot class="table-primary">
                                                        <tr>
                                                            <td class="fw-bold">Total</td>
                                                            <td class="text-end fw-bold">
                                                                {{ number_format($data['income'], 2) }}
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Expense Details Modal for {{ date('F', mktime(0, 0, 0, $month, 1)) }} -->
                            <div class="modal fade" id="monthExpenseDetailsModal_m{{ $month }}" tabindex="-1"
                                aria-labelledby="expenseDetailsModalLabel{{ $month }}" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-warning text-dark">
                                            <h5 class="modal-title" id="expenseDetailsModalLabel{{ $month }}">
                                                <i class="fas fa-shopping-cart me-2"></i>Expense Details for {{ date('F', mktime(0, 0, 0, $month, 1)) }} {{ $currentYear }}
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover table-striped">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Category</th>
                                                            <th class="text-end">Total Expense</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php
                                                            $categoryExpenses = [];
                                                            foreach ($categories as $category) {
                                                                $amount = App\Models\ExpenseCalculation::where('types', 'EXPENSE')
                                                                    ->where('category_id', $category->id)
                                                                    ->whereMonth('date', $month)
                                                                    ->whereYear('date', $currentYear)
                                                                    ->sum('amount');
                                                                if ($amount != 0) {
                                                                    $categoryExpenses[] = [
                                                                        'name' => $category->name,
                                                                        'amount' => $amount,
                                                                    ];
                                                                }
                                                            }
                                                            usort($categoryExpenses, fn($a, $b) => $b['amount'] <=> $a['amount']);
                                                        @endphp

                                                        @foreach ($categoryExpenses as $categoryExpense)
                                                            <tr>
                                                                <td>{{ $categoryExpense['name'] }}</td>
                                                                <td class="text-end fw-semibold text-danger">
                                                                    {{ number_format($categoryExpense['amount'], 2) }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                    <tfoot class="table-warning">
                                                        <tr>
                                                            <td class="fw-bold">Total</td>
                                                            <td class="text-end fw-bold">
                                                                {{ number_format($data['expense'], 2) }}
                                                            </td>
                                                        </tr>
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
                <!-- Yearly Monthly Data End -->

            </div>

            <!-- Exports Tab -->
            <div class="tab-pane fade" id="exports_data">
                <div class="row justify-content-center pt-4">

                    {{-- Exports tab values (thisMonthIncome, thisMonthExpense, thisYearIncome, thisYearExpense, etc.) are provided by View::composer in AppServiceProvider --}}
                    <div class="col-md-6">
                        <h4>{{ date('F') }} Net Income</h4>
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
                                    <td>{{ number_format($thisMonthtotalIncome, 2) }}</td>
                                    <td>
                                        @php
                                            $needs = $thisMonthtotalIncome * 0.5;
                                        @endphp
                                        {{ number_format($needs, 2) }}
                                    </td>
                                    <td>
                                        @php
                                            $wants = $thisMonthtotalIncome * 0.3;
                                        @endphp
                                        {{ number_format($wants, 2) }}
                                    </td>
                                    <td>
                                        @php
                                            $savings = $thisMonthtotalIncome * 0.2;
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
                                    <td> {{ number_format($thisMonthtotalIncome - $thisMonthExpense->sum('totalExpense'), 2) }}
                                    </td>
                                    <td>{{ number_format($needs - $thisMonthneeds, 2) }}</td>
                                    <td>{{ number_format($wants - $thisMonthwants, 2) }}</td>
                                    <td>{{ number_format($savings - $thisMonthsavings, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h4>{{ date('Y') }} Net Income</h4>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Total Income</th>
                                    <th>Total Expense</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ number_format($thisYeartotalIncome, 2) }}</td>
                                    <td>{{ number_format($thisYearExpense->sum('totalExpenseYear'), 2) }}</td>
                                </tr>
                                <tr class="bg-success">
                                    <td colspan="2">Net Income:
                                        {{ number_format($thisYeartotalIncome - $thisYearExpense->sum('totalExpenseYear'), 2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="CategoryMonthly">
                <div class="row justify-content-center mt-5">

                    <div class="col-md-3">
                        <h4>Category ways Monthly Income</h4>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($thisMonthIncome as $item)
                                    <tr>
                                        <td>{{ $categoryMap[$item->category_id] ?? 'Unknown' }}</td>
                                        <td class="bg-info">{{ number_format($item->totalIncome, 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-success">
                                    <td colspan="3">Total Income: {{ number_format($thisMonthIncome->sum('totalIncome'), 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-3">
                        <h4>Category ways Monthly Expense</h4>
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
                                        <td>{{ $categoryMap[$item->category_id] ?? 'Unknown' }}</td>
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
                        <h4>Category ways {{ date('Y') }} Total Income</h4>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($thisYearIncome as $item)
                                    <tr>
                                        <td>{{ $categoryMap[$item->category_id] ?? 'Unknown' }}</td>
                                        <td class="bg-info">{{ number_format($item->totalIncomeYear, 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-success">
                                    <td colspan="3">Total Income: {{ number_format($thisYearIncome->sum('totalIncomeYear'), 2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-3">
                        <h4>Category ways {{ date('Y') }} Total Expense</h4>
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
                                        <td>{{ $categoryMap[$item->category_id] ?? 'Unknown' }}</td>
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
    </div>

    <script>
        async function refreshHomeKpis() {
            try {
                const res = await fetch('{{ route('home.data.kpis') }}');
                const data = await res.json();

                const fmt = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const net = data.month_income - data.month_expense;
                const savingsRate = data.month_income > 0 ? (net / data.month_income) * 100 : 0;

                document.getElementById('kpiMonthIncome').textContent = fmt(data.month_income);
                document.getElementById('kpiMonthExpense').textContent = fmt(data.month_expense);
                document.getElementById('kpiMonthNet').textContent = fmt(net);
                document.getElementById('kpiSavingsRate').textContent = savingsRate.toFixed(1) + '%';
                document.getElementById('kpiBalance').textContent = fmt(data.balance);
                document.getElementById('kpiTip').textContent = data.tip_of_the_day;

                const topCategoriesEl = document.getElementById('kpiTopCategories');
                if (data.top_categories && data.top_categories.length) {
                    topCategoriesEl.innerHTML = data.top_categories.map(c => `
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small">${c.name}</span>
                            <span class="small fw-bold">${fmt(c.amount)} <span class="text-muted">(${c.percent.toFixed(0)}%)</span></span>
                        </div>
                    `).join('');
                } else {
                    topCategoriesEl.innerHTML = '<div class="text-muted small">No expenses recorded yet this month.</div>';
                }

                document.getElementById('kpisUpdatedAt').textContent = 'Updated ' + new Date(data.generated_at).toLocaleTimeString();
            } catch (e) {
                console.warn('Could not refresh KPIs', e);
            }
        }

        document.getElementById('refreshKpisBtn').addEventListener('click', refreshHomeKpis);
        refreshHomeKpis();
        setInterval(refreshHomeKpis, 30000);
    </script>

</x-backend.layouts.master>
