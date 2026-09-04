<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Create HandCash
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> HandCash </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('handCashes.index') }}">HandCash</a></li>
            <li class="breadcrumb-item active">Create HandCash</li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>

    <x-backend.layouts.elements.errors />

    <div class="card">
        <div class="card-body">
            <form action="{{ route('handCashes.store') }}" method="post">
                @csrf

                <div id="dynamic-inputs">
                    <div class="row form-group align-items-end">
                        <div class="col-md-2">
                            <x-backend.form.input name="date[]" type="date" label="Date" value="{{ date('Y-m-d') }}" />
                        </div>
                        <div class="col-md-2">
                            <x-backend.form.autocomplete-input name="name[]" label="HandCash Name"
                                model="App\Models\HandCash" column="name" />
                        </div>
                        <div class="col-md-2">
                            <x-backend.form.input name="amount[]" type="number" step="0.01" label="Amount" />
                        </div>
                        <div class="col-md-2">
                            <x-backend.form.select name="types[]" label="HandCash Types" class="select2"
                                :options="config('finance.handcash_types')" />
                        </div>
                        <div class="col-md-2">
                            <x-backend.form.select name="rules[]" label="Cash Rules" class="select2"
                                :options="config('finance.handcash_rules')" />
                        </div>
                        <div class="col-md-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addInput()"><i class="bi bi-plus-lg"></i> Add</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeInput(this)"><i class="bi bi-dash-lg"></i> Remove</button>
                        </div>
                    </div>
                </div>

                <a href="{{ route('handCashes.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <x-backend.form.saveButton>Save</x-backend.form.saveButton>
            </form>
        </div>
    </div>

    <script>
        function addInput() {
            var dynamicInputs = document.getElementById("dynamic-inputs");
            var newRow = document.createElement("div");
            newRow.classList.add("row", "form-group", "align-items-end");

            newRow.innerHTML = `
                <div class="col-md-2">
                    <input type="date" name="date[]" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="col-md-2">
                    <input type="text" list="dl_App_Models_HandCash_name" name="name[]" class="form-control" autocomplete="off" placeholder="HandCash Name">
                </div>
                <div class="col-md-2">
                    <input type="number" step="0.01" name="amount[]" class="form-control" placeholder="Amount">
                </div>
                <div class="col-md-2">
                    <select class="form-select select2" name="types[]">
                        @foreach (config('finance.handcash_types') as $key => $value)
                            <option value="{{ $key }}">{{ $value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select select2" name="rules[]">
                        @foreach (config('finance.handcash_rules') as $key => $value)
                            <option value="{{ $key }}">{{ $value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addInput()"><i class="bi bi-plus-lg"></i> Add</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeInput(this)"><i class="bi bi-dash-lg"></i> Remove</button>
                </div>
            `;

            // Global Select2 init already ran on page load, before this row existed —
            // initialize it on just the new row's selects.
            if (window.jQuery && jQuery.fn.select2) {
                jQuery(newRow).find('.select2').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }

            dynamicInputs.appendChild(newRow);
        }

        function removeInput(button) {
            var dynamicInputs = document.getElementById("dynamic-inputs");
            var rowToRemove = button.closest('.row');

            if (dynamicInputs.childElementCount > 1) {
                dynamicInputs.removeChild(rowToRemove);
            }
        }
    </script>

</x-backend.layouts.master>
