<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Cash
    </x-slot>

    <x-backend.layouts.elements.errors />


    <section class="content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-md-12 col-sm-12 col-xl-12">
                    <div class="card" style="background-color: #40c47c;">


                        <div class="card-header" style="background: rgba(0, 0, 0, 0.4); color: #f1f1f1; ">

                            <form method="GET" action="{{ route('expenseCalculations.index') }}">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12 col-sm-12">
                                        <table
                                            class="table table-borderless table-responsive text-center text-light font-weight-bold">
                                            <tr>


                                                <div class="form-group">
                                                    <td>Types:</td>
                                                    <td>
                                                        <select class="form-control" name="types" id="types">
                                                            <option value="">Select Types</option>

                                                            @php
                                                                $types = App\Models\ExpenseCalculation::select('types')
                                                                    ->distinct()
                                                                    ->get();
                                                            @endphp
                                                            @foreach ($types as $type)
                                                                <option value="{{ $type->types }}"
                                                                    {{ $type->types == $search_types ? 'selected' : '' }}>
                                                                    {{ $type->types }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                </div>

                                                <div class="form-group">
                                                    <td>Category:</td>
                                                    <td>
                                                        <select class="form-control" name="category_id"
                                                            id="category_id">
                                                            <option value="">Select Category</option>
                                                            @foreach ($categories as $category)
                                                                <option value="{{ $category->id }}"
                                                                    {{ $category->id == $search_category_id ? 'selected' : '' }}>
                                                                    {{ $category->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                </div>

                                                <div class="form-group">
                                                    <td>Date:</td>
                                                    <td>
                                                        <input type="date" name="entry_date_start"
                                                            id="entry_date_start" class="form-control"
                                                            value="{{ $search_entry_date_start }}">
                                                    </td>
                                                    <td>-</td>
                                                    <td>
                                                        <input type="date" name="entry_date_end" id="entry_date_end"
                                                            class="form-control" value="{{ $search_entry_date_end }}">
                                                    </td>
                                                </div>
                                                <td>
                                                    <button class="btn btn-outline-info btn-sm"
                                                        onclick="validateForm()"><i class="fa fa-search"></i>
                                                        Search</button>

                                                </td>

                                                <td>
                                                    <a href="{{ route('expenseCalculations.index') }}"
                                                        class="btn btn-outline-danger btn-sm"><i
                                                            class="fa fa-refresh"></i> Reset</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                            </form>


                        </div>

                    </div>

                    <div class="row pt-1">
                        <div class="col-md-2 col-sm-12">
                            <a type="button" class="btn btn-outline-dark" data-bs-toggle="modal"
                                data-bs-target="#CashEntryModal"><i class="fa fa-plus" aria-hidden="true"></i>
                                Create
                            </a>

                            @php
                                $this_month_income = App\Models\ExpenseCalculation::where('types', 'income')
                                    ->whereMonth('date', date('m'))
                                    ->whereYear('date', date('Y'))
                                    ->sum('amount');
                                // dd($this_month_income);
                                $this_month_expense = App\Models\ExpenseCalculation::where('types', 'expense')
                                    ->whereMonth('date', date('m'))
                                    ->whereYear('date', date('Y'))
                                    ->sum('amount');
                            // dd($this_month_expense);
                            $all_time_income = App\Models\ExpenseCalculation::where('types', 'income')
                                ->sum('amount');
                            // dd($all_time_income);
                            $all_time_expense = App\Models\ExpenseCalculation::where('types', 'expense')
                                ->sum('amount');
                            // dd($all_time_expense);
                            @endphp
                        </div>
                        <div class="col-md-2 col-sm-12">
                            <h5 class="text-center"> 
                                <!--modal trigger button for show categories wise this month income-->
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#categoriesWiseIncomeModal">
                                    This Month Income: {{ $this_month_income }}
                                </button>
                                <!-- Modal -->
                                <div class="modal fade" id="categoriesWiseIncomeModal" tabindex="-1" aria-labelledby="categoriesWiseIncomeModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="categoriesWiseIncomeModalLabel">Categories Wise This Month Income</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Category</th>
                                                            <th>Income Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php
                                                            $categories = App\Models\Category::all();
                                                        @endphp
                                                        @foreach ($categories as $category)
                                                            @php
                                                                $category_income = App\Models\ExpenseCalculation::where('types', 'income')
                                                                    ->where('category_id', $category->id)
                                                                    ->whereMonth('date', date('m'))
                                                                    ->whereYear('date', date('Y'))
                                                                    ->sum('amount');
                                                            @endphp
                                                            @if ($category_income > 0)
                                                            <tr>
                                                                <td>{{ $category->name }}</td>
                                                                <td>{{ $category_income }}</td>
                                                            </tr>
                                                            @endif
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Modal End -->

                            </h5>

                        </div>
                        <div class="col-md-2 col-sm-12">
                            <h5 class="text-center">
                                <!--modal trigger button for show categories wise this month expense-->
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#categoriesWiseExpenseModal">
                                    This Month Expense: {{ $this_month_expense }}
                                </button>

                                <!-- Modal -->
                                <div class="modal fade" id="categoriesWiseExpenseModal" tabindex="-1" aria-labelledby="categoriesWiseExpenseModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="categoriesWiseExpenseModalLabel">Categories Wise This Month Expense</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Category</th>
                                                            <th>Expense Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php
                                                            $categories = App\Models\Category::all();
                                                        @endphp
                                                        @foreach ($categories as $category)
                                                            @php
                                                                $category_expense = App\Models\ExpenseCalculation::where('types', 'expense')
                                                                    ->where('category_id', $category->id)
                                                                    ->whereMonth('date', date('m'))
                                                                    ->whereYear('date', date('Y'))
                                                                    ->sum('amount');
                                                            @endphp
                                                            @if ($category_expense > 0)
                                                                <tr>
                                                                    <td>{{ $category->name }}</td>
                                                                    <td>{{ $category_expense }}</td>
                                                                </tr>
                                                            @endif
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="modal-footer"> </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Modal End -->

                                
                            </h5>


                        </div>
                         <div class="col-md-2 col-sm-12">
                            <h5 class="text-center"> 
                                <!--modal trigger button for show categories wise all month income-->
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#categoriesWiseAllTimeIncomeModal">
                                    Total Income: {{ $all_time_income }}
                                </button>
                                <!-- Modal -->
                                <div class="modal fade" id="categoriesWiseAllTimeIncomeModal" tabindex="-1" aria-labelledby="categoriesWiseAllTimeIncomeModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="categoriesWiseAllTimeIncomeModalLabel">Categories Wise All Time Income</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Category</th>
                                                            <th>Income Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php
                                                            $categories = App\Models\Category::all();
                                                        @endphp
                                                        @foreach ($categories as $category)
                                                            @php
                                                                $category_all_time_income = App\Models\ExpenseCalculation::where('types', 'income')
                                                                    ->where('category_id', $category->id)
                                                                    ->sum('amount');
                                                            @endphp
                                                            @if ($category_all_time_income > 0)
                                                            <tr>
                                                                <td>{{ $category->name }}</td>
                                                                <td>{{ $category_all_time_income }}</td>
                                                            </tr>
                                                            @endif
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Modal End -->

                            </h5>

                        </div>
                        <div class="col-md-2 col-sm-12">
                            <h5 class="text-center">
                                <!--modal trigger button for show categories wise all time expense-->
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#categoriesWiseAllTimeExpenseModal">
                                    Total Expense: {{ $all_time_expense }}
                                </button>

                                <!-- Modal -->
                                <div class="modal fade" id="categoriesWiseAllTimeExpenseModal" tabindex="-1" aria-labelledby="categoriesWiseAllTimeExpenseModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="categoriesWiseAllTimeExpenseModalLabel">Categories Wise Total Expense</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Category</th>
                                                            <th>Expense Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php
                                                            $categories = App\Models\Category::all();
                                                        @endphp
                                                        @foreach ($categories as $category)
                                                            @php
                                                                $category_all_time_expense = App\Models\ExpenseCalculation::where('types', 'expense')
                                                                    ->where('category_id', $category->id)
                                                                   ->sum('amount');
                                                            @endphp
                                                            @if ($category_all_time_expense > 0)
                                                                <tr>
                                                                    <td>{{ $category->name }}</td>
                                                                    <td>{{ $category_all_time_expense }}</td>
                                                                </tr>
                                                            @endif
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="modal-footer"> </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Modal End -->

                                
                            </h5>


                        </div>
                        <div class="col-md-2 col-sm-12 text-md-end">
                            @if ($search_cashes == !null)
                                <form method="GET" action="{{ route('expenseCalculations.index') }}">
                                    @csrf

                                    <div class="row">
                                        <div class="col-md-12 col-sm-12">
                                            <div class="form-group" id="hide_div">
                                                <label for="export_format">Export Format:</label>
                                                <select name="export_format" id="export_format" class="form-control">
                                                    <option value="xlsx">Excel (XLS)</option>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-outline-info">
                                                <i class="fa fa-file-excel" aria-hidden="true"></i> Export
                                            </button>
                                        </div>
                                    </div>

                                </form>
                            @endif
                        </div>
                    </div>


                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <!--  Table goes here id="datatablesSimple" -->
                    <table class="table table-bordered table-hover" id="myTable">
                        <thead>
                            <tr>

                                <th>Sl#</th>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Category Name</th>
                                <th>Category Types</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expenseCalculations as $cash)
                                <tr>

                                    <td>
                                        <button type="button" class="btn btn-outline-info" data-toggle="modal"
                                            data-target="#exampleModalCenter{{ $cash->id }}">
                                            {{ $cash->id }}
                                        </button>
                                    </td>
                                    <td>{{ $cash->date ? \Carbon\Carbon::parse($cash->date)->format('d-M-Y') : '' }}
                                    </td>
                                    <td>{{ $cash->name }}</td>
                                    <td>{{ $cash->types }}</td>
                                    <td>
                                        @php
                                            $category =
                                                App\Models\Category::find($cash->category_id) == null
                                                    ? ''
                                                    : App\Models\Category::find($cash->category_id)->name;
                                        @endphp
                                        {{ $category }}
                                    </td>
                                    <td>{{ $cash->amount }}</td>
                                    <td>

                                        <a type="button" class="btn btn-outline-info" data-bs-toggle="modal"
                                            data-bs-target="#CashEditModal{{ $cash->id }}">Edit</a>


                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="alert alert-danger">
                                            No Data Found
                                        </div>
                                    </td>
                                </tr>
                            @endforelse

                        </tbody>
                    </table>

                    {{ $expenseCalculations->links() }}







                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->


            <!-- /.card -->
        </div>
        <!-- /.col -->
        </div>
        <!-- /.row -->
        </div>
        <!-- /.container-fluid -->
    </section>

    <!--  start model for Data Entry -->




    <div class="modal fade" id="CashEntryModal" data-bs-backdrop="static" tabindex="-1"
        aria-labelledby="CashEntryModal" aria-hidden="true">
        <div class="modal-dialog modal-lg" style="min-width:90%;">
            <div class="modal-content" style="background-color: rgba(0,0,0,0.5); min-width:90%;">
                <div class="modal-header" style="background: rgba(0, 0, 0, 0.5); color: #f1f1f1; min-width:90%;">
                    <h5 class="modal-title text-center" id="CashEntryModal"> Data Entry</h5>
                    <button type="button" class="btn btn-light btn-close" data-bs-dismiss="modal" aria-label="Close"
                        style="background-color: white; border-color: white; color: black;"
                        onmouseover="this.classList.add('btn-danger')"
                        onmouseout="this.classList.remove('btn-danger')"></button>

                </div>
                <div class="modal-body" style="background: rgba(0, 0, 0, 0.5); color: #f1f1f1; min-width:90%;">
                    <!-- Your x-guest-layout code here -->

                    <div class="container-fluid justify-content-center"
                        style="background: rgba(0, 0, 0, 0.5); color: #f1f1f1; min-width:90%;">
                        <div class="row justify-content-between">
                            <div class="col-md-12">
                                <div>
                                    <div class=" p-4 p-md-5">
                                        <!-- Validation Errors -->
                                        <x-auth-validation-errors class="mb-4" :errors="$errors" />

                                        <form method="POST" action="{{ route('expenseCalculations.store') }}"
                                            enctype="multipart/form-data">
                                            @csrf
                                            <!-- row-1 start -->
                                            <div class="row justify-content-between">
                                                <!--  Date start  -->
                                                <div class="col-md-2">
                                                    <div class="mt-3">
                                                        <x-label for="date" :value="__('Date')" class="ml-2 " />
                                                        <div class="row">
                                                            <div class="col-12">
                                                                <input type="date" name="date[]"
                                                                    class="form-control" value="{{ date('Y-m-d') }}">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!--  Date end -->

                                                <!--  Category start -->
                                                <div class="col-md-2">
                                                    <div class="mt-3 ">
                                                        <x-label for="category_id" :value="__('Category')" class="ml-2 " />
                                                        <div class="row">
                                                            <div class="col-12">
                                                                <select name="category_id[]" class="form-control">
                                                                    <option value="">Select Category</option>
                                                                    @foreach ($categories as $category)
                                                                        <option value="{{ $category->id }}">
                                                                            {{ $category->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!--  Category end -->
                                                <!-- Name start -->
                                                <div class="col-md-2">
                                                    <div class="mt-3">
                                                        <x-label for="name" :value="__('Name')" class="ml-2" />
                                                        <div class="row">
                                                            <div class="col-12">
                                                                @php
                                                                    $nameList = App\Models\ExpenseCalculation::select(
                                                                        'name',
                                                                    )
                                                                        ->distinct()
                                                                        ->pluck('name');

                                                                    $nameList = $nameList->sortByDesc(function ($name) {
                                                                        return App\Models\ExpenseCalculation::where(
                                                                            'name',
                                                                            $name,
                                                                        )->count();
                                                                    });
                                                                @endphp

                                                                <input list="nameList" name="name[]"
                                                                    class="form-control" />

                                                                <datalist id="nameList">
                                                                    @foreach ($nameList as $n)
                                                                        <option value="{{ $n }}">
                                                                            {{ $n }}</option>
                                                                    @endforeach
                                                                </datalist>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Name end -->

                                                <!--  Amount start -->
                                                <div class="col-md-2">
                                                    <div class="mt-3 ">
                                                        <x-label for="amount" :value="__('Amount')" class="ml-2 " />
                                                        <div class="row">
                                                            <div class="col-12">
                                                                <x-input name="amount[]" class="form-control"
                                                                    autofocus />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!--  Amount end -->
                                                <div class="col-md-2">
                                                    <div class="mt-3 ">
                                                        <label for="types[]">HandCash Types</label>
                                                        <select class="form-control" name="types[]">
                                                            <option value="Widrows">Withdraws</option>
                                                            <option value="Save">Savings</option>

                                                        </select>
                                                    </div>
                                                </div>
                                                <!--  HandCash Types end -->
                                                <div class="col-md-2">
                                                    <div class="mt-3 ">
                                                        <label for="rules[]">Cash Rules</label>
                                                        <select class="form-control" name="rules[]">
                                                            <option value="Peti">Peti Cash</option>
                                                            <option value="City_Bank">City Bank</option>
                                                            <option value="Cash">Cash</option>
                                                            <option value="Mobile_Bkash">Bkash</option>
                                                            <option value="Mobile_Rocket">Rocket</option>
                                                            <option value="Sonali_Bank_Gulshan">Sonali Bank Gulshan
                                                            </option>
                                                            <option value="Sonali_Bank_Tongi">Sonali Bank Tongi
                                                            </option>
                                                            <option value="City_Bank_Islamic">City Bank Islamic
                                                            </option>
                                                            <option value="DBBL">Dutch Bangla Bank</option>
                                                            <option value="PBL">Prime Bank Ltd</option>
                                                            <option value="Mobile_Nagad">Nagad</option>
                                                            <option value="loan">Loan To Other </option>
                                                            <option value="CreditCard">Credit Card</option>
                                                            <option value="FD">FD</option>
                                                            <option value="DPS">DPS</option>
                                                            <option value="MyLoan">MyLoan</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <!--  Cash Rules end -->
                                            </div>
                                            <!--  row-1 end -->

                                            <!--  Dynamic input fields -->
                                            <div id="dynamic-fields-container"></div>

                                            <!--  Add and remove buttons -->
                                            <div class="mt-3">
                                                <button type="button" id="add-field-btn" class="btn btn-primary">Add
                                                    Field</button>
                                                <button type="button" id="remove-field-btn"
                                                    class="btn btn-danger">Remove Field</button>
                                            </div>

                                            <!--  Submit button -->
                                            <div style="margin-top: 50px;">
                                                <button type="submit"
                                                    class="btn btn-outline-light ml-2 mx-auto d-block">Create</button>
                                            </div>
                                        </form>

                                        <script>
                                            // JavaScript/jQuery code to handle dynamic fields

                                            // Function to add a new set of input fields
                                            function addField() {
                                                var container = document.getElementById('dynamic-fields-container');

                                                var fieldTemplate = `
            <div class="row justify-content-between mt-3">
                <div class="col-md-2">
                    <div class="mt-3">
                        <x-label for="date" :value="__('Date')" class="ml-2" />
                        <div class="row">
                            <div class="col-12">
                                <input type="date" name="date[]" class="form-control" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mt-3 ">
                        <x-label for="category_id" :value="__('Category')" class="ml-2" />
                        <div class="row">
                            <div class="col-12">
                                <select name="category_id[]" class="form-control">
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mt-3">
                        <x-label for="name" :value="__('Name')" class="ml-2" />
                        <div class="row">
                            <div class="col-12">
                                <x-input name="name[]" class="form-control" autofocus />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mt-3 ">
                        <x-label for="amount" :value="__('Amount')" class="ml-2" />
                        <div class="row">
                            <div class="col-12">
                                <x-input name="amount[]" class="form-control" autofocus />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mt-3 ">
                    <label for="types[]">HandCash Types</label>
                    <select class="form-control" name="types[]">
                         <option value="Widrows">Withdraws</option>
                        <option value="Save">Savings</option>                      
                    </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mt-3 ">
                    <label for="rules[]">Cash Rules</label>
                    <select class="form-control" name="rules[]">
                         <option value="Peti">Peti Cash</option>
                                                        <option value="City_Bank">City Bank</option>
                                                        <option value="Cash">Cash</option>
                                                        <option value="Mobile_Bkash">Bkash</option>
                                                        <option value="Mobile_Rocket">Rocket</option>
                                                        <option value="Sonali_Bank_Gulshan">Sonali Bank Gulshan
                                                            </option>
                                                            <option value="Sonali_Bank_Tongi">Sonali Bank Tongi</option>
                                                            <option value="City_Bank_Islamic">City Bank Islamic</option>
                                                        <option value="DBBL">Dutch Bangla Bank</option>
                                                        <option value="PBL">Prime Bank Ltd</option>
                                                        <option value="Mobile_Nagad">Nagad</option>
                                                        <option value="loan">Loan To Other </option>
                                                        <option value="CreditCard">Credit Card</option>
                                                        <option value="FD">FD</option>
                                                        <option value="DPS">DPS</option>
                                                        <option value="MyLoan">MyLoan</option>
                    </select>
                </div>
                </div>
            </div>
        `;

                                                container.insertAdjacentHTML('beforeend', fieldTemplate);
                                            }

                                            // Function to remove the last set of input fields
                                            function removeField() {
                                                var container = document.getElementById('dynamic-fields-container');
                                                var fields = container.getElementsByClassName('row');

                                                if (fields.length > 1) {
                                                    fields[fields.length - 1].remove();
                                                }
                                            }

                                            // Add event listeners to the buttons
                                            document.getElementById('add-field-btn').addEventListener('click', addField);
                                            document.getElementById('remove-field-btn').addEventListener('click', removeField);
                                        </script>

                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>




                </div>
            </div>
        </div>
    </div>



    <!--  end model for Data Entry -->

    <!--  start model for Data Edit -->
    @foreach ($expenseCalculations as $cash)
        <div class="modal fade" id="CashEditModal{{ $cash->id }}" tabindex="-1"
            aria-labelledby="CashEditModal{{ $cash->id }}Label" aria-hidden="true" data-bs-backdrop="static"
            data-bs-keyboard="false">
            <div class="modal-dialog modal-xl" style="min-width:90%;">
                <div class="modal-content" style="background-color: rgba(0,0,0,0.5); min-width:90%;">
                    <div class="modal-header" style="background: rgba(0, 0, 0, 0.5); color: #f1f1f1; min-width:90%;">
                        <h5 class="modal-title text-center" id="CashEditModalLabel">Data Edit</h5>
                        <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Close</button>
                    </div>
                    <div class="modal-body" style="background: rgba(0, 0, 0, 0.5); color: #f1f1f1; min-width:90%;">
                        <!-- Your x-guest-layout code here -->


                        <div class="container-fluid justify-content-center"
                            style="background: rgba(0, 0, 0, 0.5); color: #f1f1f1; min-width:90%;">
                            <div class="row justify-content-between">
                                <div class="col-md-12">
                                    <div>
                                        <div class=" p-4 p-md-5">
                                            <!-- Validation Errors -->
                                            <x-auth-validation-errors class="mb-4" :errors="$errors" />

                                            <form method="POST"
                                                action="{{ route('expenseCalculations.update', $cash) }}"
                                                enctype="multipart/form-data">
                                                @csrf
                                                @method('PUT')

                                                <!--  Rest of your form elements -->
                                                <!--  row-1 start -->
                                                <div class="row justify-content-between">
                                                    <!--  Date start  -->
                                                    <div class="col-md-3">


                                                        <div class="mt-3">

                                                            <x-label for="date" :value="__('Date')"
                                                                class="ml-2 " />

                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <input type="date" name="date"
                                                                        id="date" class="form-control"
                                                                        value="{{ $cash->date }}">
                                                                </div>

                                                            </div>
                                                        </div>

                                                    </div>
                                                    <!--  Date end -->

                                                    <!--  Category start -->
                                                    <div class="col-md-3">

                                                        <div class="mt-3 ">
                                                            <x-label for="category_id" :value="__('Category')"
                                                                class="ml-2 " />

                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <select name="category_id" id="category_id"
                                                                        class="form-control">
                                                                        <option value="">Select Category</option>
                                                                        @foreach ($categories as $category)
                                                                            <option value="{{ $category->id }}"
                                                                                @if ($category->id == $cash->category_id) selected @endif>
                                                                                {{ $category->name }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!--  Category end -->
                                                    <!--  Name start -->
                                                    <div class="col-md-3">


                                                        <div class="mt-3">

                                                            <x-label for="name" :value="__('Name')"
                                                                class="ml-2 " />

                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <input type="text" name="name"
                                                                        id="name" value="{{ $cash->name }}"
                                                                        class="form-control">
                                                                </div>

                                                            </div>
                                                        </div>



                                                    </div>
                                                    <!--  Name end -->

                                                    <!--  Amount start -->
                                                    <div class="col-md-3">

                                                        <div class="mt-3 ">
                                                            <x-label for="amount" :value="__('Amount')"
                                                                class="ml-2 " />
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <input type="number" name="amount"
                                                                        id="amount" value="{{ $cash->amount }}"
                                                                        class="form-control">
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!--  Amount end -->

                                                </div>
                                                <!--  row-1 end -->
                                                <div style="margin-top:50px;">

                                                    <button type="submit"
                                                        class="btn btn-outline-light ml-2 mx-auto d-block">Save</button>
                                                </div>

                                            </form>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!--  end model for Data Edit -->

    <!--  start model for Data details -->

    @foreach ($expenseCalculations as $cash)
        <div class="modal fade" id="exampleModalCenter{{ $cash->id }}" tabindex="-1" data-bs-backdrop="static"
            aria-labelledby="registerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" style="min-width:90%;">
                <div class="modal-content" style="background-color: rgba(0,0,0,0.5); min-width:90%;">
                    <div class="modal-header" style="background: rgba(0, 0, 0, 0.5); color: #f1f1f1; min-width:90%;">
                        <h5 class="modal-title text-center" id="registerModalLabel"> Data Show of
                            {{ $cash->serial_number }}</h5>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">X</button>

                    </div>
                    <div class="modal-body" style="background: rgba(0, 0, 0, 0.5); color: #f1f1f1; min-width:90%;">
                        <div class="container-fluid">
                            <!--  row-1 start -->
                            <div class="row justify-content-between">
                                <!--  Date start  -->
                                <div class="col-md-3">


                                    <div class="mt-3">

                                        <x-label for="date" :value="__('Date')" class="ml-2 " />

                                        <div class="row">
                                            <div class="col-12">
                                                <input type="date" name="date" id="date"
                                                    class="form-control" value="{{ $cash->date }}" readonly>
                                            </div>

                                        </div>
                                    </div>

                                </div>
                                <!--  Date end -->

                                <!--  Category start -->
                                <div class="col-md-3">

                                    <div class="mt-3 ">
                                        <x-label for="category_id" :value="__('Category')" class="ml-2 " />

                                        <div class="row">
                                            <div class="col-12">
                                                <select name="category_id" id="category_id" class="form-control">
                                                    <option value="">Select Category</option>
                                                    @foreach ($categories as $category)
                                                        <option value="{{ $category->id }}"
                                                            @if ($category->id == $cash->category_id) selected @endif readonly>
                                                            {{ $category->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <!--  Category end -->
                                <!--  Name start -->
                                <div class="col-md-3">


                                    <div class="mt-3">

                                        <x-label for="name" :value="__('Name')" class="ml-2 " />

                                        <div class="row">
                                            <div class="col-12">
                                                <x-input name="name" id="name" class="form-control" autofocus
                                                    value="{{ $cash->name }}" readonly />
                                            </div>

                                        </div>
                                    </div>



                                </div>
                                <!--  Name end -->

                                <!--  Amount start -->
                                <div class="col-md-3">

                                    <div class="mt-3 ">
                                        <x-label for="amount" :value="__('Amount')" class="ml-2 " />

                                        <div class="row">
                                            <div class="col-12">
                                                <x-input name="amount" id="amount" class="form-control" autofocus
                                                    value="{{ $cash->amount }}" readonly />
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <!--  Amount end -->

                            </div>
                            <!--  row-1 end -->
                        </div>


                    </div>
                    <div class="modal-footer">

                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <a type="button" class="btn btn-outline-info" data-bs-toggle="modal"
                            data-bs-target="#CashEditModal{{ $cash->id }}" data-bs-dismiss="modal"
                            onclick="closeAndShowModal('{{ $cash->id }}')">Edit</a>





                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!--  End model for Data details -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // Single Delete
            $('.delete-button').on('click', function() {
                var cashId = $(this).data('cash-id');

                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This action cannot be undone!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteCash(cashId);
                    }
                });
            });

            // Delete Selected
            $('#delete-selected').on('click', function() {
                var selectedCashes = $('input[name="selected_cashes[]"]:checked').map(function() {
                    return $(this).val();
                }).get();

                if (selectedCashes.length > 0) {
                    Swal.fire({
                        title: 'Are you sure?',
                        text: 'This action cannot be undone!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, delete them all!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            deleteCashes(selectedCashes);
                        }
                    });
                } else {
                    Swal.fire('No data Selected', 'Please select at least one data to delete.', 'warning');
                }
            });

            function deleteCash(cashId) {
                var deleteForm = $('#delete-form-' + cashId);
                deleteForm.submit();
            }

            function deleteCashes(cashIds) {
                var totalDeleted = 0;
                var deletedCount = 0;

                cashIds.forEach(function(cashId) {
                    var deleteForm = $('#delete-form-' + cashId);
                    var csrfToken = deleteForm.find('input[name="_token"]').val();

                    $.ajax({
                        url: deleteForm.attr('action'),
                        type: 'POST',
                        data: {
                            _method: 'DELETE',
                            _token: csrfToken
                        },
                        success: function() {
                            deletedCount++;
                            if (deletedCount === totalDeleted) {
                                Swal.fire({
                                    title: 'Deleted!',
                                    text: 'Total ' + deletedCount +
                                        ' cash(es) have been deleted.',
                                    icon: 'success'
                                }).then(() => {
                                    location.reload(); // Reload the page
                                });
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'An error occurred while deleting the cash(es).',
                                'error');
                        }
                    });

                    totalDeleted++;
                });
            }
        });

        $(document).ready(function() {
            $("#hide_div").hide();
        });



        function closeAndShowModal(cashId) {
            closePreviousModal(); // Close previous modal

            // Show the new modal using its ID
            var newModal = document.querySelector('#CashEditModal' + cashId);
            var modalInstance = new bootstrap.Modal(newModal);
            modalInstance.show();
        }

        function closePreviousModal() {
            // Find the previous modal using its class and trigger the click event on the "Close" button
            var previousModal = document.querySelector('.modal.show');
            if (previousModal) { // Check if previousModal exists
                var closeButton = previousModal.querySelector('[data-dismiss="modal"]');
                if (closeButton) { // Check if closeButton exists
                    if (typeof closeButton.click === 'function') { // Check if closeButton.click is a function
                        closeButton.click();
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function validateForm() {
            var incCategory = document.getElementById("types").value;
            var entryDateStart = document.getElementById("entry_date_start").value;
            var entryDateEnd = document.getElementById("entry_date_end").value;

            if (incCategory === "" && companyId === "" && entryDateStart === "" && entryDateEnd === "") {
                Swal.fire({
                    title: "Warning",
                    text: "Please fill in at least one field to search",
                    icon: "warning",
                    confirmButtonColor: "#3085d6",
                    confirmButtonText: "OK"
                });
            } else {
                // Submit the form or perform further processing
            }
        }
    </script>


</x-backend.layouts.master>
