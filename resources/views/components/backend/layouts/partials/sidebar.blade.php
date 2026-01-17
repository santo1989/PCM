<div id="layoutSidenav_nav">
    <nav class="sb-sidenav accordion sb-sidenav-light" id="sidenavAccordion" style="color:#0078D7;">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <!-- Admin Only Sections -->
                @can('Admin')
                    <div class="sb-sidenav-menu-heading text-dark">Personal Expense Calculator</div>

                    <!-- Main Links (collapsed) -->
                    <a class="nav-link collapsed text-dark" href="#" data-bs-toggle="collapse" 
                       data-bs-target="#collapseMainNav" aria-expanded="false" aria-controls="collapseMainNav">
                        <div class="sb-nav-link-icon"><i class="fas fa-home"></i></div>
                        <span class="fw-semibold">Home</span>
                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                    </a>

                    <div class="collapse" id="collapseMainNav" aria-labelledby="headingMain" 
                         data-bs-parent="#sidenavAccordion">
                        <nav class="sb-sidenav-menu-nested nav">
                            <a class="nav-link" href="{{ route('home') }}">
                                <div class="sb-nav-link-icon"><i class="fas fa-home"></i></div>
                                Home
                            </a>
                            <a class="nav-link" href="{{ route('categories.index') }}">
                                <div class="sb-nav-link-icon"><i class="fas fa-tags"></i></div>
                                Category Management
                            </a>
                            <a class="nav-link" href="{{ route('interactive.dashboard') }}">
                                <div class="sb-nav-link-icon"><i class="fas fa-chart-bar"></i></div>
                                Interactive Dashboard
                            </a>
                        </nav>
                    </div>

                    <a class="nav-link text-dark" href="{{ route('expenseCalculations.index') }}">
                        <div class="sb-nav-link-icon"><i class="fas fa-money-bill-wave"></i></div>
                        Expense Management
                    </a>
                    
                    <a class="nav-link text-dark" href="{{ route('handCashes.index') }}">
                        <div class="sb-nav-link-icon"><i class="fas fa-cash-register"></i></div>
                        Cash Management
                    </a>

                    <!-- User Management -->
                    <a class="nav-link collapsed text-dark" href="#" data-bs-toggle="collapse"
                       data-bs-target="#collapseLayouts" aria-expanded="false" aria-controls="collapseLayouts">
                        <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                        <span class="fw-semibold">User Management</span>
                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                    </a>

                    <div class="collapse" id="collapseLayouts" aria-labelledby="headingOne"
                         data-bs-parent="#sidenavAccordion">
                        <nav class="sb-sidenav-menu-nested nav">
                            <a class="nav-link" href="{{ route('roles.index') }}">
                                <div class="sb-nav-link-icon"><i class="fas fa-user-tag"></i></div>
                                Role
                            </a>
                            <a class="nav-link" href="{{ route('users.index') }}">
                                <div class="sb-nav-link-icon"><i class="fas fa-users-cog"></i></div>
                                Users
                            </a>
                            <a class="nav-link" href="{{ route('online_user') }}">
                                <div class="sb-nav-link-icon"><i class="fas fa-user-clock"></i></div>
                                Online User List
                            </a>
                        </nav>
                    </div>
                @endcan

                <!-- Yearly Summary - Visible to all users -->
                @php
                    // Moved PHP code here for better organization
                    $categories = App\Models\Category::all();
                    
                    $incomeYears = App\Models\ExpenseCalculation::selectRaw('DISTINCT YEAR(created_at) as year')
                        ->where('types', 'INCOME')
                        ->get()
                        ->pluck('year');
                    
                    $expenseYears = App\Models\ExpenseCalculation::selectRaw('DISTINCT YEAR(created_at) as year')
                        ->where('types', 'EXPENSE')
                        ->get()
                        ->pluck('year');
                    
                    $years = $incomeYears->merge($expenseYears)->unique()->sortDesc();
                @endphp

                @if($years->count() > 0)
                    <div class="sb-sidenav-menu-heading mt-3 text-dark">Yearly Summary</div>
                    <div class="px-3">
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless text-dark small mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-0">Year</th>
                                        <th class="text-end">Income</th>
                                        <th class="text-end pe-0">Expense</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($years as $year)
                                        @php
                                            $totalIncome = App\Models\ExpenseCalculation::where('types', 'INCOME')
                                                ->whereYear('created_at', $year)
                                                ->sum('amount');
                                            
                                            $totalExpense = App\Models\ExpenseCalculation::where('types', 'EXPENSE')
                                                ->whereYear('created_at', $year)
                                                ->sum('amount');
                                        @endphp
                                        <tr>
                                            <td class="ps-0">{{ $year }}</td>
                                            <td class="text-end">
                                                @if($totalIncome > 0)
                                                    <button type="button" class="btn btn-link p-0 text-decoration-none text-primary" 
                                                            data-bs-toggle="modal" data-bs-target="#incomeDetailsModal{{ $year }}">
                                                        {{ number_format($totalIncome, 2) }}
                                                    </button>
                                                @else
                                                    <span class="text-muted">0.00</span>
                                                @endif
                                            </td>
                                            <td class="text-end pe-0">
                                                @if($totalExpense > 0)
                                                    <button type="button" class="btn btn-link p-0 text-decoration-none text-primary" 
                                                            data-bs-toggle="modal" data-bs-target="#expenseDetailsModal{{ $year }}">
                                                        {{ number_format($totalExpense, 2) }}
                                                    </button>
                                                @else
                                                    <span class="text-muted">0.00</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Footer -->
        <div class="sb-sidenav-footer" style="color:#0078D7;">
            <div class="small">Logged in as:</div>
            <span class="fw-semibold">{{ auth()->user()->role->name ?? 'User' }}</span>
        </div>
    </nav>
