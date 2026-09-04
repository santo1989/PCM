<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Cash Filter
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> Cash Filter </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('expenseCalculations.index') }}">Cash</a></li>
            <li class="breadcrumb-item active">Filter</li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>

    <div class="row">
        <div class="col-md-4 col-sm-12">
            <div class="card">
                <div class="card-header" style="background: var(--grad-primary); color: #fff;">
                    Filters
                </div>
                <div class="card-body">
                    <form action="{{ route('expenseCalculations.filter') }}" method="GET" id="filterForm">
                        @csrf

                        <div class="form-group mb-3">
                            <label class="form-label">Types</label>
                            @php
                                $types = App\Models\ExpenseCalculation::select('types')->distinct()->get();
                            @endphp
                            @foreach ($types as $type)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="types[]"
                                        value="{{ $type->types }}" id="filter_type_{{ $loop->index }}">
                                    <label class="form-check-label" for="filter_type_{{ $loop->index }}">{{ $type->types }}</label>
                                </div>
                            @endforeach
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">Category</label>
                            @php
                                $filterCategories = App\Models\ExpenseCalculation::select('category_id')->distinct()->get();
                            @endphp
                            @foreach ($filterCategories as $filterCategory)
                                @php
                                    $categoryName = optional(App\Models\Category::find($filterCategory->category_id))->name;
                                @endphp
                                @if ($categoryName)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="category_id[]"
                                            value="{{ $filterCategory->category_id }}" id="filter_category_{{ $filterCategory->category_id }}">
                                        <label class="form-check-label" for="filter_category_{{ $filterCategory->category_id }}">{{ $categoryName }}</label>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">From</label>
                                <input type="date" name="entry_date_start" id="entry_date_start" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label">To</label>
                                <input type="date" name="entry_date_end" id="entry_date_end" class="form-control">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8 col-sm-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="myTable">
                            <thead>
                                <tr>
                                    <th>Sl#</th>
                                    <th>Category Name</th>
                                    <th>Category Types</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $i = 1; @endphp
                                @forelse ($expenseCalculations as $cash)
                                    <tr>
                                        <td>{{ $i++ }}</td>
                                        <td>
                                            @php
                                                $category = optional(App\Models\Category::find($cash->category_id))->name;
                                            @endphp
                                            {{ $category }}
                                        </td>
                                        <td>{{ $cash->types }}</td>
                                        <td>{{ $cash->total_amount }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            <div class="alert alert-danger mb-0">No Data Found</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            $('input[type="checkbox"], #entry_date_start, #entry_date_end').on('change', function () {
                $('#filterForm').submit();
            });

            $('#filterForm').on('submit', function (e) {
                e.preventDefault();

                $.ajax({
                    url: "{{ route('expenseCalculations.filter') }}",
                    type: "GET",
                    data: $(this).serialize(),
                    success: function (response) {
                        $('#myTable tbody').html($(response).find('#myTable tbody').html());
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                    }
                });
            });
        });
    </script>
</x-backend.layouts.master>
