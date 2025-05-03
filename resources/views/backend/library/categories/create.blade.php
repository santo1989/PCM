<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Create Category
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> Category </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Category</a></li>
            <li class="breadcrumb-item active">Create Category</li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>


    <x-backend.layouts.elements.errors />
    <form action="{{ route('categories.store') }}" method="post" enctype="multipart/form-data">
        <div class="pb-3">
            @csrf
            <br>
            <x-backend.form.input name="name" type="text" label="Category Name" />
            <br>
            {{-- <x-backend.form.input name="types" type="text" label="Category Types" /> --}}
            <div class="form-group">
                <label for="types">Category Types</label>
                <select class="form-control" name="types" id="types">
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                    <option value="Loan">Loan to Other</option>
                    <option value="Return">Loan Return</option>

                </select>
            </div>
            <br>
            <div class="form-group">
                <label for="rules">50/30/20 rules</label>
                <select class="form-control" name="rules" id="rules">
                    <option value="needs">50% of income: needs</option>
                    <option value="wants">30% of income: wants</option>
                    <option value="savings">20% of income: savings</option>
                </select>
            </div>
            <br>
            <br>
            <x-backend.form.saveButton>Save</x-backend.form.saveButton>



        </div>
    </form>

</x-backend.layouts.master>
