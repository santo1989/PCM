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
                            <td>{{ $data['income'] }}</td>
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
                            <td class="bg-danger">{{ $data['expense'] }}</td>
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
            </table>




        </div>
        {{-- Yearly Monthly Data End --}}

    </div>
    </div>
</x-backend.layouts.master>
