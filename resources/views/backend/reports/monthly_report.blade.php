<x-backend.layouts.master>

    <x-slot name="pageTitle">
        Month Dashboard
    </x-slot>
    {{-- <x-slot name='breadCrumb'>
                <x-backend.layouts.elements.breadcrumb>
                    <x-slot name="pageHeader"> Monthly Report Search </x-slot>
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
        <div class="row text-center p-2">
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
                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="{{ route('Monthly_report') }}" class="btn btn-outline-danger">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </a>
                                <button type="button" class="btn btn-outline-success"
                                    id="download_excel"
                                    onclick="excelDownloadReport()">
                                    <i class="fas fa-file-excel"></i> Excel Download 
                                </button>
                            </td>
                        </div>
                    </tr>
                </table>
            </form>
        </div>
        <div id="printable" class="row justify-content-center">
            <div class="col-md-12 text-center">
                <h2>Monthly Report</h2>
                <h3>{{ $startDate }} - {{ $endDate }}</h3>
            </div>

            <div class="row justify-content-center pt-4">
                @php

                @endphp
                <div class="col-md-6">
                    {{-- <h4>{{ date('F', mktime(0, 0, 0, $currentMonth, 1)) }} Net Income</h4> --}}
                    <h4>{{ $startDate }} - {{ $endDate }} Net Income</h4>
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
                    <h4> Net Income</h4>
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
                    <h4>{{ date('F', mktime(0, 0, 0, $currentMonth, 1)) }}, {{ $currentYear }} Monthly Income</h4>
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
                                        {{ $category->name }}
                                    </td>
                                    <td class="bg-info">{{ $item->totalIncomeYear }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-success">
                                <td colspan="3">Total Income: {{ $thisYearIncomecategory->sum('totalIncomeYear') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-3">
                    <h4>{{ $currentYear }} Monthly Expense</h4>
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

    <script>
        //download_excel from the printable div
        function excelDownloadReport() {
            var printableContent = document.getElementById("printable").innerHTML;
            var blob = new Blob([printableContent], {
                type: "application/vnd.ms-excel"
            });
            var url = URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "monthly_report.xls";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>

</x-backend.layouts.master>
