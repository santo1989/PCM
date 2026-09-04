<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Category List
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> Category </x-slot>

            <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Category</a></li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>

    <section class="content">
        <div class="container-fluid">
            @if (is_null($categories) || empty($categories))
                <div class="row">
                    <div class="col-md-12 col-lg-12 col-sm-12">
                        <h1 class="text-danger"> <strong>Currently No Information Available!</strong> </h1>
                    </div>
                </div>
            @else
                <x-backend.layouts.elements.message :message="session('message')" />
                <x-backend.layouts.elements.errors />

                <div class="gradient-header mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1 text-white">Categories</h4>
                        <div class="small">Income, expense and 50/30/20 rule categories used across every entry.</div>
                    </div>
                    <a href="{{ route('categories.create') }}" class="btn btn-light">
                        <i class="bi bi-plus-circle"></i> Create Category
                    </a>
                </div>

                <x-backend.insights-panel :insights="\App\Services\InsightEngine::dashboardSummary()" />

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    @php
                                        // Client-side-only icon lookup keyed on the category's existing "types"
                                        // value — categories has no icon column, so nothing is persisted here.
                                        $categoryTypeIcon = [
                                            'INCOME' => 'bi-cash-coin text-success',
                                            'EXPENSE' => 'bi-cart-fill text-danger',
                                            'LOAN' => 'bi-arrow-left-right text-warning',
                                            'RETURN' => 'bi-arrow-left-right text-info',
                                        ];
                                    @endphp
                                    <table id="datatablesSimple" class="table table-bordered table-hover table-responsive-cards">
                                        <thead>
                                            <tr>
                                                <th>Sl#</th>
                                                <th>Name</th>
                                                <th>Types</th>
                                                <th>Rules</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $sl = 0 @endphp
                                            @foreach ($categories as $category)
                                                <tr>
                                                    <td data-label="Sl#">{{ ++$sl }}</td>
                                                    <td data-label="Name">
                                                        <i class="bi {{ $categoryTypeIcon[$category->types] ?? 'bi-tag-fill text-secondary' }} me-1"></i>
                                                        {{ $category->name }}
                                                    </td>
                                                    <td data-label="Types">{{ $category->types }}</td>
                                                    <td data-label="Rules">{{ $category->rules }}</td>
                                                    <td data-label="Actions">
                                                        <x-backend.form.anchor
                                                            :href="route('categories.edit', ['category' => $category->id])"
                                                            type="edit" />

                                                        <x-backend.form.anchor
                                                            :href="route('categories.show', ['category' => $category->id])"
                                                            type="show" />

                                                        <button type="button"
                                                            onclick="confirmDelete('{{ route('categories.destroy', ['category' => $category->id]) }}')"
                                                            class="btn btn-outline-danger my-1 mx-1 inline btn-sm">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </section>
    @endif

    <script>
        function confirmDelete(url) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.action = url;
                    form.innerHTML = `@csrf @method('delete')`;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>

</x-backend.layouts.master>
