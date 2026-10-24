<x-backend.layouts.master>
    <x-slot name="pageTitle">Edit Recurring Transaction</x-slot>

    <x-backend.layouts.elements.errors />

    <div class="mb-3">
        <a href="{{ route('recurring.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Recurring transactions</a>
        <h2 class="h4 mb-0 mt-1">Edit {{ $recurring->name }}</h2>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('recurring.update', $recurring) }}">
                @csrf
                @method('put')
                @include('backend.library.recurring._form')

                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="rt_active" name="is_active" value="1"
                        {{ old('is_active', $recurring->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="rt_active">Active</label>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route('recurring.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save changes</button>
                </div>
            </form>
        </div>
    </div>
</x-backend.layouts.master>
