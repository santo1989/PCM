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
                    {{-- <tr>
                        <div class="col-sm-3">
                            <td>Start Year</td>
                            <td>
                                <select name="start_year" id="start_year" class="form-control" required>
                                    <option value="" disabled selected>Select Year</option>
                                    @for ($i = 2022; $i <= date('Y'); $i++)
                                        <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                            </td>
                        </div>
                        <div class="col-sm-3">
                            <td>Start Month</td>
                            <td>
                                <select name="start_month" id="start_month" class="form-control" required>
                                    <option value="" disabled selected>Select Month</option>
                                    @for ($i = 1; $i <= 12; $i++)
                                        <option value="{{ $i }}"
                                            {{ $i == intval(request('start_month')) ? 'selected' : '' }}>
                                            {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                                        </option>
                                    @endfor
                                </select>
                            </td>
                        </div>
                        <div class="col-sm-3">
                            <td>End Year</td>
                            <td>
                                <select name="end_year" id="end_year" class="form-control" required>
                                    <option value="" disabled selected>Select Year</option>
                                    @for ($i = 2022; $i <= date('Y'); $i++)
                                        <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                            </td>
                        </div>
                        <div class="col-sm-3">
                            <td>End Month</td>
                            <td>
                                <select name="end_month" id="end_month" class="form-control" required>
                                    <option value="" disabled selected>Select Month</option>
                                    @for ($i = 1; $i <= 12; $i++)
                                        <option value="{{ $i }}"
                                            {{ $i == intval(request('end_month')) ? 'selected' : '' }}>
                                            {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                                        </option>
                                    @endfor
                                </select>
                            </td>
                        </div>
                        <div class="col-sm-3">
                            <td>
                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="{{ route('Monthly_report') }}" class="btn btn-outline-danger">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </a>
                            </td>
                        </div>
                    </tr> --}}
                    <tr>
                        <div class="col-sm-4">
                            <td>Start Date</td>
                            <td>
                                <input type="date" name="start_date" id="start_date" class="form-control" required value="{{ $startDate ?? '' }}">
                            </td>
                        </div>
                        <div class="col-sm-4">
                            <td>End Date</td>
                            <td>
                                <input type="date" name="end_date" id="end_date" class="form-control" required value="{{ $endDate ?? '' }}">
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
                            </td>
                        </div>
                    </tr>
                </table>
            </form>
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
                            <td colspan="3">Total Income: {{ $thisYearIncomecategory->sum('totalIncomeYear') }}</td>
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

</x-backend.layouts.master>
