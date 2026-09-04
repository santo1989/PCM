<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Update Profile
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> Profile Update </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Profile Update</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>

    <x-backend.layouts.elements.errors />

    <div class="card">
        <div class="card-body">
            <form action="{{ route('users.update', ['user' => $user->id]) }}" method="post" enctype="multipart/form-data">
                @csrf
                @method('put')

                @can('Admin')
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Role</label>
                                <select name="role_id" id="role_id" class="form-select select2">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" {{ $role->id == $user->role_id ? 'selected' : '' }}>
                                            {{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                @endcan

                <div class="row">
                    <div class="col-md-6">
                        <x-backend.form.input name="name" type="text" label="Name" :value="$user->name" />
                    </div>
                    <div class="col-md-6">
                        <x-backend.form.input name="picture" type="file" label="Picture" />
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <x-backend.form.input name="mobile" type="number" label="Mobile" :value="$user->mobile" />
                    </div>
                    <div class="col-md-6">
                        <x-backend.form.input name="dob" type="date" label="Date of Birth" :value="$user->dob" />
                    </div>
                </div>

                <div class="row">
                    <div class="form-group row bg-danger text-white mx-0 py-2 rounded">
                        <label for="change_password" class="col-md-4 col-form-label">Change Password</label>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="change_password" name="change_password"
                                    value="1" {{ old('change_password') ? 'checked' : '' }}>
                                <label class="form-check-label" for="change_password">
                                    Check to change password
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row" id="password_fields"
                        style="{{ old('change_password') ? '' : 'display:none' }}">
                        <div class="col-md-6">
                            <x-backend.form.input name="password" type="password" label="Password" />
                        </div>
                        <div class="col-md-6">
                            <x-backend.form.input name="confirm_password" type="password" label="Confirm Password" />
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-save-fill"></i> Save</button>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#change_password').change(function() {
                if (this.checked) {
                    $('#password_fields').show();
                } else {
                    $('#password_fields').hide();
                }
            });
        });
    </script>

</x-backend.layouts.master>
