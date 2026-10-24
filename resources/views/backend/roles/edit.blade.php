<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Edit Role
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> Role </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">Role</a></li>
            <li class="breadcrumb-item active">Edit Role</li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>

    <x-backend.layouts.elements.errors />

    <div class="card">
        <div class="card-body">
            <form action="{{ route('roles.update', ['role' => $role->id]) }}" method="post">
                @csrf
                @method('put')

                <x-backend.form.input name="name" type="text" label="Role Name" placeholder="Enter role name"
                    :value="old('name', $role->name)" required />

                <x-backend.form.saveButton>Save</x-backend.form.saveButton>
            </form>
        </div>
    </div>

</x-backend.layouts.master>
