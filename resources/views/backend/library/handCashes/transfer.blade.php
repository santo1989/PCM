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

    <div class="card">
        <div class="card-body">
            <form action="{{ route('handCashes_transfer') }}" method="post">
                @csrf

                <div class="row">
                    <div class="col-md-2">
                        <x-backend.form.input name="date" type="date" label="Date" value="{{ date('Y-m-d') }}" />
                    </div>
                    <div class="col-md-3">
                        <x-backend.form.autocomplete-input name="name" label="Transfer Description"
                            model="App\Models\HandCash" column="name" />
                    </div>
                    <div class="col-md-2">
                        <x-backend.form.input name="amount" type="number" step="0.01" label="Amount" />
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-2">
                        <x-backend.form.select name="types1" label="From: Type"
                            :options="config('finance.handcash_types')" selected="WIDROWS" />
                    </div>
                    <div class="col-md-3">
                        <x-backend.form.select name="rules1" label="From: Account"
                            :options="config('finance.handcash_rules')" />
                    </div>
                    <div class="col-md-2">
                        <x-backend.form.select name="types2" label="To: Type"
                            :options="config('finance.handcash_types')" selected="SAVE" />
                    </div>
                    <div class="col-md-3">
                        <x-backend.form.select name="rules2" label="To: Account"
                            :options="config('finance.handcash_rules')" />
                    </div>
                </div>

                <a href="{{ route('handCashes.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <x-backend.form.saveButton>Save</x-backend.form.saveButton>
            </form>
        </div>
    </div>

    <script>
        // Keep the "From"/"To" type selects as opposites of each other: money leaving one
        // account (Withdraws) always means it's landing in the other (Savings), and vice versa.
        document.addEventListener('DOMContentLoaded', function () {
            var types1 = document.getElementById('types1');
            var types2 = document.getElementById('types2');
            if (!types1 || !types2) return;

            function sync(source, target) {
                target.value = source.value === 'SAVE' ? 'WIDROWS' : 'SAVE';
            }

            types1.addEventListener('change', function () { sync(types1, types2); });
            types2.addEventListener('change', function () { sync(types2, types1); });
        });
    </script>

</x-backend.layouts.master>
