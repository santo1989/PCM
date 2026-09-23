<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Role List
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> Role </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">Role</a></li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>

    <section class="content">
        <div class="container-fluid">

            <x-backend.layouts.elements.message :message="session('message')" />

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('roles.create') }}">
                                <i class="bi bi-plus-circle"></i> Create
                            </a>
                            <form method="GET" action="{{ route('roles.index') }}" data-live-filter class="d-inline-flex gap-2 ms-2 align-items-center">
                                <input type="search" name="search" class="form-control form-control-sm" placeholder="Search role" value="{{ request('search') }}" autocomplete="off">
                                <x-backend.per-page :default="25" />
                            </form>
                        </div>
                        <div class="card-body" id="live-roles" data-live-region>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Sl#</th>
                                            <th>Name</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $sl = ($roles->currentPage() - 1) * $roles->perPage(); @endphp
                                        @foreach ($roles as $role)
                                            <tr>
                                                <td>{{ ++$sl }}</td>
                                                <td>{{ $role->name }}</td>
                                                <td>
                                                    <a class="btn btn-sm btn-outline-success"
                                                        href="{{ route('roles.edit', ['role' => $role->id]) }}">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </a>
                                                    <button type="button"
                                                        onclick="confirmDelete('{{ route('roles.destroy', $role->id) }}')"
                                                        class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <x-backend.pager :paginator="$roles" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