</div>

<!-- Modals - Placed outside the sidebar for proper rendering -->
@foreach ($years as $year)
    <!-- Income Details Modal -->
    <div class="modal fade" id="incomeDetailsModal{{ $year }}" tabindex="-1" 
         aria-labelledby="incomeDetailsModalLabel{{ $year }}" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="incomeDetailsModalLabel{{ $year }}">
                        <i class="fas fa-money-bill-wave me-2"></i>Income Details for {{ $year }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" 
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Total Income</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $category)
                                    @php
                                        $category_income = App\Models\ExpenseCalculation::where('types', 'INCOME')
                                            ->where('category_id', $category->id)
                                            ->whereYear('date', $year)
                                            ->sum('amount');
                                    @endphp
                                    @if ($category_income > 0)
                                        <tr>
                                            <td>{{ $category->name }}</td>
                                            <td class="text-end fw-semibold text-success">
                                                {{ number_format($category_income, 2) }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                            <tfoot class="table-primary">
                                <tr>
                                    <td class="fw-bold">Total</td>
                                    <td class="text-end fw-bold">
                                        @php
                                            $yearTotalIncome = App\Models\ExpenseCalculation::where('types', 'INCOME')
                                                ->whereYear('date', $year)
                                                ->sum('amount');
                                        @endphp
                                        {{ number_format($yearTotalIncome, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Expense Details Modal -->
    <div class="modal fade" id="expenseDetailsModal{{ $year }}" tabindex="-1" 
         aria-labelledby="expenseDetailsModalLabel{{ $year }}" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="expenseDetailsModalLabel{{ $year }}">
                        <i class="fas fa-shopping-cart me-2"></i>Expense Details for {{ $year }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" 
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Total Expense</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $category)
                                    @php
                                        $category_expense = App\Models\ExpenseCalculation::where('types', 'EXPENSE')
                                            ->where('category_id', $category->id)
                                            ->whereYear('date', $year)
                                            ->sum('amount');
                                    @endphp
                                    @if ($category_expense > 0)
                                        <tr>
                                            <td>{{ $category->name }}</td>
                                            <td class="text-end fw-semibold text-danger">
                                                {{ number_format($category_expense, 2) }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                            <tfoot class="table-warning">
                                <tr>
                                    <td class="fw-bold">Total</td>
                                    <td class="text-end fw-bold">
                                        @php
                                            $yearTotalExpense = App\Models\ExpenseCalculation::where('types', 'EXPENSE')
                                                ->whereYear('date', $year)
                                                ->sum('amount');
                                        @endphp
                                        {{ number_format($yearTotalExpense, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach

<!-- Include Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Optional: Add custom CSS for better sidebar styling -->
<style>
    .sb-sidenav {
        transition: all 0.3s ease;
    }
    
    .sb-sidenav .nav-link {
        transition: all 0.2s ease;
    }
    
    .sb-sidenav .nav-link:hover {
        background-color: rgba(0, 120, 215, 0.1);
        padding-left: 1.5rem !important;
    }
    
    .sb-sidenav-menu-heading {
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table-sm th, .table-sm td {
        padding: 0.25rem 0.5rem;
    }
    
    .btn-link {
        font-weight: 500;
    }
    
    .sb-sidenav-footer {
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
</style>