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
    <form action="{{ route('handCashes.store') }}" method="post" enctype="multipart/form-data">
        <div class="pb-3">
            @csrf
            <br>
            <!-- Add a wrapper to contain the dynamic inputs -->
            <div id="dynamic-inputs">
                <!-- Initial input fields -->
                <div class="row form-group">
                    <div class="col-md-2">
                        <x-backend.form.input name="date[]" type="date" label="Date"
                            value="{{ date('Y-m-d') }}" />
                    </div>
                    <div class="col-md-2">
                        <x-backend.form.input name="name[]" type="text" label="HandCash Name" />
                    </div>
                    <div class="col-md-2">
                        <x-backend.form.input name="amount[]" type="number" label="amount" />
                    </div>
                    <div class="col-md-2">
                        <label for="types[]">HandCash Types</label>
                        <select class="form-control" name="types[]">
                            <option value="Save">Savings</option>
                            <option value="Widrows">Withdraws</option>

                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="rules[]">Cash Rules</label>
                        <select class="form-control" name="rules[]">
                            <option value="Peti">Peti Cash</option>
                            <option value="Cash">Cash</option>
                            <option value="City_Bank">City Bank</option>
                            <option value="City_Bank_Islamic">City Bank Islamic</option>
                            <option value="FD">FD</option>
                            <option value="DPS">DPS</option>
                            <option value="MyLoan">MyLoan</option>
                            <option value="DPSLoan">DPS Loan</option>
                            <option value="loan">Loan To Other </option>
                            <option value="CreditCard">Credit Card</option>
                            <option value="Sonali_Bank_Gulshan">Sonali Bank Gulshan</option>
                            <option value="Sonali_Bank_Tongi">Sonali Bank Tongi</option>
                            <option value="DBBL">Dutch Bangla Bank</option>
                            <option value="PBL">Prime Bank Ltd</option>
                            <option value="Mobile_Nagad">Nagad</option>
                            <option value="Mobile_Bkash">Bkash</option>
                            <option value="Mobile_Rocket">Rocket</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <!-- Add and Remove buttons -->
                        <button type="button" onclick="addInput()">Add</button>
                        <button type="button" onclick="removeInput(this)">Remove</button>
                    </div>
                </div>
            </div>
            <a href="{{ route('handCashes.index') }}" class="btn btn-danger">Cancel</a>
            <x-backend.form.saveButton>Save</x-backend.form.saveButton>
        </div>
    </form>

    <!-- JavaScript/jQuery code -->
    <script>
        function addInput() {
            var dynamicInputs = document.getElementById("dynamic-inputs");
            var newInput = document.createElement("div");
            newInput.classList.add("row", "form-group");

            newInput.innerHTML = `
            <div class="col-md-2">
                <x-backend.form.input name="date[]" type="date" label="Date" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-2">
                    <x-backend.form.input name="name[]" type="text" label="HandCash Name" />
                </div>
                  <div class="col-md-2">
                    <x-backend.form.input name="amount[]" type="number" label="amount" />
                </div>
            <div class="col-md-2">
                <label for="types[]">HandCash Types</label>
                <select class="form-control" name="types[]">
                    <option value="Save">Savings</option>
                    <option value="Widrows">Withdraws</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="rules[]">Cash Rules</label>
                <select class="form-control" name="rules[]">
                            <option value="Peti">Peti Cash</option>
                            <option value="Cash">Cash</option>
                            <option value="City_Bank">City Bank</option>
                            <option value="City_Bank_Islamic">City Bank Islamic</option>
                            <option value="FD">FD</option>
                            <option value="DPS">DPS</option>
                            <option value="MyLoan">MyLoan</option>
                            <option value="DPSLoan">DPS Loan</option>
                            <option value="loan">Loan To Other </option>
                            <option value="CreditCard">Credit Card</option>
                            <option value="Sonali_Bank_Gulshan">Sonali Bank Gulshan</option>
                            <option value="Sonali_Bank_Tongi">Sonali Bank Tongi</option>
                            <option value="DBBL">Dutch Bangla Bank</option>
                            <option value="PBL">Prime Bank Ltd</option>
                            <option value="Mobile_Nagad">Nagad</option>
                            <option value="Mobile_Bkash">Bkash</option>
                            <option value="Mobile_Rocket">Rocket</option>
                </select>
            </div>
            <div class="col-md-2">
                <!-- Add and Remove buttons -->
                <button type="button" onclick="addInput()">Add</button>
                <button type="button" onclick="removeInput(this)">Remove</button>
            </div>
        `;

            dynamicInputs.appendChild(newInput);
        }

        function removeInput(button) {
            var dynamicInputs = document.getElementById("dynamic-inputs");
            var rowToRemove = button.parentNode.parentNode; // Get the parent row of the button

            // Make sure there's at least one input before removing
            if (dynamicInputs.childElementCount > 1) {
                dynamicInputs.removeChild(rowToRemove);
            }
        }
    </script>

</x-backend.layouts.master>
