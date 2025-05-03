<x-backend.layouts.master>
    <x-slot name="title">
        Projection Report
    </x-slot>
    <div class="container">
        <div class="row justify-content-center pt-4">
            <div class="col-md-2">
                <a href="{{ route('Budge_Projection') }}" class="btn btn-sm btn-outline-danger">Budge Projection</a>
            </div>

            <div class="col-md-2">
                <a href="{{ route('Yearly_report') }}" class="btn btn-sm btn-outline-danger">Yearly Report</a>

            </div>
            <div class="col-md-2">
                <a href="{{ route('Monthly_report') }}" class="btn btn-sm btn-outline-danger">Monthly Report</a>
            </div>
            <div class="col-md-2">
                <a href="{{ route('Monthly_invest') }}" class="btn btn-sm btn-outline-danger">Monthly Investment</a>
            </div>
            <div class="col-md-2">
                <a href="{{ route('power_bi_report') }}" class="btn btn-sm btn-outline-danger">BI Report</a>
            </div>

        </div>
        <h2 class="text-center mb-4">Monthly Financial Analysis</h2>

        <!-- Date Filter Form -->
        <div class="row mb-4">
            <form method="GET" class="col-md-12">
                <div class="form-row">
                    <div class="col-md-3">
                        <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Summary Section -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4>Allocation Summary</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Total Income</th>
                                    <th>Investment (50% + 10%/yr)</th>
                                    <th>Needs (30%)</th>
                                    <th>Wants (10%)</th>
                                    <th>Future Goals (10%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ number_format($incomes->sum('amount'), 2) }}</td>
                                    <td class="bg-success text-white">
                                        {{ number_format($incomes->sum('investment'), 2) }}
                                    </td>
                                    <td>{{ number_format($incomes->sum('needs'), 2) }}</td>
                                    <td>{{ number_format($incomes->sum('wants'), 2) }}</td>
                                    <td>{{ number_format($incomes->sum('future'), 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
                                    <th>Investment</th>
                                    <th>Needs</th>
                                    <th>Wants</th>
                                    <th>Future</th>
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
                                            $totalExpense = (float) $expenses->sum('amount');
                                            echo number_format($totalExpense, 2);
                                            
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

        <!-- Chart.js Script -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    </div>


</x-backend.layouts.master>
