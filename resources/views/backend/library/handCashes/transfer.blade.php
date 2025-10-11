<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Transfer HandCash
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> HandCash </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('handCashes.index') }}">HandCash</a></li>
            <li class="breadcrumb-item active">Transfer HandCash</li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>


    <x-backend.layouts.elements.errors />
    <form action="{{ route('handCashes_transfer') }}" method="post" enctype="multipart/form-data">
        <div class="pb-3">
            @csrf
            <br>
            <!-- Add a wrapper to contain the dynamic inputs -->
            <div id="dynamic-inputs">
                <!-- Initial input fields -->
                <div class="row form-group">
                    <div class="col-md-1">
                        <x-backend.form.input name="date" type="date" label="Date"
                            value="{{ date('Y-m-d') }}" />
                    </div>
                    <div class="col-md-2">
                        <x-backend.form.input name="name" type="text" label="HandCash Name" />
                    </div>
                    <div class="col-md-2">
                        <x-backend.form.input name="amount" type="number" label="amount" />
                    </div>
                    <div class="col-md-1">
                        <label for="types2">Types1</label>
                        <select class="form-control" name="types1">
                            <option value="Save">Savings</option>
                            <option value="Widrows">Withdraws</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="rules1">Rules</label>
                        <select class="form-control" name="rules1">
                            <option value="Mobile_Bkash">Bkash</option>
                            <option value="Mobile_Rocket">Rocket</option>
                            <option value="City_Bank">City Bank</option>
                            <option value="Sonali_Bank_Gulshan">Sonali Bank Gulshan</option>
                            <option value="Sonali_Bank_Tongi">Sonali Bank Tongi</option>
                            <option value="DBBL">Dutch Bangla Bank</option>
                            <option value="PBL">Prime Bank Ltd</option>
                            <option value="Mobile_Nagad">Nagad</option>
                            <option value="Cash">Cash</option>
                            <option value="Peti">Peti Cash</option>
                            <option value="loan">Loan To Other </option>
                            <option value="CreditCard">Credit Card</option>
                            <option value="FD">FD</option>
                            <option value="DPS">DPS</option>
                            <option value="MyLoan">MyLoan</option>
                            <option value="DPSLoan">DPS Loan</option>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <label for="types2">Types2</label>
                        <select class="form-control" name="types2">
                            <option value="Save">Savings</option>
                            <option value="Widrows">Withdraws</option>
                        </select>
                        <script>
                            var types1 = document.getElementsByName('types1');
                            var types2 = document.getElementsByName('types2');
                            if (types1 = 'Save') {
                                types2 = 'Widrows';
                            } else {
                                types2 = 'Save';

                            }
                        </script>
                    </div>
                    <div class="col-md-2">
                        <label for="rules2">Rules</label>
                        <select class="form-control" name="rules2">
                            <option value="Mobile_Bkash">Bkash</option>
                            <option value="Mobile_Rocket">Rocket</option>
                            <option value="City_Bank">City Bank</option>
                            <option value="Sonali_Bank_Gulshan">Sonali Bank Gulshan</option>
                            <option value="Sonali_Bank_Tongi">Sonali Bank Tongi</option>
                            <option value="DBBL">Dutch Bangla Bank</option>
                            <option value="PBL">Prime Bank Ltd</option>
                            <option value="Mobile_Nagad">Nagad</option>
                            <option value="Cash">Cash</option>
                            <option value="Peti">Peti Cash</option>
                            <option value="loan">Loan To Other </option>
                            <option value="CreditCard">Credit Card</option>
                            <option value="FD">FD</option>
                            <option value="DPS">DPS</option>
                            <option value="MyLoan">MyLoan</option>
                            <option value="DPSLoan">DPS Loan</option>
                        </select>
                    </div>



                </div>
            </div>
            <a href="{{ route('handCashes.index') }}" class="btn btn-danger">Cancel</a>
            <x-backend.form.saveButton>Save</x-backend.form.saveButton>
        </div>
    </form>


</x-backend.layouts.master>
