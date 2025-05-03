<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Edit Category Information
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> Category </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Category</a></li>
            <li class="breadcrumb-item active">Edit Category Information</li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>


    <x-backend.layouts.elements.errors />
    <form action="{{ route('categories.update', ['category' => $categories->id]) }}" method="post"
        enctype="multipart/form-data">
        <div class="pb-3">
            @csrf
            @method('put')


            <x-backend.form.input name="name" type="text" label="Name" :value="$categories->name" />
            <br>
            <div class="form-group">
                <label for="types">Category Types</label>
                <select class="form-control" name="types" id="types">
                    <option value="income" {{ $categories->types == 'income' ? 'selected' : '' }}>Income</option>
                    <option value="expense" {{ $categories->types == 'expense' ? 'selected' : '' }}>Expense</option>
                    <option value="saving" {{ $categories->types == 'saving' ? 'selected' : '' }}>Saving</option>
                    <option value="Loan" {{ $categories->types == 'Loan' ? 'selected' : '' }}>Loan to Other</option>
                    <option value="Return" {{ $categories->types == 'Return' ? 'selected' : '' }}>Loan Return</option>
                </select>
            </div>
            <br>

            <div class="form-group">
                <label for="rules">50/30/20 rules</label>
                <select class="form-control" name="rules" id="rules">
                    <option value="needs" {{ $categories->rules == 'needs' ? 'selected' : '' }}>50% of income: needs
                    </option>
                    <option value="wants" {{ $categories->rules == 'wants' ? 'selected' : '' }}>30% of income: wants
                    </option>
                    <option value="savings" {{ $categories->rules == 'savings' ? 'selected' : '' }}>20% of income:
                        savings</option>
                </select>
            </div>
            <br>

            <x-backend.form.saveButton>Save</x-backend.form.saveButton>
        </div>
    </form>


</x-backend.layouts.master>
