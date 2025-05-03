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
        @php
        $date = date('Y-m-d');
        // Get all categories and their expenses average for all year
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
        'totalExpense' => $expense->totalExpense,
        'averageExpense' => $averageExpense,
        ];
        }

        // Now $thisYearExpense contains the category ID, total expense, and average expense for each category.

        // dd($thisYearExpense);

        // Get the average expense in a month form all year
        $thisYearExpenseTotal = App\Models\ExpenseCalculation::where('types', 'expense')->select(DB::raw('sum(amount) as totalExpense'), DB::raw('count(distinct MONTH(date)) as totalMonths'), DB::raw('sum(amount)/count(distinct MONTH(date)) as averageExpense'))->first()->averageExpense;

        // dd($thisYearExpenseTotal);

        // Calculate the last month and year based on the current date
        $lastMonth = date('m') == '01' ? '12' : str_pad(date('m') - 1, 2, '0', STR_PAD_LEFT);
        $lastYear = date('m') == '01' ? date('Y') - 1 : date('Y');

        // Get all categories and their expenses for the last month
        $lastMonthExpense = App\Models\ExpenseCalculation::whereYear('date', $lastYear)
        ->whereMonth('date', $lastMonth)
        ->where('types', 'expense')
        ->groupBy('category_id')
        ->select('category_id', \DB::raw('SUM(amount) as totalExpense'))
        ->get();

        $lastMonthExpenseTotal = App\Models\ExpenseCalculation::whereYear('date', $lastYear)
        ->whereMonth('date', $lastMonth)
        ->where('types', 'expense')
        ->sum('amount');

        // Get all categories
        $categories = App\Models\Category::all();

        // Calculate the total expense without category_id 1
        $totalExpenseWithoutCategory1 = App\Models\ExpenseCalculation::where('types', 'expense')->avg('amount');
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
                    {{-- <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td>{{ $category->name }}<input type="hidden" name="category_id[]"
                        value="{{ $category->id }}"></td>
                    <td>{{ $thisYearExpense->where('category_id', $category->id)->avg('totalExpense') }}
                    </td>
                    <td>{{ $lastMonthExpense->where('category_id', $category->id)->avg('totalExpense') }}
                    </td>
                    <td><input type="text" name="projectedExpense[{{ $category->id }}]"
                            id="projectedExpense{{ $category->id }}"
                            class="form-control projectedExpenseInput"></td>
                    </tr>
                    @endforeach
                    <tr>
                        <td>Total</td>
                        <td>{{ $thisYearExpenseTotal }}</td>
                        <td>{{ $lastMonthExpenseTotal }}</td>
                        <td id="totalExpenseOutput"></td>
                    </tr>
                    <tr>
                        <td>Total without Category 1</td>
                        <td colspan="2"></td>
                        <td>{{ $totalExpenseWithoutCategory1 }}</td>
                    </tr>
                    </tbody> --}}
                    <tbody>
                        @foreach ($categories as $category)
                        @php
                        $thisYearExpenseCategory = collect($thisYearExpense)->where('category_id', $category->id);
                        $lastMonthExpenseCategory = collect($lastMonthExpense)->where('category_id', $category->id);
                        @endphp
                        <tr>
                            <td>{{ $category->name }}<input type="hidden" name="category_id[]" value="{{ $category->id }}"></td>
                            <td>{{ $thisYearExpenseCategory->avg('totalExpense') }}</td>
                            <td>{{ $lastMonthExpenseCategory->avg('totalExpense') }}</td>
                            <td><input type="text" name="projectedExpense[{{ $category->id }}]"
                                    id="projectedExpense{{ $category->id }}" class="form-control projectedExpenseInput"></td>
                        </tr>
                        @endforeach
                        <tr>
                            <td>Total</td>
                            <td>{{ $thisYearExpenseTotal }}</td>
                            <td>{{ $lastMonthExpenseTotal }}</td>
                            <td id="totalExpenseOutput"></td>
                        </tr>
                        <tr>
                            <td>Total without Category 1</td>
                            <td colspan="2"></td>
                            <td>{{ $totalExpenseWithoutCategory1 }}</td>
                        </tr>
                    </tbody>

                </table>
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>

    <script>
        // Function to calculate the total and update the output
        function calculateTotal() {
            var total = 0;
            var projectedExpenseInputs = document.getElementsByClassName('projectedExpenseInput');
            for (var i = 0; i < projectedExpenseInputs.length; i++) {
                var value = parseInt(projectedExpenseInputs[i].value);
                total += isNaN(value) ? 0 : value;
            }
            document.getElementById('totalExpenseOutput').innerText = total;
        }

        // Add event listeners to each projectedExpense input for keyup event
        var projectedExpenseInputs = document.getElementsByClassName('projectedExpenseInput');
        for (var i = 0; i < projectedExpenseInputs.length; i++) {
            projectedExpenseInputs[i].addEventListener('keyup', calculateTotal);
        }

        // Initial calculation on page load
        calculateTotal();
    </script>



    <script>
        $(document).ready(function() {
            $('#example').DataTable();
        });
    </script>

    {{-- <x-slot name="pageTitle">
        Admin Dashboard
    </x-slot>

   <div class="container">
    @php
        $date = date('Y-m-d');

        // Get all categories and their expenses for this year
        $thisYearExpense = App\Models\ExpenseCalculation::whereYear('date', date('Y'))
            ->groupBy('category_id')
            ->select('category_id', \DB::raw('SUM(amount) as totalExpense'))
            ->get();
        $thisYearExpenseTotal = App\Models\ExpenseCalculation::whereYear('date', date('Y'))->where('types', 'expense')->sum('amount')/12;

        // Calculate the last month and year based on the current date
        $lastMonth = date('m') == '01' ? '12' : str_pad(date('m') - 1, 2, '0', STR_PAD_LEFT);
        $lastYear = date('m') == '01' ? date('Y') - 1 : date('Y');

        // Get all categories and their expenses for the last month
        $lastMonthExpense = App\Models\ExpenseCalculation::whereYear('date', $lastYear)
            ->whereMonth('date', $lastMonth)
            ->groupBy('category_id')
            ->select('category_id', \DB::raw('SUM(amount) as totalExpense'))
            ->get();

             $lastMonthExpenseTotal = App\Models\ExpenseCalculation::whereYear('date', $lastYear)
            ->whereMonth('date', $lastMonth)->where('types', 'expense')->sum('amount');

        // Get all categories
        $categories = App\Models\Category::all();

        // Calculate the total expense without category_id 1
        $totalExpenseWithoutCategory1 = App\Models\ExpenseCalculation::where('types', 'expense')->avg('amount');
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
                <th>This Year Avg Expense</th>
                <th>Last Month Expensed</th>
                <th>This Month Projected Expense</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($categories as $category)
            <tr>
                <td>{{ $category->name }}<input type="hidden" name="category_id[]"
                        value="{{ $category->id }}"></td>
                <td>{{ $thisYearExpense->where('category_id', $category->id)->avg('totalExpense') }}</td>
                <td>{{ $lastMonthExpense->where('category_id', $category->id)->avg('totalExpense') }}</td>
                <td><input type="text" name="projectedExpense[{{ $category->id }}]"
                        id="projectedExpense{{ $category->id }}"
                        class="form-control projectedExpenseInput"></td>
            </tr>
            @endforeach
            <tr>
                <td>Total</td>
                <td>{{ $thisYearExpenseTotal }}</td>
                <td>{{ $lastMonthExpenseTotal }}</td>
                <td id="totalExpenseOutput"></td>
            </tr>
            <tr>
                <td>Total without Category 1</td>
                <td colspan="2"></td>
                <td>{{ $totalExpenseWithoutCategory1 }}</td>
            </tr>
        </tbody>
    </table>
    <button type="submit" class="btn btn-primary">Submit</button>
    </div>
    </form>
    </div>

    <script>
        // Function to calculate the total and update the output
        function calculateTotal() {
            var total = 0;
            var projectedExpenseInputs = document.getElementsByClassName('projectedExpenseInput');
            for (var i = 0; i < projectedExpenseInputs.length; i++) {
                var value = parseInt(projectedExpenseInputs[i].value);
                total += isNaN(value) ? 0 : value;
            }
            document.getElementById('totalExpenseOutput').innerText = total;
        }

        // Add event listeners to each projectedExpense input for keyup event
        var projectedExpenseInputs = document.getElementsByClassName('projectedExpenseInput');
        for (var i = 0; i < projectedExpenseInputs.length; i++) {
            projectedExpenseInputs[i].addEventListener('keyup', calculateTotal);
        }

        // Initial calculation on page load
        calculateTotal();
    </script>



    <script>
        $(document).ready(function() {
            $('#example').DataTable();
        });
    </script> --}}


</x-backend.layouts.master>