<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Cash
    </x-slot>

    <x-backend.layouts.elements.message :message="session('message')" />
    <x-backend.layouts.elements.errors />

    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>


    <section class="content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-md-12 col-sm-12 col-xl-12">
                    <div class="card mb-3 no-print">
                        <div class="card-body">
                            <form method="GET" action="{{ route('expenseCalculations.index') }}" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Types</label>
                                    @php
                                        $types = App\Models\ExpenseCalculation::select('types')
                                            ->distinct()
                                            ->get();
                                    @endphp
                                    <select class="form-select select2" name="types[]" id="types"
                                        multiple data-placeholder="All Types">
                                        @foreach ($types as $type)
                                            <option value="{{ $type->types }}"
                                                {{ in_array($type->types, $search_types) ? 'selected' : '' }}>
                                                {{ $type->types }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Category</label>
                                    <select class="form-select select2" name="category_id[]"
                                        id="category_id" multiple data-placeholder="All Categories">
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                {{ in_array((string) $category->id, array_map('strval', $search_category_id), true) ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Start Date</label>
                                    <input type="date" name="entry_date_start"
                                        id="entry_date_start" class="form-control"
                                        value="{{ $search_entry_date_start }}"
                                        min="{{ $minDataDate }}" max="{{ now()->toDateString() }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">End Date</label>
                                    <input type="date" name="entry_date_end" id="entry_date_end"
                                        class="form-control" value="{{ $search_entry_date_end }}"
                                        min="{{ $minDataDate }}" max="{{ now()->toDateString() }}">
                                </div>

                                <div class="col-md-3 d-flex flex-wrap gap-2 justify-content-md-end">
                                    <button class="btn btn-outline-info" onclick="validateForm()">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <a href="{{ route('expenseCalculations.index') }}" class="btn btn-outline-danger">
                                        <i class="fas fa-rotate-right"></i> Reset
                                    </a>
                                    <a href="{{ route('expenseCalculations.index', array_merge(request()->query(), ['export_format' => 'xlsx'])) }}"
                                        class="btn btn-outline-success">
                                        <i class="fas fa-file-excel"></i> Excel
                                    </a>
                                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                                        <i class="bi bi-printer"></i> Print / PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row pt-1">
                        <div class="col-md-2 col-sm-12">
                            <a type="button" class="btn btn-outline-dark" data-bs-toggle="modal"
                                data-bs-target="#CashEntryModal"><i class="fas fa-plus" aria-hidden="true"></i>
                                Create
                            </a>

                            @php
                                $this_month_income = App\Models\ExpenseCalculation::where('types', 'INCOME')
                                    ->whereMonth('date', date('m'))
                                    ->whereYear('date', date('Y'))
                                    ->sum('amount');
                                // dd($this_month_income);
                                $this_month_expense = App\Models\ExpenseCalculation::where('types', 'EXPENSE')
                                    ->whereMonth('date', date('m'))
                                    ->whereYear('date', date('Y'))
                                    ->sum('amount');
                                // dd($this_month_expense);
                                $all_time_income = App\Models\ExpenseCalculation::where('types', 'INCOME')->sum(
                                    'amount',
                                );
                                // dd($all_time_income);
                                $all_time_expense = App\Models\ExpenseCalculation::where('types', 'EXPENSE')->sum(
                                    'amount',
                                );
                                // dd($all_time_expense);
                            @endphp
                        </div>
                        <div class="col-md-2 col-sm-12">
                            <h5 class="text-center">
                                <!--modal trigger button for show categories wise this month income-->
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                                    data-bs-target="#categoriesWiseIncomeModal">
                                    This Month Income: {{ $this_month_income }}
                                </button>
                                <!-- Modal -->
                                <div class="modal fade" id="categoriesWiseIncomeModal" tabindex="-1"
                                    aria-labelledby="categoriesWiseIncomeModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-fullscreen">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="categoriesWiseIncomeModalLabel">Categories
                                                    Wise This Month Income</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6 col-sm-12">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th>Category</th>
                                                                    <th>Income Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @php
                                                                    $categories = App\Models\Category::all();
                                                                    $categoryIncomes = [];
                                                                    foreach ($categories as $category) {
                                                                        $amount = App\Models\ExpenseCalculation::where(
                                                                            'types',
                                                                            'income',
                                                                        )
                                                                            ->where('category_id', $category->id)
                                                                            ->whereMonth('date', date('m'))
                                                                             ->whereYear('date', date('Y'))
                                                                             ->sum('amount');
                                                                         if ($amount != 0) {
                                                                             $categoryIncomes[] = [
                                                                                 'name' => $category->name,
                                                                                 'amount' => $amount,
                                                                             ];
                                                                         }
                                                                    }
                                                                    usort($categoryIncomes, function ($a, $b) {
                                                                        return $b['amount'] <=> $a['amount'];
                                                                    });
                                                                @endphp
                                                                @foreach ($categoryIncomes as $categoryIncome)
                                                                    <tr>
                                                                        <td>{{ $categoryIncome['name'] }}</td>
                                                                        <td>{{ $categoryIncome['amount'] }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-6 col-sm-12">
                                                        <!-- Bar Chart for This Month Income -->
                                                        <div style="margin-bottom: 30px;">
                                                            <h6 class="text-center">Category Wise Income</h6>
                                                            <canvas id="thisMonthIncomeBarChart"></canvas>
                                                        </div>

                                                        <!-- Pie Chart for This Month Income Distribution -->
                                                        {{-- <div>
                                                            <h6 class="text-center">Income Distribution %</h6>
                                                            <canvas id="thisMonthIncomePieChart"></canvas>
                                                        </div> --}}

                                                        <script>
                                                            @php
                                                                $categories = App\Models\Category::all();
                                                                $chartData = [];
                                                                $totalIncome = 0;

                                                                foreach ($categories as $category) {
                                                                     $amount = App\Models\ExpenseCalculation::where('types', 'INCOME')->where('category_id', $category->id)->whereMonth('date', date('m'))->whereYear('date', date('Y'))->sum('amount');
                                                                     if ($amount != 0) {
                                                                         $chartData[] = ['name' => $category->name, 'amount' => $amount];
                                                                         $totalIncome += $amount;
                                                                     }
                                                                }
                                                                usort($chartData, fn($a, $b) => $b['amount'] <=> $a['amount']);
                                                                $incomeLabels = array_column($chartData, 'name');
                                                                $incomeData = array_column($chartData, 'amount');
                                                            @endphp

                                                            // This Month Income Bar Chart
                                                            var ctxBar = document.getElementById('thisMonthIncomeBarChart').getContext('2d');
                                                            var thisMonthIncomeBarChart = new Chart(ctxBar, {
                                                                type: 'bar',
                                                                data: {
                                                                    labels: {!! json_encode($incomeLabels) !!},
                                                                    datasets: [{
                                                                        label: 'Income Amount',
                                                                        data: {!! json_encode($incomeData) !!},
                                                                        backgroundColor: 'rgba(75, 192, 192, 0.8)',
                                                                        borderColor: 'rgba(75, 192, 192, 1)',
                                                                        borderWidth: 1
                                                                    }]
                                                                },
                                                                options: {
                                                                    responsive: true,
                                                                    maintainAspectRatio: true,
                                                                    scales: {
                                                                        y: {
                                                                            beginAtZero: true
                                                                        }
                                                                    },
                                                                    plugins: {
                                                                        legend: {
                                                                            display: false
                                                                        }
                                                                    }
                                                                }
                                                            });

                                                            // // This Month Income Pie Chart
                                                            // var ctxPie = document.getElementById('thisMonthIncomePieChart').getContext('2d');
                                                            // var thisMonthIncomePieChart = new Chart(ctxPie, {
                                                            //     type: 'doughnut',
                                                            //     data: {
                                                            //         labels: {!! json_encode($incomeLabels) !!},
                                                            //         datasets: [{
                                                            //             data: {!! json_encode($incomeData) !!},
                                                            //             backgroundColor: [
                                                            //                 'rgba(255, 99, 132, 0.8)',
                                                            //                 'rgba(54, 162, 235, 0.8)',
                                                            //                 'rgba(255, 206, 86, 0.8)',
                                                            //                 'rgba(75, 192, 192, 0.8)',
                                                            //                 'rgba(153, 102, 255, 0.8)',
                                                            //                 'rgba(255, 159, 64, 0.8)',
                                                            //                 'rgba(199, 199, 199, 0.8)',
                                                            //                 'rgba(83, 102, 255, 0.8)'
                                                            //             ],
                                                            //             borderColor: '#fff',
                                                            //             borderWidth: 2
                                                            //         }]
                                                            //     },
                                                            //     options: {
                                                            //         responsive: true,
                                                            //         maintainAspectRatio: true,
                                                            //         plugins: {
                                                            //             legend: {
                                                            //                 position: 'bottom',
                                                            //                 labels: {
                                                            //                     font: {
                                                            //                         size: 10
                                                            //                     }
                                                            //                 }
                                                            //             }
                                                            //         }
                                                            //     }
                                                            // });
                                                        </script>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Modal End -->

                            </h5>

                        </div>
                        <div class="col-md-2 col-sm-12">
                            <h5 class="text-center">
                                <!--modal trigger button for show categories wise this month expense-->
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                                    data-bs-target="#categoriesWiseExpenseModal">
                                    This Month Expense: {{ $this_month_expense }}
                                </button>

                                <!-- Modal -->
                                <div class="modal fade" id="categoriesWiseExpenseModal" tabindex="-1"
                                    aria-labelledby="categoriesWiseExpenseModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-fullscreen">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="categoriesWiseExpenseModalLabel">Categories
                                                    Wise This Month Expense</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6 col-sm-12">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th>Category</th>
                                                                    <th>Expense Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @php
                                                                    $categories = App\Models\Category::all();
                                                                    $categoryExpenses = [];
                                                                    foreach ($categories as $category) {
                                                                        $amount = App\Models\ExpenseCalculation::where(
                                                                            'types',
                                                                            'expense',
                                                                        )
                                                                            ->where('category_id', $category->id)
                                                                            ->whereMonth('date', date('m'))
                                                                             ->whereYear('date', date('Y'))
                                                                             ->sum('amount');
                                                                         if ($amount != 0) {
                                                                             $categoryExpenses[] = [
                                                                                 'name' => $category->name,
                                                                                 'amount' => $amount,
                                                                             ];
                                                                         }
                                                                    }
                                                                    usort($categoryExpenses, function ($a, $b) {
                                                                        return $b['amount'] <=> $a['amount'];
                                                                    });
                                                                @endphp
                                                                @foreach ($categoryExpenses as $categoryExpense)
                                                                    <tr>
                                                                        <td>{{ $categoryExpense['name'] }}</td>
                                                                        <td>{{ $categoryExpense['amount'] }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-6 col-sm-12">
                                                        <!-- Bar Chart for This Month Expense -->
                                                        <div style="margin-bottom: 30px;">
                                                            <h6 class="text-center">Category Wise Expense</h6>
                                                            <canvas id="thisMonthExpenseBarChart"></canvas>
                                                        </div>

                                                        <!-- Pie Chart for This Month Expense Distribution -->
                                                        {{-- <div>
                                                            <h6 class="text-center">Expense Distribution %</h6>
                                                            <canvas id="thisMonthExpensePieChart"></canvas>
                                                        </div> --}}

                                                        <script>
                                                            @php
                                                                $categories = App\Models\Category::all();
                                                                $chartData = [];

                                                                foreach ($categories as $category) {
                                                                     $amount = App\Models\ExpenseCalculation::where('types', 'EXPENSE')->where('category_id', $category->id)->whereMonth('date', date('m'))->whereYear('date', date('Y'))->sum('amount');
                                                                     if ($amount != 0) {
                                                                         $chartData[] = ['name' => $category->name, 'amount' => $amount];
                                                                     }
                                                                }
                                                                usort($chartData, fn($a, $b) => $b['amount'] <=> $a['amount']);
                                                                $expenseLabels = array_column($chartData, 'name');
                                                                $expenseData = array_column($chartData, 'amount');
                                                            @endphp

                                                            // This Month Expense Bar Chart
                                                            var ctxExpenseBar = document.getElementById('thisMonthExpenseBarChart').getContext('2d');
                                                            var thisMonthExpenseBarChart = new Chart(ctxExpenseBar, {
                                                                type: 'bar',
                                                                data: {
                                                                    labels: {!! json_encode($expenseLabels) !!},
                                                                    datasets: [{
                                                                        label: 'Expense Amount',
                                                                        data: {!! json_encode($expenseData) !!},
                                                                        backgroundColor: 'rgba(255, 99, 132, 0.8)',
                                                                        borderColor: 'rgba(255, 99, 132, 1)',
                                                                        borderWidth: 1
                                                                    }]
                                                                },
                                                                options: {
                                                                    responsive: true,
                                                                    maintainAspectRatio: true,
                                                                    scales: {
                                                                        y: {
                                                                            beginAtZero: true
                                                                        }
                                                                    },
                                                                    plugins: {
                                                                        legend: {
                                                                            display: false
                                                                        }
                                                                    }
                                                                }
                                                            });

                                                            // // This Month Expense Pie Chart
                                                            // var ctxExpensePie = document.getElementById('thisMonthExpensePieChart').getContext('2d');
                                                            // var thisMonthExpensePieChart = new Chart(ctxExpensePie, {
                                                            //     type: 'doughnut',
                                                            //     data: {
                                                            //         labels: {!! json_encode($expenseLabels) !!},
                                                            //         datasets: [{
                                                            //             data: {!! json_encode($expenseData) !!},
                                                            //             backgroundColor: [
                                                            //                 'rgba(255, 99, 132, 0.8)',
                                                            //                 'rgba(54, 162, 235, 0.8)',
                                                            //                 'rgba(255, 206, 86, 0.8)',
                                                            //                 'rgba(75, 192, 192, 0.8)',
                                                            //                 'rgba(153, 102, 255, 0.8)',
                                                            //                 'rgba(255, 159, 64, 0.8)',
                                                            //                 'rgba(199, 199, 199, 0.8)',
                                                            //                 'rgba(83, 102, 255, 0.8)'
                                                            //             ],
                                                            //             borderColor: '#fff',
                                                            //             borderWidth: 2
                                                            //         }]
                                                            //     },
                                                            //     options: {
                                                            //         responsive: true,
                                                            //         maintainAspectRatio: true,
                                                            //         plugins: {
                                                            //             legend: {
                                                            //                 position: 'bottom',
                                                            //                 labels: {
                                                            //                     font: {
                                                            //                         size: 10
                                                            //                     }
                                                            //                 }
                                                            //             }
                                                            //         }
                                                            //     }
                                                            // });
                                                        </script>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="modal-footer"> </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Modal End -->


                            </h5>


                        </div>
                        <div class="col-md-2 col-sm-12">
                            <h5 class="text-center">
                                <!--modal trigger button for show categories wise all month income-->
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                                    data-bs-target="#categoriesWiseAllTimeIncomeModal">
                                    Total Income: {{ $all_time_income }}
                                </button>
                                <!-- Modal -->
                                <div class="modal fade" id="categoriesWiseAllTimeIncomeModal" tabindex="-1"
                                    aria-labelledby="categoriesWiseAllTimeIncomeModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-fullscreen">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="categoriesWiseAllTimeIncomeModalLabel">
                                                    Categories Wise All Time Income</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6 col-sm-12">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th>Category</th>
                                                                    <th>Income Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @php
                                                                    $categories = App\Models\Category::all();
                                                                    $allTimeIncomes = [];
                                                                    foreach ($categories as $category) {
                                                                        $amount = App\Models\ExpenseCalculation::where(
                                                                            'types',
                                                                            'income',
                                                                        )
                                                                            ->where('category_id', $category->id)
                                                                             ->sum('amount');
                                                                         if ($amount != 0) {
                                                                             $allTimeIncomes[] = [
                                                                                 'name' => $category->name,
                                                                                 'amount' => $amount,
                                                                             ];
                                                                         }
                                                                    }
                                                                    usort($allTimeIncomes, function ($a, $b) {
                                                                        return $b['amount'] <=> $a['amount'];
                                                                    });
                                                                @endphp
                                                                @foreach ($allTimeIncomes as $allTimeIncome)
                                                                    <tr>
                                                                        <td>{{ $allTimeIncome['name'] }}</td>
                                                                        <td>{{ $allTimeIncome['amount'] }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-6 col-sm-12">
                                                        <!-- Bar Chart for All Time Income -->
                                                        <div style="margin-bottom: 30px;">
                                                            <h6 class="text-center">Category Wise Total Income</h6>
                                                            <canvas id="allTimeIncomeBarChart"></canvas>
                                                        </div>

                                                        <!-- Pie Chart for All Time Income Distribution -->
                                                        {{-- <div>
                                                            <h6 class="text-center">Total Income Distribution %</h6>
                                                            <canvas id="allTimeIncomePieChart"></canvas>
                                                        </div> --}}

                                                        <script>
                                                            @php
                                                                $categories = App\Models\Category::all();
                                                                $chartData = [];

                                                                foreach ($categories as $category) {
                                                                     $amount = App\Models\ExpenseCalculation::where('types', 'INCOME')->where('category_id', $category->id)->sum('amount');
                                                                     if ($amount != 0) {
                                                                         $chartData[] = ['name' => $category->name, 'amount' => $amount];
                                                                     }
                                                                }
                                                                usort($chartData, fn($a, $b) => $b['amount'] <=> $a['amount']);
                                                                $allTimeIncomeLabels = array_column($chartData, 'name');
                                                                $allTimeIncomeData = array_column($chartData, 'amount');
                                                            @endphp

                                                            // All Time Income Bar Chart
                                                            var ctxAllTimeIncomeBar = document.getElementById('allTimeIncomeBarChart').getContext('2d');
                                                            var allTimeIncomeBarChart = new Chart(ctxAllTimeIncomeBar, {
                                                                type: 'bar',
                                                                data: {
                                                                    labels: {!! json_encode($allTimeIncomeLabels) !!},
                                                                    datasets: [{
                                                                        label: 'Total Income Amount',
                                                                        data: {!! json_encode($allTimeIncomeData) !!},
                                                                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                                                                        borderColor: 'rgba(54, 162, 235, 1)',
                                                                        borderWidth: 1
                                                                    }]
                                                                },
                                                                options: {
                                                                    responsive: true,
                                                                    maintainAspectRatio: true,
                                                                    scales: {
                                                                        y: {
                                                                            beginAtZero: true
                                                                        }
                                                                    },
                                                                    plugins: {
                                                                        legend: {
                                                                            display: false
                                                                        }
                                                                    }
                                                                }
                                                            });

                                                            // // All Time Income Pie Chart
                                                            // var ctxAllTimeIncomePie = document.getElementById('allTimeIncomePieChart').getContext('2d');
                                                            // var allTimeIncomePieChart = new Chart(ctxAllTimeIncomePie, {
                                                            //     type: 'doughnut',
                                                            //     data: {
                                                            //         labels: {!! json_encode($allTimeIncomeLabels) !!},
                                                            //         datasets: [{
                                                            //             data: {!! json_encode($allTimeIncomeData) !!},
                                                            //             backgroundColor: [
                                                            //                 'rgba(255, 99, 132, 0.8)',
                                                            //                 'rgba(54, 162, 235, 0.8)',
                                                            //                 'rgba(255, 206, 86, 0.8)',
                                                            //                 'rgba(75, 192, 192, 0.8)',
                                                            //                 'rgba(153, 102, 255, 0.8)',
                                                            //                 'rgba(255, 159, 64, 0.8)',
                                                            //                 'rgba(199, 199, 199, 0.8)',
                                                            //                 'rgba(83, 102, 255, 0.8)'
                                                            //             ],
                                                            //             borderColor: '#fff',
                                                            //             borderWidth: 2
                                                            //         }]
                                                            //     },
                                                            //     options: {
                                                            //         responsive: true,
                                                            //         maintainAspectRatio: true,
                                                            //         plugins: {
                                                            //             legend: {
                                                            //                 position: 'bottom',
                                                            //                 labels: {
                                                            //                     font: {
                                                            //                         size: 10
                                                            //                     }
                                                            //                 }
                                                            //             }
                                                            //         }
                                                            //     }
                                                            // });
                                                        </script>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Modal End -->

                            </h5>

                        </div>
                        <div class="col-md-2 col-sm-12">
                            <h5 class="text-center">
                                <!--modal trigger button for show categories wise all time expense-->
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                                    data-bs-target="#categoriesWiseAllTimeExpenseModal">
                                    Total Expense: {{ $all_time_expense }}
                                </button>

                                <!-- Modal -->
                                <div class="modal fade" id="categoriesWiseAllTimeExpenseModal" tabindex="-1"
                                    aria-labelledby="categoriesWiseAllTimeExpenseModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-fullscreen">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="categoriesWiseAllTimeExpenseModalLabel">
                                                    Categories Wise Total Expense</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6 col-sm-12">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th>Category</th>
                                                                    <th>Expense Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @php
                                                                    $categories = App\Models\Category::all();
                                                                    $allTimeExpenses = [];
                                                                    foreach ($categories as $category) {
                                                                        $amount = App\Models\ExpenseCalculation::where(
                                                                            'types',
                                                                            'expense',
                                                                        )
                                                                            ->where('category_id', $category->id)
                                                                             ->sum('amount');
                                                                         if ($amount != 0) {
                                                                             $allTimeExpenses[] = [
                                                                                 'name' => $category->name,
                                                                                 'amount' => $amount,
                                                                             ];
                                                                         }
                                                                    }
                                                                    usort($allTimeExpenses, function ($a, $b) {
                                                                        return $b['amount'] <=> $a['amount'];
                                                                    });
                                                                @endphp
                                                                @foreach ($allTimeExpenses as $allTimeExpense)
                                                                    <tr>
                                                                        <td>{{ $allTimeExpense['name'] }}</td>
                                                                        <td>{{ $allTimeExpense['amount'] }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-6 col-sm-12">
                                                        <!-- Bar Chart for All Time Expense -->
                                                        <div style="margin-bottom: 30px;">
                                                            <h6 class="text-center">Category Wise Total Expense</h6>
                                                            <canvas id="allTimeExpenseBarChart"></canvas>
                                                        </div>

                                                        <!-- Pie Chart for All Time Expense Distribution -->
                                                        {{-- <div>
                                                            <h6 class="text-center">Total Expense Distribution %</h6>
                                                            <canvas id="allTimeExpensePieChart"></canvas>
                                                        </div> --}}

                                                        <script>
                                                            @php
                                                                $categories = App\Models\Category::all();
                                                                $chartData = [];

                                                                foreach ($categories as $category) {
                                                                     $amount = App\Models\ExpenseCalculation::where('types', 'EXPENSE')->where('category_id', $category->id)->sum('amount');
                                                                     if ($amount != 0) {
                                                                         $chartData[] = ['name' => $category->name, 'amount' => $amount];
                                                                     }
                                                                }
                                                                usort($chartData, fn($a, $b) => $b['amount'] <=> $a['amount']);
                                                                $allTimeExpenseLabels = array_column($chartData, 'name');
                                                                $allTimeExpenseData = array_column($chartData, 'amount');
                                                            @endphp

                                                            // All Time Expense Bar Chart
                                                            var ctxAllTimeExpenseBar = document.getElementById('allTimeExpenseBarChart').getContext('2d');
                                                            var allTimeExpenseBarChart = new Chart(ctxAllTimeExpenseBar, {
                                                                type: 'bar',
                                                                data: {
                                                                    labels: {!! json_encode($allTimeExpenseLabels) !!},
                                                                    datasets: [{
                                                                        label: 'Total Expense Amount',
                                                                        data: {!! json_encode($allTimeExpenseData) !!},
                                                                        backgroundColor: 'rgba(255, 159, 64, 0.8)',
                                                                        borderColor: 'rgba(255, 159, 64, 1)',
                                                                        borderWidth: 1
                                                                    }]
                                                                },
                                                                options: {
                                                                    responsive: true,
                                                                    maintainAspectRatio: true,
                                                                    scales: {
                                                                        y: {
                                                                            beginAtZero: true
                                                                        }
                                                                    },
                                                                    plugins: {
                                                                        legend: {
                                                                            display: false
                                                                        }
                                                                    }
                                                                }
                                                            });

                                                            // // All Time Expense Pie Chart
                                                            // var ctxAllTimeExpensePie = document.getElementById('allTimeExpensePieChart').getContext('2d');
                                                            // var allTimeExpensePieChart = new Chart(ctxAllTimeExpensePie, {
                                                            //     type: 'doughnut',
                                                            //     data: {
                                                            //         labels: {!! json_encode($allTimeExpenseLabels) !!},
                                                            //         datasets: [{
                                                            //             data: {!! json_encode($allTimeExpenseData) !!},
                                                            //             backgroundColor: [
                                                            //                 'rgba(255, 99, 132, 0.8)',
                                                            //                 'rgba(54, 162, 235, 0.8)',
                                                            //                 'rgba(255, 206, 86, 0.8)',
                                                            //                 'rgba(75, 192, 192, 0.8)',
                                                            //                 'rgba(153, 102, 255, 0.8)',
                                                            //                 'rgba(255, 159, 64, 0.8)',
                                                            //                 'rgba(199, 199, 199, 0.8)',
                                                            //                 'rgba(83, 102, 255, 0.8)'
                                                            //             ],
                                                            //             borderColor: '#fff',
                                                            //             borderWidth: 2
                                                            //         }]
                                                            //     },
                                                            //     options: {
                                                            //         responsive: true,
                                                            //         maintainAspectRatio: true,
                                                            //         plugins: {
                                                            //             legend: {
                                                            //                 position: 'bottom',
                                                            //                 labels: {
                                                            //                     font: {
                                                            //                         size: 10
                                                            //                     }
                                                            //                 }
                                                            //             }
                                                            //         }
                                                            //     }
                                                            // });
                                                        </script>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="modal-footer"> </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Modal End -->


                            </h5>


                        </div>
                    </div>


                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <!--  Table goes here id="datatablesSimple" -->
                    <table class="table table-bordered table-hover table-responsive-cards" id="myTable">
                        <thead>
                            <tr>

                                <th>Sl#</th>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Category Name</th>
                                <th>Category Types</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expenseCalculations as $cash)
                                <tr>

                                    <td data-label="Sl#">
                                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal"
                                            data-bs-target="#exampleModalCenter{{ $cash->id }}">
                                            {{ $cash->id }}
                                        </button>
                                    </td>
                                    <td data-label="Date">{{ $cash->date ? \Carbon\Carbon::parse($cash->date)->format('d-M-Y') : '' }}
                                    </td>
                                    <td data-label="Name">{{ $cash->name }}</td>
                                    <td data-label="Category Name">{{ optional($cash->category)->name }}</td>
                                    <td data-label="Category Types">{{ $cash->types }}</td>
                                    <td data-label="Amount">{{ $cash->amount }}</td>
                                    <td data-label="Actions">

                                        <a type="button" class="btn btn-outline-info" data-bs-toggle="modal"
                                            data-bs-target="#CashEditModal{{ $cash->id }}">Edit</a>

                                        <button type="button"
                                            onclick="confirmDelete('{{ route('expenseCalculations.destroy', ['cash' => $cash->id]) }}')"
                                            class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>

                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="alert alert-danger">
                                            No Data Found
                                        </div>
                                    </td>
                                </tr>
                            @endforelse

                        </tbody>
                    </table>

                    {{ $expenseCalculations->links() }}







                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->


            <!-- /.card -->
        </div>
        <!-- /.col -->
        </div>
        <!-- /.row -->
        </div>
        <!-- /.container-fluid -->
    </section>

    <!--  start model for Data Entry -->




    <div class="modal fade" id="CashEntryModal" data-bs-backdrop="static" tabindex="-1"
        aria-labelledby="CashEntryModal" aria-hidden="true">
        <div class="modal-dialog modal-lg" style="min-width:90%;">
            <div class="modal-content" style="background-color: #fff; min-width:90%;">
                <div class="modal-header" style="background: var(--grad-primary); color: #fff; min-width:90%;">
                    <h5 class="modal-title text-center" id="CashEntryModal"> Data Entry</h5>
                    <button type="button" class="btn btn-light btn-close" data-bs-dismiss="modal"
                        aria-label="Close" style="background-color: white; border-color: white; color: black;"
                        onmouseover="this.classList.add('btn-danger')"
                        onmouseout="this.classList.remove('btn-danger')"></button>

                </div>
                <div class="modal-body" style="background: #fff; min-width:90%;">
                    <div class="container-fluid justify-content-center">
                        <div class="row justify-content-between">
                            <div class="col-md-12">
                                <div class="p-4 p-md-5">

                                    <form method="POST" action="{{ route('expenseCalculations.store') }}">
                                        @csrf

                                        <!-- row-1: always-present first entry -->
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-2">
                                                <x-backend.form.input name="date[]" type="date" label="Date"
                                                    value="{{ date('Y-m-d') }}" />
                                            </div>
                                            <div class="col-md-2">
                                                <x-backend.form.select name="category_id[]" label="Category"
                                                    class="select2"
                                                    :options="$categories->pluck('name', 'id')" />
                                            </div>
                                            <div class="col-md-2">
                                                <x-backend.form.autocomplete-input name="name[]" label="Name"
                                                    model="App\Models\ExpenseCalculation" column="name" />
                                            </div>
                                            <div class="col-md-2">
                                                <x-backend.form.input name="amount[]" type="number" step="0.01"
                                                    label="Amount" />
                                            </div>
                                            <div class="col-md-2">
                                                <x-backend.form.select name="types[]" label="HandCash Types"
                                                    class="select2" :options="config('finance.handcash_types')" />
                                            </div>
                                            <div class="col-md-2">
                                                <x-backend.form.select name="rules[]" label="Cash Rules"
                                                    class="select2"
                                                    :options="config('finance.handcash_rules')" />
                                            </div>
                                        </div>
                                        <!-- row-1 end -->

                                        <!--  Dynamic input fields -->
                                        <div id="dynamic-fields-container"></div>

                                        <!--  Add and remove buttons -->
                                        <div class="mt-3">
                                            <button type="button" id="add-field-btn"
                                                class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i>
                                                Add Field</button>
                                            <button type="button" id="remove-field-btn"
                                                class="btn btn-sm btn-outline-danger"><i class="bi bi-dash-lg"></i>
                                                Remove Field</button>
                                        </div>

                                        <!--  Submit button -->
                                        <div class="mt-4 text-center">
                                            <button type="submit" class="btn btn-primary px-4">Create</button>
                                        </div>
                                    </form>

                                    <script>
                                        function addField() {
                                            var container = document.getElementById('dynamic-fields-container');

                                            var fieldTemplate = `
            <div class="row g-2 align-items-end mt-2">
                <div class="col-md-2">
                    <input type="date" name="date[]" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="col-md-2">
                    <select name="category_id[]" class="form-select select2">
                        <option value="">Select Category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" list="dl_App_Models_ExpenseCalculation_name" name="name[]" class="form-control" autocomplete="off" placeholder="Name">
                </div>
                <div class="col-md-2">
                    <input type="number" step="0.01" name="amount[]" class="form-control" placeholder="Amount">
                </div>
                <div class="col-md-2">
                    <select class="form-select select2" name="types[]">
                        @foreach (config('finance.handcash_types') as $key => $value)
                            <option value="{{ $key }}">{{ $value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select select2" name="rules[]">
                        @foreach (config('finance.handcash_rules') as $key => $value)
                            <option value="{{ $key }}">{{ $value }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        `;

                                            container.insertAdjacentHTML('beforeend', fieldTemplate);

                                            // The global Select2 init already ran on page load, before this
                                            // row existed — initialize it on just the new row's selects.
                                            var newRow = container.lastElementChild;
                                            if (window.jQuery && jQuery.fn.select2) {
                                                jQuery(newRow).find('.select2').select2({
                                                    theme: 'bootstrap-5',
                                                    width: '100%'
                                                });
                                            }
                                        }

                                        function removeField() {
                                            var container = document.getElementById('dynamic-fields-container');
                                            var fields = container.getElementsByClassName('row');

                                            if (fields.length > 0) {
                                                fields[fields.length - 1].remove();
                                            }
                                        }

                                        document.getElementById('add-field-btn').addEventListener('click', addField);
                                        document.getElementById('remove-field-btn').addEventListener('click', removeField);
                                    </script>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!--  end model for Data Entry -->

    <!--  start model for Data Edit -->
    @foreach ($expenseCalculations as $cash)
        <div class="modal fade" id="CashEditModal{{ $cash->id }}" tabindex="-1"
            aria-labelledby="CashEditModal{{ $cash->id }}Label" aria-hidden="true" data-bs-backdrop="static"
            data-bs-keyboard="false">
            <div class="modal-dialog modal-xl" style="min-width:90%;">
                <div class="modal-content" style="background-color: #fff; min-width:90%;">
                    <div class="modal-header" style="background: var(--grad-primary); color: #fff; min-width:90%;">
                        <h5 class="modal-title text-center" id="CashEditModalLabel">Data Edit</h5>
                        <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Close</button>
                    </div>
                    <div class="modal-body" style="background: #fff; min-width:90%;">
                        <div class="container-fluid justify-content-center">
                            <div class="row justify-content-between">
                                <div class="col-md-12">
                                    <div class="p-4 p-md-5">

                                        <form method="POST" action="{{ route('expenseCalculations.update', $cash) }}">
                                            @csrf
                                            @method('PUT')

                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-3">
                                                    <x-backend.form.input name="date" :id="'edit_date_' . $cash->id"
                                                        type="date" label="Date" :value="$cash->date" />
                                                </div>
                                                <div class="col-md-3">
                                                    <x-backend.form.select name="category_id"
                                                        :id="'edit_category_id_' . $cash->id" label="Category"
                                                        class="select2"
                                                        :options="$categories->pluck('name', 'id')"
                                                        :selected="$cash->category_id" />
                                                </div>
                                                <div class="col-md-3">
                                                    <x-backend.form.autocomplete-input name="name" label="Name"
                                                        model="App\Models\ExpenseCalculation" column="name"
                                                        :value="$cash->name" />
                                                </div>
                                                <div class="col-md-3">
                                                    <x-backend.form.input name="amount"
                                                        :id="'edit_amount_' . $cash->id" type="number" step="0.01"
                                                        label="Amount" :value="$cash->amount" />
                                                </div>
                                            </div>

                                            <div class="mt-4 text-center">
                                                <button type="submit" class="btn btn-primary px-4">Save</button>
                                            </div>
                                        </form>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!--  end model for Data Edit -->

    <!--  start model for Data details -->

    @foreach ($expenseCalculations as $cash)
        <div class="modal fade" id="exampleModalCenter{{ $cash->id }}" tabindex="-1" data-bs-backdrop="static"
            aria-labelledby="registerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" style="min-width:90%;">
                <div class="modal-content" style="background-color: #fff; min-width:90%;">
                    <div class="modal-header" style="background: var(--grad-primary); color: #fff; min-width:90%;">
                        <h5 class="modal-title text-center" id="registerModalLabel">{{ $cash->name }}</h5>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">X</button>
                    </div>
                    <div class="modal-body" style="background: #fff; min-width:90%;">
                        <div class="container-fluid">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <x-backend.form.input name="date" type="date" label="Date" :value="$cash->date"
                                        readonly />
                                </div>
                                <div class="col-md-3">
                                    <x-backend.form.input name="category" type="text" label="Category"
                                        :value="optional($cash->category)->name" readonly />
                                </div>
                                <div class="col-md-3">
                                    <x-backend.form.input name="name" type="text" label="Name" :value="$cash->name"
                                        readonly />
                                </div>
                                <div class="col-md-3">
                                    <x-backend.form.input name="amount" type="number" label="Amount"
                                        :value="$cash->amount" readonly />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <a type="button" class="btn btn-outline-info" data-bs-toggle="modal"
                            data-bs-target="#CashEditModal{{ $cash->id }}" data-bs-dismiss="modal">Edit</a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!--  End model for Data details -->
    <script>
        function validateForm() {
            var incCategory = document.getElementById("types").value;
            var entryDateStart = document.getElementById("entry_date_start").value;
            var entryDateEnd = document.getElementById("entry_date_end").value;

            if (incCategory === "" && entryDateStart === "" && entryDateEnd === "") {
                Swal.fire({
                    title: "Warning",
                    text: "Please fill in at least one field to search",
                    icon: "warning",
                    confirmButtonColor: "#3085d6",
                    confirmButtonText: "OK"
                });
            } else {
                // Submit the form or perform further processing
            }
        }

        function confirmDelete(url) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.action = url;
                    form.innerHTML = `@csrf @method('delete')`;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>


</x-backend.layouts.master>
