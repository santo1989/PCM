<x-backend.layouts.master>

    <x-slot name="pageTitle">
        Yearly Reports Dashboard
    </x-slot>


    <div class="container">
        <div class="row justify-content-center pt-4">
            <div class="col-md-3">
                <a href="{{ route('Budge_Projection') }}" class="btn btn-sm btn-outline-danger">Budge Projection</a>
            </div>

            <div class="col-md-3">
                <a href="{{ route('Yearly_report') }}" class="btn btn-sm btn-outline-danger">Yearly Report</a>

            </div>
            <div class="col-md-3">
                <a href="{{ route('Monthly_report') }}" class="btn btn-sm btn-outline-danger">Monthly Report</a>
            </div>
            <div class="col-md-3">
                <a href="{{ route('power_bi_report') }}" class="btn btn-sm btn-outline-danger">BI Report</a>
            </div>

        </div>
        <div class="row">
            <div class="col-md-12 pt-2 pb-2">

                <title>Budget Projection</title>
                <!-- Include Chart.js library -->
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <div>
                    <canvas id="budgetChart" width="800" height="300"></canvas>
                </div>

                <script>
                    // Get the data from PHP and convert it to a JavaScript object
                    var monthlyData = @json($monthlyData);

                    // Extract the income and expenses data for the graph
                    var incomeData = Object.values(monthlyData).map(data => data.income);
                    var expenseData = Object.values(monthlyData).map(data => data.expense);
                    var needsData = Object.values(monthlyData).map(data => data.needs);
                    var wantsData = Object.values(monthlyData).map(data => data.wants);
                    var savingsData = Object.values(monthlyData).map(data => data.savings);
                    var thisMonthneedsData = Object.values(monthlyData).map(data => data.thisMonthneeds);
                    var thisMonthwantsData = Object.values(monthlyData).map(data => data.thisMonthwants);
                    var thisMonthsavingsData = Object.values(monthlyData).map(data => data.thisMonthsavings);

                    // Chart.js configuration
                    var ctx = document.getElementById('budgetChart').getContext('2d');
                    var budgetChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September',
                                'October', 'November', 'December'
                            ],
                            datasets: [{
                                    label: 'Income',
                                    borderColor: 'green',
                                    backgroundColor: 'green',
                                    data: incomeData
                                },
                                {
                                    label: 'Expenses',
                                    // borderColor: 'red',
                                    bacgroundColor: 'red',
                                    color: 'red',
                                    data: expenseData
                                },
                                // {
                                //     label: 'Needs',
                                //     borderColor: 'blue',
                                //     backgroundColor: 'blue',
                                //     data: needsData
                                // },
                                //  {
                                //     label: 'This Month Needs',
                                //     borderColor: 'yellow',
                                //     backgroundColor: 'yellow',
                                //     data: thisMonthneedsData
                                // },
                                //  {
                                //     label: 'Wants',
                                //     borderColor: 'orange',
                                //     backgroundColor: 'orange',
                                //     data: wantsData
                                // },
                                // {
                                //     label: 'This Month Wants',
                                //     borderColor: 'pink',
                                //     backgroundColor: 'pink',
                                //     data: thisMonthwantsData
                                // },
                                // {
                                //     label: 'Savings',
                                //     borderColor: 'purple',
                                //     backgroundColor: 'purple',
                                //     data: savingsData
                                // },

                                // {
                                //     label: 'This Month Savings',
                                //     borderColor: 'black',
                                //     backgroundColor: 'black',
                                //     data: thisMonthsavingsData
                                // }


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

            {{-- Yearly Monthly Data Start --}}
            <h1 class="text-center">Full Yearly Report</h1>
            <table class="table table-bordered table-hover text-center">
                <thead>
                    <tr>
                        <th></th>
                        <th>Incame / Expense</th>
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
                                        {{ $data['income'] }}
                                    </button>
                                @else
                                    {{ $data['income'] }}
                                @endif
                            </td>
                            <td rowspan="2" class="bg-info">
                                {{ $data['income'] - $data['expense'] }}</td>
                            <td>{{ $data['needs'] }}</td>
                            <td rowspan="2" class="bg-info">
                                {{ $data['needs'] - $data['thisMonthneeds'] }}</td>
                            <td>{{ $data['wants'] }}</td>
                            <td rowspan="2" class="bg-info">
                                {{ $data['wants'] - $data['thisMonthwants'] }}</td>
                            <td>{{ $data['savings'] }}</td>
                            <td rowspan="2" class="bg-info">
                                {{ $data['savings'] - $data['thisMonthsavings'] }}</td>
                        </tr>
                        <tr>
                            <td class="bg-danger">
                                @if ($data['expense'] > 0)
                                    <button type="button"
                                        class="btn btn-link p-0 text-decoration-none text-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#monthExpenseDetailsModal_m{{ $month }}">
                                        {{ $data['expense'] }}
                                    </button>
                                @else
                                    {{ $data['expense'] }}
                                @endif
                            </td>
                            <td>{{ $data['thisMonthneeds'] }}</td>
                            <td>{{ $data['thisMonthwants'] }}</td>
                            <td>{{ $data['thisMonthsavings'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-success">
                        <th rowspan="2">Total</th>
                        <td>{{ array_sum(array_column($monthlyData, 'income')) }}</td>
                        <td rowspan="2">
                            {{ array_sum(array_column($monthlyData, 'income')) - array_sum(array_column($monthlyData, 'expense')) }}
                        </td>
                        <td>{{ array_sum(array_column($monthlyData, 'needs')) }}</td>
                        <td rowspan="2">
                            {{ array_sum(array_column($monthlyData, 'needs')) - array_sum(array_column($monthlyData, 'thisMonthneeds')) }}
                        </td>
                        <td>{{ array_sum(array_column($monthlyData, 'wants')) }}</td>
                        <td rowspan="2">
                            {{ array_sum(array_column($monthlyData, 'wants')) - array_sum(array_column($monthlyData, 'thisMonthwants')) }}
                        </td>
                        <td>{{ array_sum(array_column($monthlyData, 'savings')) }}</td>
                        <td rowspan="2">
                            {{ array_sum(array_column($monthlyData, 'savings')) - array_sum(array_column($monthlyData, 'thisMonthsavings')) }}
                        </td>
                    </tr>
                    <tr class="bg-danger">
                        <td>{{ array_sum(array_column($monthlyData, 'expense')) }}</td>
                        <td>{{ array_sum(array_column($monthlyData, 'thisMonthneeds')) }}</td>
                        <td>{{ array_sum(array_column($monthlyData, 'thisMonthwants')) }}</td>
                        <td>{{ array_sum(array_column($monthlyData, 'thisMonthsavings')) }}</td>
                    </tr>
                </tbody>
            @php
                $categories = App\Models\Category::all();
            @endphp

            @foreach ($monthlyData as $month => $data)
                <div class="modal fade" id="monthIncomeDetailsModal_m{{ $month }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Income Details for {{ date('F', mktime(0, 0, 0, $month, 1)) }}</h5>
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
                                                    $amount = App\Models\ExpenseCalculation::where('types', 'INCOME')->where('category_id', $category->id)->whereMonth('date', $month)->sum('amount');
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
                                <h5 class="modal-title">Expense Details for {{ date('F', mktime(0, 0, 0, $month, 1)) }}</h5>
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
                                                    $amount = App\Models\ExpenseCalculation::where('types', 'EXPENSE')->where('category_id', $category->id)->whereMonth('date', $month)->sum('amount');
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
