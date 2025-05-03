<x-backend.layouts.master>

    <x-slot name="pageTitle">
        Admin Dashboard
    </x-slot>
    {{-- <x-slot name='breadCrumb'>
                <x-backend.layouts.elements.breadcrumb>
                    <x-slot name="pageHeader"> Dashboard </x-slot>
                    <li class="breadcrumb-item active">Dashboard</li>
                </x-backend.layouts.elements.breadcrumb>
            </x-slot> --}}

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
        <div class="container mt-4">
            <ul class="nav nav-tabs" id="contractTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="imports_tab_data" data-toggle="tab" href="#imports_data">
                        Yearly Monthly Data
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="exports_tab_data" data-toggle="tab" href="#exports_data">
                        Monthly Income & Expense
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="t3" data-toggle="tab" href="#t3">T3</a>
                </li>
            </ul>

            <div class="tab-content" id="contractTabsContent">
                <!-- Imports Tab -->
                <div class="tab-pane fade show active" id="imports_data">
                    <div class="row justify-content-center pt-4">


                        {{-- Yearly Monthly Data Start --}}
                        <div class="col-md-12">
                            <h2><strong class="text-center"> Last 12 Month Income & Expense With 50% Needs, 30% Wants,
                                    20% Savings Rule </strong>
                            </h2>
                            @php
                                $currentYear = date('Y');

                                $monthlyData = [];

                                // $currentMonth = date('m');

                                for ($month = 1; $month <= 12; $month++) {
                                    $thisMonthIncome = App\Models\ExpenseCalculation::where('types', 'income')
                                        ->whereMonth('date', $month)
                                        ->whereYear('date', $currentYear)
                                        ->get();

                                    $thisMonthSalaryIncome = App\Models\ExpenseCalculation::where('types', 'income')
                                        ->where('category_id', 1)
                                        ->whereMonth('date', $month)
                                        ->whereYear('date', $currentYear)
                                        ->get();

                                    $thisMonthExpense = App\Models\ExpenseCalculation::where('types', 'expense')
                                        ->whereMonth('date', $month)
                                        ->whereYear('date', $currentYear)
                                        ->groupBy('category_id')
                                        ->select('category_id', \DB::raw('SUM(amount) as totalExpense'))
                                        ->get();

                                    $thisMonthneeds = App\Models\ExpenseCalculation::where('rules', 'needs')
                                        ->whereMonth('date', $month)
                                        ->whereYear('date', $currentYear)
                                        ->sum('amount');

                                    $thisMonthwants = App\Models\ExpenseCalculation::where('rules', 'wants')
                                        ->whereMonth('date', $month)
                                        ->whereYear('date', $currentYear)
                                        ->sum('amount');

                                    $thisMonthsavings = App\Models\ExpenseCalculation::where('rules', 'savings')
                                        ->whereMonth('date', $month)
                                        ->whereYear('date', $currentYear)
                                        ->sum('amount');

                                    $monthlyData[$month] = [
                                        'income' => $thisMonthIncome->sum('amount'),
                                        'needs' => $thisMonthSalaryIncome->sum('amount') * 0.5,
                                        'wants' => $thisMonthSalaryIncome->sum('amount') * 0.3,
                                        'savings' => $thisMonthSalaryIncome->sum('amount') * 0.2,
                                        'expense' => $thisMonthExpense->sum('totalExpense'),
                                        'thisMonthneeds' => $thisMonthneeds,
                                        'thisMonthwants' => $thisMonthwants,
                                        'thisMonthsavings' => $thisMonthsavings,
                                    ];
                                }
                            @endphp

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
                                        {{-- @if ($month === $currentMonth || $month === $currentMonth - 1 || $month === $currentMonth + 0) --}}
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
                                        {{-- @endif --}}
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
                    </div>
                    {{-- Yearly Monthly Data End --}}

                </div>

                <!-- Exports Tab -->
                <div class="tab-pane fade" id="exports_data">
                    <div class="row justify-content-center pt-4">


                        @php
                            // Get the current month and year from the selected values in the dropdowns
                            $currentMonth = date('m');
                            $currentYear = date('Y');
                            if (isset($_GET['year'])) {
                                $currentYear = $_GET['year'];
                            }
                            if (isset($_GET['month'])) {
                                $currentMonth = $_GET['month'];
                            }

                            $thisMonthIncome = App\Models\ExpenseCalculation::where('types', 'income')
                                ->whereMonth('date', $currentMonth)
                                ->whereYear('date', $currentYear)
                                ->get();

                            // $thisMonthIncome = App\Models\ExpenseCalculation::Where('types', 'income')
                            //     ->whereMonth('date', $currentMonth)
                            //     ->whereYear('date', $currentYear)
                            //     ->get();
                            // dd($thisMonthIncome);
                            $thisMonthExpense = App\Models\ExpenseCalculation::where('types', 'expense')
                                ->whereMonth('date', $currentMonth)
                                ->whereYear('date', $currentYear)
                                ->groupBy('category_id')
                                ->select('category_id', \DB::raw('SUM(amount) as totalExpense'))
                                ->get();
                            // dd($thisMonthExpense);

                            $thisMonthneeds = App\Models\ExpenseCalculation::Where('rules', 'needs')
                                ->whereMonth('date', $currentMonth)
                                ->whereYear('date', $currentYear)
                                ->sum('amount');

                            $thisMonthwants = App\Models\ExpenseCalculation::Where('rules', 'wants')
                                ->whereMonth('date', $currentMonth)
                                ->whereYear('date', $currentYear)
                                ->sum('amount');

                            $thisMonthsavings = App\Models\ExpenseCalculation::Where('rules', 'savings')
                                ->whereMonth('date', $currentMonth)
                                ->whereYear('date', $currentYear)
                                ->sum('amount');

                            $thisYearIncome = App\Models\ExpenseCalculation::Where('types', 'income')
                                ->whereYear('date', $currentYear)
                                ->get();
                            // dd($thisMonthIncome);
                            $thisYearExpense = App\Models\ExpenseCalculation::Where('types', 'expense')
                                ->whereYear('date', $currentYear)
                                ->groupBy('category_id')
                                ->select('category_id', \DB::raw('SUM(amount) as totalExpenseYear'))
                                ->get();

                        @endphp
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
                                        <td>{{ $thisMonthIncome->sum('amount') }}</td>
                                        <td>
                                            @php
                                                $needs = $thisMonthIncome->sum('amount') * 0.5;
                                            @endphp
                                            {{ $needs }}
                                        </td>
                                        <td>
                                            @php
                                                $wants = $thisMonthIncome->sum('amount') * 0.3;
                                            @endphp
                                            {{ $wants }}
                                        </td>
                                        <td>
                                            @php
                                                $savings = $thisMonthIncome->sum('amount') * 0.2;
                                            @endphp
                                            {{ $savings }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Total Expense</th>
                                        <td> {{ $thisMonthExpense->sum('totalExpense') }}</td>
                                        <td>{{ $thisMonthneeds }}</td>
                                        <td>{{ $thisMonthwants }}</td>
                                        <td>{{ $thisMonthsavings }}</td>
                                    </tr>
                                    <tr class="bg-success">
                                        <th>Net Income</th>
                                        <td> {{ $thisMonthIncome->sum('amount') - $thisMonthExpense->sum('totalExpense') }}
                                        </td>
                                        <td>{{ $needs - $thisMonthneeds }}</td>
                                        <td>{{ $wants - $thisMonthwants }}</td>
                                        <td>{{ $savings - $thisMonthsavings }}</td>
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
                                        <td>{{ $thisYearIncome->sum('amount') }}</td>
                                        <td>{{ $thisYearExpense->sum('totalExpenseYear') }}</td>
                                    </tr>
                                    <tr class="bg-success">
                                        <td colspan="2">Net Income:
                                            {{ $thisYearIncome->sum('amount') - $thisYearExpense->sum('totalExpenseYear') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="t3">
                    <div class="row justify-content-center mt-5">


                        <div class="col-md-3">
                            <h4>Category ways Monthly Income</h4>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Name</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    @foreach ($thisMonthIncome as $item)
                                        <tr>
                                            <td>{{ $item->date }}</td>
                                            <td>{{ $item->name }}</td>
                                            <td class="bg-info">{{ $item->amount }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="bg-success">
                                        <td colspan="3">Total Income: {{ $thisMonthIncome->sum('amount') }}</td>
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
                                            <td>
                                                @php
                                                    $category = App\Models\Category::find($item->category_id);
                                                @endphp
                                                {{ $category->name }}
                                            </td>
                                            <td class="bg-info">{{ $item->totalExpense }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="bg-success">
                                        <td colspan="3">Total Expense:
                                            {{ $thisMonthExpense->sum('totalExpense') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-md-3">
                            <h4>{{ date('Y') }} Monthly Income</h4>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        {{-- <th>Date</th> --}}
                                        <th>Name</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $currentYear = date('Y');
                                        $thisYearIncome = App\Models\ExpenseCalculation::where('types', 'income')
                                            ->whereYear('date', $currentYear)
                                            ->groupBy('category_id')
                                            ->select('category_id', \DB::raw('SUM(amount) as totalIncameYear'))
                                            ->get();
                                    @endphp

                                    @foreach ($thisYearIncome as $item)
                                        <tr>
                                            <td>
                                                @php
                                                    $category = App\Models\Category::find($item->category_id);
                                                @endphp
                                                {{ $category->name }}
                                            </td>
                                            <td class="bg-info">{{ $item->totalIncameYear }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="bg-success">
                                        <td colspan="3">Total Income: {{ $thisYearIncome->sum('totalIncameYear') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-3">
                            <h4>{{ date('Y') }} Monthly Expense</h4>
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
                                                {{ $category->name }}
                                            </td>
                                            <td class="bg-info">{{ $item->totalExpenseYear }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="bg-success">
                                        <td colspan="3">Total Expense:
                                            {{ $thisYearExpense->sum('totalExpenseYear') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>





    </div>
    <script>
        //     const iconPath = '{{ asset('logo.PNG') }}';
        //  Push.create("Hello Shailesh!",{
        //        body: "Welcome to the Dashboard.",
        //        timeout: 5000,
        //        icon: iconPath
        // });
    </script>
</x-backend.layouts.master>
