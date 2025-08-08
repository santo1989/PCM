<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Edit HandCash Information
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> HandCash </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('handCashes.index') }}">HandCash</a></li>
            <li class="breadcrumb-item active">Edit HandCash Information</li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>


    <x-backend.layouts.elements.errors />
    <form action="{{ route('handCashes.update', ['handCash' => $handCashes->id]) }}" method="post"
        enctype="multipart/form-data">
        <div class="pb-3">
            @csrf
            @method('put')

            <x-backend.form.input name="date" type="date" label="Date" :value="$handCashes->date" />
            <br>
            <x-backend.form.input name="name" type="text" label="Name" :value="$handCashes->name" />
            <br>
            <x-backend.form.input name="amount" type="number" label="Amount" :value="$handCashes->amount" />
            <br>
            <div class="form-group">
                <label for="types">HandCash Types</label>
                <select class="form-control" name="types" id="types">
                    <option value="Save" {{ $handCashes->types == 'Save' ? 'selected' : '' }}>Savings</option>
                    <option value="Widrows" {{ $handCashes->types == 'Widrows' ? 'selected' : '' }}>Widrows</option>
                </select>
            </div>
            <br>

            <div class="form-group">
                <label for="rules">Cash Rules</label>
                <select class="form-control" name="rules" id="rules">
                    {{-- <option value="Mobile" {{ $handCashes->rules == 'Mobile' ? 'selected' : '' }}>Mobile</option>
                    <option value="Bank" {{ $handCashes->rules == 'Bank' ? 'selected' : '' }}>Bank</option>
                    <option value="Cash" {{ $handCashes->rules == 'Cash' ? 'selected' : '' }}>Cash</option>
                    <option value="loan" {{ $handCashes->rules == 'loan' ? 'selected' : '' }}>Loan To Other</option> --}}
                    <option value="Mobile_Bkash" {{ $handCashes->rules == 'Mobile_Bkash' ? 'selected' : '' }}>Bkash
                    </option>
                    <option value="Mobile_Rocket" {{ $handCashes->rules == 'Mobile_Rocket' ? 'selected' : '' }}>Rocket
                    </option>
                    <option value="City_Bank" {{ $handCashes->rules == 'City_Bank' ? 'selected' : '' }}>City Bank
                    </option>
                    <option value="Sonali_Bank_Gulshan"
                        {{ $handCashes->rules == 'Sonali_Bank_Gulshan' ? 'selected' : '' }}>Sonali Bank Gulshan
                    </option>
                    <option value="Sonali_Bank_Tongi" {{ $handCashes->rules == 'Sonali_Bank_Tongi' ? 'selected' : '' }}>
                        Sonali Bank Tongi
                    </option>
                    <option value="DBBL" {{ $handCashes->rules == 'DBBL' ? 'selected' : '' }}>Dutch Bangla Bank
                    </option>
                    <option value="PBL" {{ $handCashes->rules == 'PBL' ? 'selected' : '' }}>Prime Bank Ltd </option>
                    <option value="Mobile_Nagad" {{ $handCashes->rules == 'Mobile_Nagad' ? 'selected' : '' }}>Nagad
                    </option>
                    <option value="Cash" {{ $handCashes->rules == 'Cash' ? 'selected' : '' }}>Cash</option>
                    <option value="Peti" {{ $handCashes->rules == 'Peti' ? 'selected' : '' }}>Peti Cash</option>
                    <option value="loan" {{ $handCashes->rules == 'loan' ? 'selected' : '' }}>Loan To Other</option>
                    <option value="CreditCard" {{ $handCashes->rules == 'CreditCard' ? 'selected' : '' }}>Credit Card </option>
                    <option value="FD" {{ $handCashes->rules == 'FD' ? 'selected' : '' }}>FD</option>
                    <option value="DPS" {{ $handCashes->rules == 'DPS' ? 'selected' : '' }}>DPS</option>
                    <option value="MyLoan" {{ $handCashes->rules == 'MyLoan' ? 'selected' : '' }}>MyLoan</option>


                </select>
            </div>
            <br>

            <x-backend.form.saveButton>Save</x-backend.form.saveButton>
        </div>
    </form>


</x-backend.layouts.master>
