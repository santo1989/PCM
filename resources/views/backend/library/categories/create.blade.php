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

    <div class="card overflow-hidden">
        <div class="p-4" style="background: var(--grad-primary);">
            <h5 class="mb-0 text-white"><i class="bi bi-tag-fill"></i> Create Category</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('categories.store') }}" method="post">
                @csrf

                <x-backend.form.input name="name" type="text" label="Category Name" required />

                <x-backend.form.select name="types" label="Category Types"
                    :options="config('finance.category_types')" />

                <x-backend.form.select name="rules" label="50/30/20 rules"
                    :options="config('finance.budget_rules')" />

                <x-backend.form.saveButton>Save</x-backend.form.saveButton>
            </form>
        </div>
    </div>

</x-backend.layouts.master>
