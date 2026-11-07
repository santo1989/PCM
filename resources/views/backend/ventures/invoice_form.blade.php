<x-backend.layouts.master>
    <x-slot name="pageTitle">{{ $invoice->exists ? 'Edit ' . $invoice->number : 'New invoice' }}</x-slot>

    <x-backend.layouts.elements.errors />

    @php
        $items = old('items', $invoice->items ?: [['description' => '', 'period' => '', 'qty' => 1, 'rate' => '']]);
        $val = fn($field, $default = null) => old($field, $default ?? $invoice->$field);
        $date = fn($v) => $v instanceof \Carbon\Carbon ? $v->toDateString() : $v;
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0">{{ $invoice->exists ? "Edit {$invoice->type} {$invoice->number}" : 'New invoice / bill / cash memo' }}</h2>
            @if ($schedule)<div class="small text-muted">From the bill reminder “{{ $schedule->title }}”: saving moves the reminder to its next date.</div>@endif
        </div>
        <a href="{{ route('ventures.index', ['venture' => $invoice->venture]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <form method="POST" action="{{ $invoice->exists ? route('ventures.invoices.update', $invoice) : route('ventures.invoices.store') }}" id="invoiceForm">
        @csrf
        @if ($invoice->exists) @method('PUT') @endif
        @if ($schedule)<input type="hidden" name="schedule_id" value="{{ $schedule->id }}">@endif

        <div class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-6 col-md-3"><label class="form-label" for="iv_venture">From</label>
                    <select id="iv_venture" name="venture" class="form-select">@foreach (config('finance.ventures') as $k => $v)<option value="{{ $k }}" {{ $val('venture') === $k ? 'selected' : '' }}>{{ $v['label'] }}</option>@endforeach</select></div>
                <div class="col-6 col-md-3"><label class="form-label" for="iv_type">Document</label>
                    <select id="iv_type" name="type" class="form-select">@foreach (\App\Models\VentureInvoice::TYPES as $t)<option {{ $val('type') === $t ? 'selected' : '' }}>{{ $t }}</option>@endforeach</select></div>
                <div class="col-6 col-md-3"><label class="form-label" for="iv_number">Number</label><input id="iv_number" name="number" class="form-control" value="{{ $val('number') }}" required maxlength="32"></div>
                <div class="col-6 col-md-3"><label class="form-label" for="iv_client">Client (tag)</label><input id="iv_client" name="client" list="ivClients" class="form-control text-uppercase" value="{{ $val('client') }}" required maxlength="120">
                    <datalist id="ivClients">@foreach ($clients as $c)<option value="{{ $c }}">@endforeach</datalist></div>
                <div class="col-12 col-md-6"><label class="form-label" for="iv_billto">Bill to (as printed)</label>
                    <textarea id="iv_billto" name="bill_to" rows="3" class="form-control" placeholder="Company name, email, address">{{ $val('bill_to') }}</textarea></div>
                <div class="col-6 col-md-3"><label class="form-label" for="iv_date">Date</label><input type="date" id="iv_date" name="date" class="form-control" value="{{ $date($val('date')) }}" required></div>
                <div class="col-6 col-md-3"><label class="form-label" for="iv_due">Pay by</label><input type="date" id="iv_due" name="due_date" class="form-control" value="{{ $date($val('due_date')) }}"></div>
                <div class="col-12 col-md-6 d-none" data-cash-memo>
                    <label class="form-label" for="iv_account">Received into</label>
                    <select id="iv_account" name="account" class="form-select">@foreach (config('finance.handcash_rules') as $k => $v)<option value="{{ $k }}" {{ old('account', 'CITY_BANK') === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select>
                    <div class="form-text">A cash memo is paid on the spot: saving it also adds the income to this account.</div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span>Lines <span class="small text-muted">(a negative rate is a discount)</span></span>
                <button type="button" class="btn btn-outline-primary btn-sm" id="addLine"><i class="bi bi-plus-lg"></i> Line</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" style="min-width: 720px;">
                        <thead class="table-light"><tr><th>Description</th><th style="width: 160px;">Period</th><th style="width: 80px;">Qty</th><th style="width: 130px;">Rate (Tk)</th><th class="text-end" style="width: 120px;">Amount</th><th style="width: 40px;"></th></tr></thead>
                        <tbody id="lines">
                            @foreach ($items as $n => $it)
                                <tr data-line>
                                    <td><textarea name="items[{{ $n }}][description]" rows="2" class="form-control form-control-sm" aria-label="Description">{{ $it['description'] ?? '' }}</textarea></td>
                                    <td><input name="items[{{ $n }}][period]" class="form-control form-control-sm" value="{{ $it['period'] ?? '' }}" aria-label="Period"></td>
                                    <td><input type="number" name="items[{{ $n }}][qty]" min="0" step="any" class="form-control form-control-sm text-end" value="{{ $it['qty'] ?? 1 }}" aria-label="Quantity" data-qty></td>
                                    <td><input type="number" name="items[{{ $n }}][rate]" step="any" class="form-control form-control-sm text-end" value="{{ $it['rate'] ?? '' }}" aria-label="Rate" data-rate></td>
                                    <td class="text-end" data-amount></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove aria-label="Remove line"><i class="bi bi-x-lg"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr class="fw-bold"><td colspan="4" class="text-end">Total</td><td class="text-end" id="total"></td><td></td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body"><label class="form-label" for="iv_notes">Note</label><textarea id="iv_notes" name="notes" rows="2" class="form-control">{{ $val('notes') }}</textarea></div>
        </div>

        <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save &amp; view</button></div>
    </form>

    <script>
        (function () {
            var body = document.getElementById('lines'), fmt = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            function recalc() {
                var total = 0;
                body.querySelectorAll('[data-line]').forEach(function (tr) {
                    var a = (parseFloat(tr.querySelector('[data-qty]').value) || 0) * (parseFloat(tr.querySelector('[data-rate]').value) || 0);
                    tr.querySelector('[data-amount]').textContent = fmt.format(a);
                    total += a;
                });
                document.getElementById('total').textContent = fmt.format(total) + ' Tk';
            }
            document.getElementById('addLine').addEventListener('click', function () {
                var rows = body.querySelectorAll('[data-line]'), tr = rows[rows.length - 1].cloneNode(true), n = Date.now();
                tr.querySelectorAll('input, textarea').forEach(function (el) { el.name = el.name.replace(/items\[[^\]]+\]/, 'items[' + n + ']'); el.value = el.dataset.qty !== undefined ? 1 : ''; });
                body.appendChild(tr); recalc();
            });
            body.addEventListener('click', function (e) {
                var b = e.target.closest('[data-remove]');
                if (b && body.querySelectorAll('[data-line]').length > 1) { b.closest('tr').remove(); recalc(); }
            });
            body.addEventListener('input', recalc);
            var type = document.getElementById('iv_type'), memo = document.querySelector('[data-cash-memo]');
            function toggleMemo() { memo.classList.toggle('d-none', type.value !== 'Cash memo' || {{ $invoice->exists ? 'true' : 'false' }}); }
            type.addEventListener('change', toggleMemo); toggleMemo(); recalc();
        })();
    </script>
</x-backend.layouts.master>
