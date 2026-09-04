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

    <div class="card">
        <div class="card-body">
            <form action="{{ route('handCashes.update', ['handCash' => $handCashes->id]) }}" method="post">
                @csrf
                @method('put')

                <x-backend.form.input name="date" type="date" label="Date" :value="$handCashes->date" />

                <x-backend.form.autocomplete-input name="name" label="Name" model="App\Models\HandCash" column="name"
                    :value="$handCashes->name" />

                <x-backend.form.input name="amount" type="number" step="0.01" label="Amount" :value="$handCashes->amount" />

                <x-backend.form.select name="types" label="HandCash Types" class="select2"
                    :options="config('finance.handcash_types')" :selected="$handCashes->types" />

                <x-backend.form.select name="rules" label="Cash Rules" class="select2"
                    :options="config('finance.handcash_rules')" :selected="$handCashes->rules" />

                <x-backend.form.saveButton>Save</x-backend.form.saveButton>
            </form>
        </div>
    </div>

</x-backend.layouts.master>
