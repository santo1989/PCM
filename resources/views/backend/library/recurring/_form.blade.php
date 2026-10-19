{{-- Shared create/edit fields. $recurring is null on create. --}}
@php
    $r = $recurring ?? null;
    $kind = old('kind', $r->kind ?? 'income');
    $accounts = \App\Services\AutocompleteSource::ranked(config('finance.handcash_rules'), \App\Models\HandCash::class, 'rules');
@endphp
<div class="row g-3" data-recurring-form>
    <div class="col-12">
        <div class="form-label mb-1">Type</div>
        <div class="btn-group w-100" role="group" aria-label="Recurring type">
            <input type="radio" class="btn-check" name="kind" id="rt_kind_income" value="income" autocomplete="off"
                {{ $kind === 'income' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="rt_kind_income"><i class="bi bi-cash-coin"></i> Income</label>
            <input type="radio" class="btn-check" name="kind" id="rt_kind_transfer" value="transfer" autocomplete="off"
                {{ $kind === 'transfer' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="rt_kind_transfer"><i class="bi bi-arrow-left-right"></i> Transfer</label>
        </div>
        <div class="form-text" data-kind-help="income">Adds an income entry to Income &amp; Expenses.</div>
        <div class="form-text" data-kind-help="transfer">Moves money between accounts, like the Transfer page: a Withdraws
            entry on the "from" account and a Savings entry on the "to" account.</div>
    </div>
    <div class="col-12 col-md-6">
        <label for="rt_name" class="form-label">Name</label>
        <input type="text" id="rt_name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $r->name ?? '') }}" placeholder="e.g. Monthly salary, DPS deposit" required maxlength="191">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6" data-kind-field="income">
        <label for="rt_category" class="form-label">Income category</label>
        <select id="rt_category" name="category_id" class="form-select select2 @error('category_id') is-invalid @enderror">
            <option value="">Choose…</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" {{ old('category_id', $r->category_id ?? null) == $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-3" data-kind-field="transfer">
        <label for="rt_from" class="form-label">From account</label>
        <select id="rt_from" name="from_account" class="form-select select2 @error('from_account') is-invalid @enderror">
            <option value="">Choose…</option>
            @foreach ($accounts as $key => $label)
                <option value="{{ $key }}" {{ old('from_account', $r->from_account ?? null) === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('from_account')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-3" data-kind-field="transfer">
        <label for="rt_to" class="form-label">To account</label>
        <select id="rt_to" name="to_account" class="form-select select2 @error('to_account') is-invalid @enderror">
            <option value="">Choose…</option>
            @foreach ($accounts as $key => $label)
                <option value="{{ $key }}" {{ old('to_account', $r->to_account ?? null) === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('to_account')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-3">
        <label for="rt_amount" class="form-label">Amount</label>
        <input type="number" id="rt_amount" name="amount" step="0.01" min="0.01" inputmode="decimal"
            class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $r->amount ?? '') }}" required>
        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-3">
        <label for="rt_day" class="form-label">Day of month</label>
        <input type="number" id="rt_day" name="day_of_month" min="1" max="31" inputmode="numeric"
            class="form-control @error('day_of_month') is-invalid @enderror"
            value="{{ old('day_of_month', $r->day_of_month ?? now()->day) }}" required>
        @error('day_of_month')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-3">
        <label for="rt_start" class="form-label">Starts</label>
        <input type="date" id="rt_start" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
            value="{{ old('start_date', optional($r->start_date ?? null)->toDateString() ?? now()->toDateString()) }}" required>
        @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-3">
        <label for="rt_end" class="form-label">Ends <span class="text-muted small">(optional)</span></label>
        <input type="date" id="rt_end" name="end_date" class="form-control @error('end_date') is-invalid @enderror"
            value="{{ old('end_date', optional($r->end_date ?? null)->toDateString()) }}">
        @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <div class="form-text">
            Runs on this day every month. Days 29–31 fall on the last day of shorter months. A start date in the
            past creates the missed months straight away.
        </div>
    </div>
</div>

<script>
    // Show only the fields for the chosen type, and require only those.
    (function () {
        var form = document.querySelector('[data-recurring-form]');
        if (!form) return;
        function apply() {
            var kind = form.querySelector('input[name="kind"]:checked').value;
            form.querySelectorAll('[data-kind-field], [data-kind-help]').forEach(function (el) {
                var on = (el.dataset.kindField || el.dataset.kindHelp) === kind;
                el.classList.toggle('d-none', !on);
                el.querySelectorAll('select').forEach(function (s) { s.required = on; });
            });
        }
        form.querySelectorAll('input[name="kind"]').forEach(function (r) { r.addEventListener('change', apply); });
        apply();
    })();
</script>
