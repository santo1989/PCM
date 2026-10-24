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

    <div class="card overflow-hidden">
        <div class="p-4" style="background: var(--grad-primary);">
            <h5 class="mb-0 text-white"><i class="bi bi-pencil-square"></i> Edit Category</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('categories.update', ['category' => $categories->id]) }}" method="post">
                @csrf
                @method('put')

                <x-backend.form.input name="name" type="text" label="Name" :value="$categories->name" required />

                <x-backend.form.select name="types" label="Category Types" class="select2"
                    :options="config('finance.category_types')" :selected="$categories->types" />

                <x-backend.form.select name="rules" label="50/30/20 rules" class="select2"
                    :options="config('finance.budget_rules')" :selected="$categories->rules" />

                <x-backend.form.input name="monthly_budget" type="number" step="0.01" min="0" inputmode="decimal"
                    label="Monthly budget (optional)" placeholder="e.g. 5000" :value="old('monthly_budget', $categories->monthly_budget)" />
                <div class="form-text mt-n2 mb-3">Expense categories only. You'll get an alert at {{ \App\Services\BudgetService::WARN_AT }}% and when it's exceeded.</div>

                <x-backend.form.saveButton>Save</x-backend.form.saveButton>
            </form>
        </div>
    </div>

</x-backend.layouts.master>
