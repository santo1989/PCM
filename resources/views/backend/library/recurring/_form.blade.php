{{-- Shared create/edit fields. $recurring is null on create. --}}
@php($r = $recurring ?? null)
<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="rt_name" class="form-label">Name</label>
        <input type="text" id="rt_name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $r->name ?? '') }}" placeholder="e.g. Monthly salary, House rent" required maxlength="191">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="rt_category" class="form-label">Category</label>
        <select id="rt_category" name="category_id" class="form-select select2 @error('category_id') is-invalid @enderror" required>
            <option value="">Choose…</option>
            @foreach ($categories->groupBy('types') as $type => $group)
                <optgroup label="{{ ucfirst(strtolower($type)) }}">
                    @foreach ($group as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $r->category_id ?? null) == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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
            An entry is added to Income &amp; Expenses on this day every month. Days 29–31 fall on the last day of
            shorter months. A start date in the past creates the missed months straight away.
        </div>
    </div>
</div>
