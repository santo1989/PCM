<div id="layoutSidenav_nav">
    <nav class="sb-sidenav accordion sb-sidenav-light" id="sidenavAccordion" style="color:#0078D7;">
        <div class="sb-sidenav-menu">
            @can('Admin')
                <div class="nav">
                    <div class="sb-sidenav-menu-heading">Personal Expense Calculator</div>

                    <a class="nav-link" href="{{ route('home') }}">
                        <div class="sb-nav-link-icon"><i class="fas fa-home"></i></div>
                        Home
                    </a>
                    {{-- <a class="nav-link" href="{{ route('users.show', ['user' => auth()->user()->id]) }}">
                        <div class="sb-nav-link-icon"><i class="fas fa-user-circle"></i></div>
                        Profile
                    </a> --}}
                    {{-- start library --}}

                    {{-- <a class="nav-link collapsed " href="#" data-bs-toggle="collapse"
                        data-bs-target="#collapseLayoutslibrary" aria-expanded="false" aria-controls="collapseLayouts">
                        <div class="sb-nav-link-icon"></div>
                        <h4 class="sb-sidenav-menu-heading"><i class="fas fa-th-large     "></i>
                            LIBRARY </h4>
                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                    </a>

                    <div class="collapse" id="collapseLayoutslibrary" aria-labelledby="headinglibrary"
                        data-bs-parent="#sidenavAccordion">
                        <nav class="sb-sidenav-menu-nested nav"> --}}

                    <a class="nav-link" href="{{ route('categories.index') }}">
                        <div class="sb-nav-link-icon"></div>
                        Category Management
                    </a>
                    <a class="nav-link" href="{{ route('expenseCalculations.index') }}">
                        <div class="sb-nav-link-icon"></div>
                        Expense Management
                    </a>
                    <a class="nav-link" href="{{ route('handCashes.index') }}">
                        <div class="sb-nav-link-icon"></div>
                        Cash Management
                    </a>


                    {{-- </nav>
                    </div> --}}
                    {{-- end library  --}}

                    {{-- <h4 class="sb-sidenav-menu-heading" style="color:#0078D7;">LIBRARY</h4> --}}

                    {{-- start DataEntry --}}

                    {{-- <a class="nav-link collapsed " href="#" data-bs-toggle="collapse"
                        data-bs-target="#collapseLayoutsDataEntry" aria-expanded="false" aria-controls="collapseLayouts">
                        <div class="sb-nav-link-icon"></div>
                        <h4 class="sb-sidenav-menu-heading">Expense Entry Form</h4>
                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                    </a>

                    <div class="collapse" id="collapseLayoutsDataEntry" aria-labelledby="headingDataEntry"
                        data-bs-parent="#sidenavAccordion">
                        <nav class="sb-sidenav-menu-nested nav">

                            <a class="nav-link" href="{{ route('expenseCalculations.index') }}">
                                <div class="sb-nav-link-icon"></div>
                                Expense Entry
                            </a>

                        </nav>
                    </div> --}}
                    {{-- end DataEntry  --}}

                    {{-- user-management start --}}
                    <a class="nav-link collapsed " href="#" data-bs-toggle="collapse"
                        data-bs-target="#collapseLayouts" aria-expanded="false" aria-controls="collapseLayouts">
                        <div class="sb-nav-link-icon"></div>
                        <h4 class="sb-sidenav-menu-heading text-dark">User Management</h4>
                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                    </a>

                    <div class="collapse" id="collapseLayouts" aria-labelledby="headingOne"
                        data-bs-parent="#sidenavAccordion">
                        <nav class="sb-sidenav-menu-nested nav">
                            <a class="nav-link " href="{{ route('roles.index') }}">
                                <div class="sb-nav-link-icon"><i class="fas fa-table"></i></div>
                                Role
                            </a>
                            <a class="nav-link " href="{{ route('users.index') }}">
                                <div class="sb-nav-link-icon"><i class="fas fa-table"></i></div>
                                Users
                            </a>
                            <a class="nav-link " href="{{ route('online_user') }}">
                                <div class="sb-nav-link-icon"></div>
                                Online User List
                            </a>


                        </nav>
                    </div>
                    {{-- user-management end --}}

                </div>
            @endcan
            @php
                // Get distinct years for income
                $incomeYears = App\Models\ExpenseCalculation::selectRaw('DISTINCT YEAR(created_at) as year')
                    ->where('types', 'income')
                    ->get()
                    ->pluck('year');

                // Get total income for each year
                $incomeData = [];
                foreach ($incomeYears as $year) {
                    $totalIncome = App\Models\ExpenseCalculation::where('types', 'income')
                        ->whereYear('created_at', $year)
                        ->sum('amount');

                    $incomeData[$year] = $totalIncome;
                }

                // Get distinct years for expense
                $expenseYears = App\Models\ExpenseCalculation::selectRaw('DISTINCT YEAR(created_at) as year')
                    ->where('types', 'expense')
                    ->get()
                    ->pluck('year');

                // Get total expense for each year
                $expenseData = [];
                foreach ($expenseYears as $year) {
                    $totalExpense = App\Models\ExpenseCalculation::where('types', 'expense')
                        ->whereYear('created_at', $year)
                        ->sum('amount');

                    $expenseData[$year] = $totalExpense;
                }
            @endphp

            <!-- Display the data in a table -->
            <table class="table table-responsive text-center text-dark font-weight-bold">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Income</th>
                        <th>Expense</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($incomeYears->merge($expenseYears)->unique() as $year)
                        <tr>
                            <td>{{ $year }}</td>
                            <td>{{ $incomeData[$year] ?? 0 }}</td>
                            <td>{{ $expenseData[$year] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="sb-sidenav-footer " style="color:#0078D7;">
            <div class="small">Logged in as:</div>
            {{ auth()->user()->role->name ?? '' }}

        </div>
    </nav>
</div>
