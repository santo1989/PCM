<x-guest-layout>
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap" rel="stylesheet" />
    <!-- MDB -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.0.1/mdb.min.css" rel="stylesheet" />

    <section class="gradient-custom"
        style="background-image: linear-gradient(#40c47c,#40c47c,#40c47c); background-size: cover; background-repeat: repeat; height:100vmin;">
        <div class="container py-5 justify-content-center">
            <div class="row justify-content-between">
                <div class="col-10">
                    <div class="card shadow-2-strong card-registration" style="border-radius: 15px;">
                        <div class="card-body p-4 p-md-5">
                            {{-- <h3 class="mb-4 pb-2 pb-md-0 mb-md-5">Registration Form</h3> --}}

                            <!-- Validation Errors -->
                            <x-auth-validation-errors class="mb-4" :errors="$errors" />

                            <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                                @csrf

                                <div class="flex justify-content-between mt-3">
                                    <div class="w-1/2">
                                        {{-- Open Space Start --}}
                                          <div style="height:115px;">
                                            <h3 class="mb-4 pb-2 pb-md-0 mb-md-5">Registration Form</h3>

                                        </div>
                                        {{-- Open Space End --}}
                                        {{-- Division start  --}}
                                        @php
                                            $divisions = App\Models\Division::all();
                                        @endphp
                                        <div class="mt-3">

                                            <x-label for="division_id" :value="__('Division')" class="ml-2 " />

                                            <div class="row">
                                                <div class="col-12">
                                                    <select name="division_id" id="division_id"
                                                        class="block mt-1 ml-2 w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                        required>
                                                        <option value="">Select Division</option>
                                                        @foreach ($divisions as $division)
                                                            <option value="{{ $division->id }}">{{ $division->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                            </div>

                                        </div>

                                        {{-- Division end --}}

                                    </div>
                                    <div class="w-1/2">
                                        {{-- image preview start--}}
                                        <div class="mt-3 ">
                                            <img class="img-thumbnail mx-auto d-block" id="output" height="100px"
                                                width="100px" />
                                            <script>
                                                var loadFile = function(event) {
                                                    var output = document.getElementById('output');
                                                    output.src = URL.createObjectURL(event.target.files[0]);
                                                    output.onload = function() {
                                                        URL.revokeObjectURL(output.src) // free memory
                                                    }
                                                };
                                            </script>

                                        </div>
                                        {{-- image preview end --}}
                                        {{-- image upload --}}
                                        <div class="mt-3 mr-4 ml-2">
                                            <x-label for="imageUpload" :value="__('Picture')" />

                                            <x-input id="image" class="block mt-1 ml-2  w-full" type="file"
                                                name="picture" :value="old('picture')" required autofocus accept="image/*"
                                                onchange="loadFile(event)" />

                                        </div>
                                        {{-- image upload end --}}

                                    </div>
                                </div>

                                <div class="flex justify-content-between mt-3">
                                    <div class="w-1/2">
                                        {{-- employee id start --}}

                                        <div class="mt-3 ">
                                            <x-label for="emp_id" :value="__('Employee ID')" />

                                            <x-input id="emp_id" class="block mt-1 w-full" type="text"
                                                name="emp_id" :value="old('emp_id')" required autofocus />

                                        </div>
                                        {{-- employee id end  --}}
                                        {{-- name start --}}
                                        <div class="mt-3">
                                            <x-label for="name" :value="__('Name')" />

                                            <x-input id="name" class="block mt-1 w-full" type="text"
                                                name="name" :value="old('name')" required autofocus />

                                        </div>
                                        {{-- name end --}}

                                        <!-- Email Address -->
                                        <div class="mt-3">
                                            <x-label for="email" :value="__('Email')" />

                                            <x-input id="email" class="block mt-1 w-full" type="email"
                                                name="email" :value="old('email')" required />
                                        </div>

                                        <!-- Password -->
                                        <div class="mt-3">
                                            <x-label for="password" :value="__('Password')" />

                                            <x-input id="password" class="block mt-1 w-full" type="password"
                                                name="password" required autocomplete="new-password" />
                                        </div>




                                    </div>

                                    <div class="w-1/2">

                                        {{-- Company start  --}}

                                        <div class="mt-3">

                                            <x-label for="company_id" :value="__('Company')" class="ml-2 " />

                                            <div class="row">
                                                <div class="col-12">
                                                    <select name="company_id" id="company_id"
                                                        class="block mt-1 ml-2 w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                        required>
                                                        <option value="">Select Division first</option>

                                                    </select>
                                                </div>

                                            </div>
                                        </div>

                                        {{-- Company end --}}

                                        {{-- department start  --}}

                                        <div class="mt-3">

                                            <x-label for="department_id" :value="__('Department')" class="ml-2 " />
                                            <div class="row">
                                                <div class="col-12">
                                                    <select name="department_id" id="department_id"
                                                        class="block mt-1 ml-2 w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                        required>
                                                        <option value="">Select Company first</option>

                                                    </select>

                                                </div>

                                            </div>

                                        </div>

                                        {{-- department end --}}

                                        {{-- designation start  --}}

                                        <div class="mt-3">
                                            <div class="row">
                                                <x-label for="designation_id" :value="__('Designation')" class="ml-2 " />
                                                <div class="col-12">
                                                    <select name="designation_id" id="designation_id"
                                                        class="block mt-1 ml-2  w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                        required>
                                                        <option value="">Select Division first</option>
                                                    </select>
                                                </div>

                                            </div>

                                        </div>

                                        {{-- designation end  --}}

                                        {{-- <!-- Confirm Password --> --}}
                                        <div class="mt-3">
                                            <div class="row">
                                                <x-label for="password_confirmation" :value="__('Confirm Password')"
                                                    class="ml-2 " />
                                                <div class="col-12">
                                                    <x-input id="password_confirmation"
                                                        class="block mt-1 ml-2  w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                        type="password" name="password_confirmation" required />
                                                </div>

                                            </div>

                                        </div>
                                        {{-- Confirm Password end --}}
                                    </div>

                                </div>
                                <div class="flex justify-content-between mt-3">
                                    <div class="w-1/2">

                                        {{-- mobile --}}
                                        <div class="mt-3">
                                            <x-label for="mobile" :value="__('Mobile')" />

                                            <x-input id="mobile" class="block mt-1 w-full" type="text"
                                                name="mobile" :value="old('mobile')" required autofocus />

                                        </div>
                                        {{-- mobile end --}}
                                    </div>
                                    <div class="w-1/2">

                                        {{-- Dob start --}}

                                        <div class="mt-3">
                                            <x-label for="dob" :value="__('Date of Birth')" class="ml-2 " />

                                            <x-input id="dob" class="block mt-1 ml-2  w-full" type="date"
                                                name="dob" :value="old('dob')" required autofocus />

                                        </div>

                                        {{-- Dob end --}}

                                    </div>
                                </div>

                                <div class="flex justify-content-between mt-3">

                                    <div class="w-1/2">

                                        {{-- Joining Date start --}}

                                        <div class="mt-3">
                                            <x-label for="joining_date" :value="__('Joining Date')" />

                                            <x-input id="joining_date" class="block mt-1 w-full" type="date"
                                                name="joining_date" :value="old('joining_date')" required autofocus />

                                        </div>

                                        {{-- Joining Date end --}}

                                    </div>

                                    <div class="w-1/2">

                                        <div style="margin-top:50px;">

                                            <button type="submit"
                                                class="btn btn-outline-primary ml-2 mx-auto d-block">Submit</button>
                                        </div>

                                        {{-- <div class="mt-3">

                                            <button type="submit"
                                                class="btn btn-outline-primary ml-2 mx-auto d-block">Submit</button>
                                        </div> --}}
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>

            </div>
            {{-- <div class="col-2 align-self-end">
                <p> <img src="{{ asset('images/assets/logo.png') }}" alt="" heigt=350px; width=150px;
                        class="rounded float-end">
                </p>
            </div> --}}

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

        @push('css')
            <style>
                .gradient-custom {
                    /* fallback for old browsers */
                    background: #f093fb;

                    /* Chrome 10-25, Safari 5.1-6 */
                    background: -webkit-linear-gradient(to bottom right, rgba(240, 147, 251, 1), rgba(245, 87, 108, 1));

                    /* W3C, IE 10+/ Edge, Firefox 16+, Chrome 26+, Opera 12+, Safari 7+ */
                    background: linear-gradient(to bottom right, rgba(240, 147, 251, 1), rgba(245, 87, 108, 1));


                }

                .card-registration .select-input.form-control[readonly]:not([disabled]) {
                    font-size: 1rem;
                    line-height: 2.15;
                    padding-left: .75em;
                    padding-right: .75em;
                }

                .card-registration .select-arrow {
                    top: 13px;
                }
            </style>
        @endpush

        @push('js')
            <!-- MDB -->
            <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.0.1/mdb.min.js"></script>

            {{-- jquary 3.5.1 --}}
            <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>


            <script>
                $(document).ready(function() {
                    $('.dob').datepicker({
                        format: 'dd/mm/yyyy',
                        uiLibrary: 'bootstrap 5',
                        changeMonth: true,
                        changeYear: true,
                        yearRange: "-80:-18",
                        maxDate: new Date(),


                    });
                });
            </script>
        @endpush
    </section>
</x-guest-layout>
