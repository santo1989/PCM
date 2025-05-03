<x-backend.layouts.master>
    <x-slot name="title">
        Projection Report
    </x-slot>

 @php
    $date = date('Y-m-d');

    // Retrieve all categories
    $categories = App\Models\Category::all()->except([1, 19, 20, 23]);

    // Calculate this year's average expenses per category
    $allYearExpenses = App\Models\ExpenseCalculation::where('types', 'expense')
        ->whereMonth('date', '!=', now()->month)
        ->groupBy('category_id')
        ->select('category_id', DB::raw('sum(amount) as totalExpense'), DB::raw('count(distinct MONTH(date)) as totalMonths'))
        ->get();

    $thisYearExpense = [];
    foreach ($allYearExpenses as $expense) {
        $averageExpense = $expense->totalExpense / $expense->totalMonths;
        $thisYearExpense[] = [
            'category_id' => $expense->category_id,
            'averageExpense' => ceil(($averageExpense * 100) / 100), // Ceil to 2 decimal places
        ];
    }

    // Get last month's expenses per category
    $lastMonth = date('m') == '01' ? '12' : str_pad(date('m') - 1, 2, '0', STR_PAD_LEFT);
    $lastYear = date('m') == '01' ? date('Y') - 1 : date('Y');

    $lastMonthExpense = App\Models\ExpenseCalculation::whereYear('date', $lastYear)
        ->whereMonth('date', $lastMonth)
        ->where('types', 'expense')
        ->groupBy('category_id')
        ->select('category_id', \DB::raw('SUM(amount) as totalExpense'))
        ->get()
        ->map(function ($expense) {
            $expense->totalExpense = ceil(($expense->totalExpense * 100) / 100); // Ceil to 2 decimal places
            return $expense;
        });

    $totallastMonthExpense = $lastMonthExpense->sum('totalExpense');
    $totallastMonthExpense = number_format($totallastMonthExpense, 2, '.', '');

    //$MonthlyactualLimitExpense will be the 40% of the total monthly income for all categories of expense
    $totalMonthlyIncome = App\Models\ExpenseCalculation::where('category_id', 1) // Assuming category_id 1 is for income
        ->whereMonth('date', date('m'))
        ->sum('amount');

    $MonthlyactualLimitExpense = ceil(($totalMonthlyIncome * 40) / 100); // Ceil to 2 decimal places
    
    
    // dd($MonthlyactualLimitExpense);





@endphp

<h3 class="text-center">Projected Monthly Budget</h3>
<form action="" method="post">
    @csrf
    <div class="row">
        <input type="hidden" name="date" value="{{ $date }}">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Avg Expense</th>
                    <th>Last Month Expensed</th>
                    <th>This Month Projected Expense</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    @php
                        $thisYearExpenseCategory = collect($thisYearExpense)->firstWhere('category_id', $category->id);
                        $lastMonthExpenseCategory = $lastMonthExpense->firstWhere('category_id', $category->id);
                    @endphp

                    @if (
                        !empty($thisYearExpenseCategory) && $thisYearExpenseCategory['averageExpense'] > 0 ||
                        !empty($lastMonthExpenseCategory) && $lastMonthExpenseCategory->totalExpense > 0
                    )
                        <tr>
                            <td>{{ $category->name }}<input type="hidden" name="category_id[]" value="{{ $category->id }}"></td>
                            <td>{{ $thisYearExpenseCategory['averageExpense'] ?? '0.00' }}</td>
                            <td>{{ $lastMonthExpenseCategory->totalExpense ?? '0.00' }}</td>
                            <td>
                                <input type="text" name="projectedExpense[{{ $category->id }}]"
                                    id="projectedExpense{{ $category->id }}" class="form-control projectedExpenseInput">
                            </td>
                        </tr>
                    @endif
                @endforeach
                <tr>
                    <td class="text-right"><strong>Monthly Limit:</strong> <strong id="monthlyLimitOutput">{{ $MonthlyactualLimitExpense }}</strong></td>
                    <td><strong id="totalExpenseOutput">{{ $thisYearExpenseCategory['averageExpense'] ?? '0.00' }}</strong></td>
                    <td><strong id="lastMonthExpenseOutput">{{ $totallastMonthExpense ?? '0.00' }}</strong></td> 
                </tr>
            </tbody>
        </table>
        {{-- <button type="submit" class="btn btn-primary">Submit</button> --}}
    </div>
</form>

<script>
    function calculateTotal() {
        var total = 0;
        var projectedExpenseInputs = document.getElementsByClassName('projectedExpenseInput');
        for (var i = 0; i < projectedExpenseInputs.length; i++) {
            var value = parseFloat(projectedExpenseInputs[i].value);
            total += isNaN(value) ? 0 : value;
        }
        document.getElementById('totalExpenseOutput').innerText = total.toFixed(2);
    }

    var projectedExpenseInputs = document.getElementsByClassName('projectedExpenseInput');
    for (var i = 0; i < projectedExpenseInputs.length; i++) {
        projectedExpenseInputs[i].addEventListener('keyup', calculateTotal);
    }

    calculateTotal();
</script>



</x-backend.layouts.master>




{{-- <x-backend.layouts.master>
    <x-slot name="title">Projection Report</x-slot>

    <div class="container">
        <div class="row justify-content-center pt-4">
            <div class="col-md-3"><a href="{{ route('Budge_Projection') }}" class="btn btn-sm btn-outline-danger">Budget
                    Projection</a></div>
            <div class="col-md-3"><a href="{{ route('Yearly_report') }}" class="btn btn-sm btn-outline-danger">Yearly
                    Report</a></div>
            <div class="col-md-3"><a href="{{ route('Monthly_report') }}" class="btn btn-sm btn-outline-danger">Monthly
                    Report</a></div>
            <div class="col-md-3"><a href="{{ route('power_bi_report') }}" class="btn btn-sm btn-outline-danger">BI
                    Report</a></div>
        </div>

        @php
            // Fetching Expense and Income data grouped by category
            $categories = App\Models\Category::all();
            $expenseAverages = App\Models\ExpenseCalculation::where('types', 'expense')
                ->groupBy('category_id')
                ->select('category_id', DB::raw('AVG(amount) as avgExpense'))
                ->get();

            $incomeAverages = App\Models\ExpenseCalculation::where('types', 'income')
                ->groupBy('category_id')
                ->select('category_id', DB::raw('AVG(amount) as avgIncome'))
                ->get();
        @endphp

        <h3 class="text-center">Projection Report</h3>
        <div class="row">
            <div class="col-md-6">
                <h4>Average Expenses by Category</h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Average Expense</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            @php
                                $avgExpense =
                                    $expenseAverages->firstWhere('category_id', $category->id)->avgExpense ?? 0;
                            @endphp
                            <tr>
                                <td>{{ $category->name }}</td>
                                <td>{{ number_format($avgExpense, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <h4>Average Income by Category</h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Average Income</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            @php
                                $avgIncome = $incomeAverages->firstWhere('category_id', $category->id)->avgIncome ?? 0;
                            @endphp
                            <tr>
                                <td>{{ $category->name }}</td>
                                <td>{{ number_format($avgIncome, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-backend.layouts.master> --}}
