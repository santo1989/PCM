<x-backend.layouts.master>
    <x-slot name="pageTitle">
        User List
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> User </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('users.index') }}">User</a></li>
            <li class="breadcrumb-item active">User</li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>

    <section class="content">
        <div class="container-fluid">

            <x-backend.layouts.elements.message :message="session('message')" />

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <form method="GET" action="{{ route('users.index') }}" class="d-flex flex-wrap gap-2">
                                <input type="text" name="search" placeholder="Search by name"
                                    value="{{ request('search') }}" class="form-control" style="max-width: 220px;">

                                @php $selectedRoleIds = array_map('strval', (array) request('role_id', [])); @endphp
                                <select name="role_id[]" class="form-select select2" multiple
                                    data-placeholder="All Roles" style="min-width: 220px;">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" {{ in_array((string) $role->id, $selectedRoleIds, true) ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <button class="btn btn-sm btn-outline-secondary" type="submit">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-x-circle"></i> Clear
                                </a>
                            </form>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="datatablesSimple" class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Sl#</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Activity</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $sl = 0 @endphp
                                        @foreach ($users as $user)
                                            <tr>
                                                <td>{{ ++$sl }}</td>
                                                <td>{{ $user->name }}</td>
                                                <td>{{ $user->email }}</td>
                                                <td>{{ $user->role->name ?? 'N/A' }}</td>
                                                <td>
                                                    <form action="{{ route('users.active', ['user' => $user->id]) }}"
                                                        method="POST">
                                                        @csrf
                                                        <button
                                                            type="button"
                                                            onclick="confirmStatusToggle(this.closest('form'))"
                                                            class="btn btn-sm {{ $user->is_active ? 'btn-danger' : 'btn-success' }}">{{ $user->is_active ? 'Inactive' : 'Active' }}</button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <x-backend.form.anchor :href="route('users.show', ['user' => $user->id])" type="show" />
                                                    <x-backend.form.anchor :href="route('users.edit', ['user' => $user->id])" type="edit" />

                                                    <button type="button"
                                                        onclick="confirmDelete('{{ route('users.destroy', ['user' => $user->id]) }}')"
                                                        class="btn btn-sm btn-outline-danger">
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

    <script>
        function confirmStatusToggle(form) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'Are you sure want to change status?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, change it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

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
