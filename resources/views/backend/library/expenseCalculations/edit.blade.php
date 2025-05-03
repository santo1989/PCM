<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Issue Entry
    </x-slot>


    <x-backend.layouts.elements.errors />
    <form action="{{ route('issue_entries.update', ['issue_entry' => $issue_entry->id]) }}" method="post"
        enctype="multipart/form-data">
        <div>
            @csrf
            @method('put')
            {{-- Issue Riser Info --}}
            <fieldset>
                <legend class="text-center">Issue Riser Info:</legend>
                <br>
                <hr />
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            @php
                                $divisions = App\Models\Division::all();
                            @endphp
                            <label>Division</label>
                            <select name="division_id" id="division_id" class="form-select" >
                                @foreach ($divisions as $division)
                                    <option value="{{ $division->id }}"
                                        {{ $division->id == $issue_entry->division_id ? 'selected' : '' }}>
                                        {{ $division->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Company</label>
                            <select name="company_id" id="company_id" class="form-select">
                                <option value="{{ $issue_entry->company_id }}">{{ $issue_entry->company->name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Department</label>
                            <select name="department_id" id="department_id" class="form-select">
                                <option value="{{ $issue_entry->department_id }}">{{ $issue_entry->department->name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <x-backend.form.input name="issue_reporter" type="text" label="Issue Reporter"
                                value="{{ $issue_entry->issue_reporter }}" />
                        </div>
                    </div>
                </div>
            </fieldset>
            <br>
            <hr />
            {{-- Issue Riser Info --}}
            {{-- Issue Info --}}
            <fieldset>
                <legend class="text-center">Issue Info:</legend>
                <br>
                <hr />
                <div class="row">
                    <div class="col-md-3">
                        <x-backend.form.input name="assign_date" type="date" label="Issue Date"
                            value="{{ $issue_entry->assign_date }}" />
                    </div>

                    <div class="col-md-3">
                        <label>Issue Type</label>
                        <select name="issue_type" id="issue_type" class="form-select">
                            <option value="Self" {{ $issue_entry->issue_type == 'Self' ? 'selected' : '' }}>Self
                            </option>
                            <option value="Vendor" {{ $issue_entry->issue_type == 'Vendor' ? 'selected' : '' }}>Vendor
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Issue Priority</label>
                        <select name="issue_priority" id="issue_priority" class="form-select">
                            <option value="Low" {{ $issue_entry->issue_priority == 'Low' ? 'selected' : '' }}>Low
                            </option>
                            <option value="Medium" {{ $issue_entry->issue_priority == 'Medium' ? 'selected' : '' }}>
                                Medium</option>
                            <option value="High" {{ $issue_entry->issue_priority == 'High' ? 'selected' : '' }}>High
                            </option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <x-backend.form.input name="due_date" type="date" label="Delivery Date"
                            value="{{ $issue_entry->due_date }}" />
                        </select>
                    </div>
                </div>
            </fieldset>
            <br>
            <hr />
            {{-- Issue  Info --}}
            {{-- Issue Entry --}}
            <fieldset>
                <legend class="text-center">Issue Entry:</legend>
                <br>
                <hr />
                <div class="row">
                    <div class="col-md-3">
                        <x-backend.form.input name="subject" type="text" label="Issue subject"
                            value="{{ $issue_entry->subject }}" />
                    </div>

                    <div class="col-md-6">
                        <label>Issue Description</label>
                        <textarea name="description" id="description" class="form-control">{{ $issue_entry->description }}</textarea>


                    </div>
                    <div class="col-md-3">
                        <x-backend.form.input name="issue_attachment" type="file" label="Issue Attachment"
                            value="{{ $issue_entry->issue_attachment }}" />
                    </div>

                </div>
            </fieldset>
            <br>
            <hr />
            {{-- Issue Entry --}}
            {{-- Vandor Attachment --}}
            <fieldset>
                <legend class="text-center">Vandor Info Entry:</legend>
                <br>
                <hr />
                <div class="row">


                    <div class="col-md-2">

                        <x-backend.form.input name="issue_handed_over_to_vendor" type="text"
                            label="Issue Handed Over Vendor" value="{{ $issue_entry->issue_handed_over_to_vendor }}" />

                    </div>

                    <div class="col-md-3">

                        <x-backend.form.input name="issue_vendor_phage" type="text" label="Vendor Phage of Issue"
                            value="{{ $issue_entry->issue_vendor_phage }}" />

                    </div>
                    <div class="col-md-2">
                        <x-backend.form.input name="vendor_handed_over_date_to_department" type="date"
                            label="Vendor Delivery Date to Department"
                            value="{{ $issue_entry->vendor_handed_over_date_to_department }}" />
                    </div>
                    <div class="col-md-5">
                        <label>Issue Handed Over Comment</label>
                        <textarea name="vendor_handed_over_comment" id="vendor_handed_over_comment" class="form-control">{{ $issue_entry->vendor_handed_over_comment }}</textarea>
                    </div>


                </div>
            </fieldset>
            <br>
            <hr />
            <fieldset>
                <legend class="text-center">Issue Closing:</legend>
                <br>
                <hr />
                <div class="row">
                    <div class="col-md-3">
                        <label>Issue Status</label>
                        <select name="issue_status" id="issue_status" class="form-select">
                            <option value="Open" {{ $issue_entry->issue_status == 'Open' ? 'selected' : '' }}>Open
                            </option>
                            <option value="Close" {{ $issue_entry->issue_status == 'Close' ? 'selected' : '' }}>Close
                            </option>
                            <option value="Pending" {{ $issue_entry->issue_status == 'Pending' ? 'selected' : '' }}>
                                Pending</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <x-backend.form.input name="issue_closed_date" type="date" label="Issue Closed Date"
                            value="{{ $issue_entry->issue_closed_date }}" />
                    </div>
                    <div class="col-md-6">
                        <label>Issue Closing Comment</label>
                        <textarea name="issue_comment" id="issue_comment" class="form-control">{{ $issue_entry->issue_comment }}</textarea>
                    </div>

                </div>
            </fieldset>
            <div class="row">
                <div class="col-md-12">
                    <x-backend.form.saveButton>Save</x-backend.form.saveButton>
                    <a href="{{ route('issue_entries.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
                </div>
            </div>
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
