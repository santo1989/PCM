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
    {{-- <form action="{{ route('roles.update') }}" method="post"> --}}
    <form action="{{ route('users.update', ['user' => $user->id]) }}" method="post" enctype="multipart/form-data">
        <div>
            @csrf
            @method('put')


            <div class="row">
                @can(Auth::user()->role_id = 1)
                    <div class="col-sm-6">
                        <!-- text input -->
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role_id" id="role_id" class="form-select">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" {{ $role->id == $user->role_id ? 'selected' : '' }}>
                                        {{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                @endcan



            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        @php
                            $divisions = App\Models\Division::all();
                        @endphp
                        <label>Division</label>
                        <select name="division_id" id="division_id" class="form-select">
                            @foreach ($divisions as $division)
                                <option value="{{ $division->id }}"
                                    {{ $division->id == $user->division_id ? 'selected' : '' }}>
                                    {{ $division->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Company</label>
                        <select name="company_id" id="company_id" class="form-select">
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}"
                                    {{ $company->id == $user->company_id ? 'selected' : '' }}>
                                    {{ $company->name }}</option>
                            @endforeach

                        </select>
                    </div>
                </div>
                <div class="col-md-4">

                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id" id="department_id" class="form-select">
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}"
                                    {{ $department->id == $user->department_id ? 'selected' : '' }}>
                                    {{ $department->name }}</option>
                            @endforeach

                        </select>
                    </div>


                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Designation</label>
                        <select name="designation_id" id="designation_id" class="form-select">
                            @foreach ($designations as $designation)
                                <option value="{{ $designation->id }}"
                                    {{ $designation->id == $user->designation_id ? 'selected' : '' }}>
                                    {{ $designation->name }}</option>
                            @endforeach

                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <x-backend.form.input name="joining_date" type="date" label="Joining Date" :value="$user->joining_date" />
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <x-backend.form.input name="name" type="text" label="Name" :value="$user->name" />
                </div>
                <div class="col-md-6">
                    <x-backend.form.input name="picture" type="file" label="Picture" :value="$user->picture" />
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
                <div class="form-group row bg-danger text-white">
                    <label for="change_password" class="col-md-4 col-form-label text-md-right">Change Password</label>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="change_password" name="change_password"
                                value="1" {{ old('change_password') ? 'checked' : '' }}>
                            <label class="form-check-label " for="change_password">
                                Check to change password
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group row" id="password_fields"
                    style="{{ old('change_password') ? '' : 'display:none' }}">
                    <div class="col-md-6">
                        <x-backend.form.input name="password" type="password" label="Password" :value="encrypt($user->password)" />
                    </div>
                    <div class="col-md-6">
                        <x-backend.form.input name="confirm_password" type="password" label="Confirm Password"
                            :value="$user->confirm_password" />
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

            </div>

            








            {{-- style="background-image: linear-gradient(#40c47c,#40c47c,#40c47c);display:inline;margin-left: 33px" --}}

        </div>
        <button type="submit" class="btn btn-outline-info btn-sm"><i class="bi bi-save-fill"></i>Save</button>
        </div>
    </form>
    <div class="pb-3">
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#division_id').on('change', function() {
                var divisionId = $(this).val();

                if (divisionId) {
                    $.ajax({
                        url: '/get-company-designation/' + divisionId,
                        type: 'GET',
                        dataType: 'json',
                        success: function(data) {
                            console.log(data);
                            const companySelect = $('#company_id');
                            const designationSelect = $('#designation_id');

                            companySelect.empty();
                            companySelect.append('<option value="">Select Company</option>');
                            $.each(data.company, function(index, company) {
                                companySelect.append(
                                    `<option value="${company.id}">${company.name}</option>`
                                );
                            });

                            designationSelect.empty();
                            designationSelect.append(
                                '<option value="">Select Designation</option>');
                            $.each(data.designations, function(index, designation) {
                                designationSelect.append(
                                    `<option value="${designation.id}">${designation.name}</option>`
                                );
                            });
                        },
                        error: function(xhr, status, error) {
                            console.error(error);
                        }
                    });
                } else {
                    alert('Select a division first for company and designation name.');
                }
            });
        });

        $(document).ready(function() {
            $('#company_id').on('change', function() {
                var company_id = $(this).val();

                if (company_id) {
                    $.ajax({
                        url: '/get-department/' + company_id,
                        type: 'GET',
                        dataType: 'json',
                        success: function(data) {
                            console.log(data);
                            const companySelect = $('#department_id');

                            companySelect.empty();
                            companySelect.append('<option value="">Select Department</option>');
                            $.each(data.departments, function(index, departments) {
                                companySelect.append(
                                    `<option value="${departments.id}">${departments.name}</option>`
                                );
                            });
                        },
                        error: function(xhr, status, error) {
                            console.error(error);
                        }
                    });
                } else {
                    alert('Select a Company first for Department name.');
                }
            });
        });
    </script>

</x-backend.layouts.master>
