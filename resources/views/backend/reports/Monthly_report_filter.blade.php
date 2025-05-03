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
        <div class="row text-center p-2">
    <form action="{{ route('Monthly_report_filter') }}" method="get">
        <table class="table table-borderless text-dark font-weight-bold">
            <tr>
                <td>Year</td>
                <td>
                    <select name="year" id="year" class="form-control" required>
                        <option value="" disabled>Select Year</option>
                        @for ($i = 2022; $i <= date('Y'); $i++)
                            <option value="{{ $i }}" {{ $i == $currentYear ? 'selected' : '' }}>{{ $i }}</option>
                        @endfor
                    </select>
                </td>
                <td>Month</td>
                <td>
                    <select name="month" id="month" class="form-control" required>
                        <option value="" disabled>Select Month</option>
                        @for ($i = 1; $i <= 12; $i++)
                            <option value="{{ $i }}" {{ $i == $currentMonth ? 'selected' : '' }}>
                                {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                            </option>
                        @endfor
                    </select>
                </td>
                <td>
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                    <a href="{{ route('Monthly_report') }}" class="btn btn-outline-danger">Refresh</a>
                </td>
            </tr>
        </table>
    </form>
</div>

<div class="row justify-content-center pt-4">
    <div class="col-md-6">
        <h4>{{ date('F', mktime(0, 0, 0, $currentMonth, 1)) }} Net Income</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Total Income</th>
                    <th>Total Expense</th>
                    <th>Net Income</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $thisMonthIncome->sum('amount') }}</td>
                    <td>{{ $thisMonthExpense->sum('totalExpense') }}</td>
                    <td>{{ $thisMonthIncome->sum('amount') - $thisMonthExpense->sum('totalExpense') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="col-md-6">
        <h4>{{ $currentYear }} Net Income</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Total Income</th>
                    <th>Total Expense</th>
                    <th>Net Income</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $thisYearIncome->sum('amount') }}</td>
                    <td>{{ $thisYearExpense->sum('totalExpenseYear') }}</td>
                    <td>{{ $thisYearIncome->sum('amount') - $thisYearExpense->sum('totalExpenseYear') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</x-backend.layouts.master>
