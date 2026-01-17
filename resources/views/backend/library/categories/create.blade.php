<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Create Category
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> Category </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Category</a></li>
            <li class="breadcrumb-item active">Create Category</li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>


    <x-backend.layouts.elements.errors />
    <form action="{{ route('categories.store') }}" method="post" enctype="multipart/form-data">
        <div class="pb-3">
            @csrf
            <br>
            <x-backend.form.input name="name" type="text" label="Category Name" />
            <br>
            {{-- <x-backend.form.input name="types" type="text" label="Category Types" /> --}}
            <div class="form-group">
                <label for="types">Category Types</label>
                <select class="form-control" name="types" id="types">
                    <option value="INCOME">Income</option>
                    <option value="EXPENSE">Expense</option>
                    <option value="LOAN">Loan to Other</option>
                    <option value="RETURN">Loan Return</option>

                </select>
            </div>
            <br>
            <div class="form-group">
                <label for="rules">50/30/20 rules</label>
                <select class="form-control" name="rules" id="rules">
                    <option value="NEEDS">50% of income: needs</option>
                    <option value="WANTS">30% of income: wants</option>
                    <option value="SAVINGS">20% of income: savings</option>
                </select>
            </div>
            <br>
            <br>
            <x-backend.form.saveButton>Save</x-backend.form.saveButton>



        </div>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Find the categories form on the page
                var form = document.querySelector('form[action="{{ route('categories.store') }}"]');
                if (!form) {
                    // fallback: pick the first form on the page
                    form = document.querySelector('form');
                }

                if (!form) return;

                // Uppercase text inputs and textareas as user types
                Array.from(form.querySelectorAll('input[type="text"], textarea')).forEach(function(el) {
                    el.addEventListener('input', function() {
                        this.value = this.value ? this.value.toUpperCase() : this.value;
                    });
                });

                // Ensure selects send uppercase values (option values are already uppercased above)
                Array.from(form.querySelectorAll('select')).forEach(function(el) {
                    el.addEventListener('change', function() {
                        if (this.value) {
                            // Coerce value to uppercase to be defensive
                            this.value = this.value.toUpperCase();
                        }
                    });
                });

                // On submit, uppercase all relevant inputs as a final guard
                form.addEventListener('submit', function() {
                    Array.from(form.querySelectorAll('input, textarea, select')).forEach(function(el) {
                        var tag = el.tagName.toLowerCase();
                        if (tag === 'select') {
                            if (el.value) el.value = el.value.toUpperCase();
                        } else if (el.type === 'text' || tag === 'textarea') {
                            if (el.value) el.value = el.value.toUpperCase();
                        }
                    });
                });
            });
        </script>
    @endpush

</x-backend.layouts.master>
