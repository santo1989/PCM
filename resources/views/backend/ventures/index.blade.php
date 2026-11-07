<x-backend.layouts.master>
    <x-slot name="pageTitle">Freelance & Business</x-slot>

    <x-backend.layouts.elements.message :message="session('message')" />
    <x-backend.layouts.elements.errors />

    @php
        $tk = fn($v) => number_format($v, 2);
        $pct = fn($v) => $v === null ? '–' : number_format($v * 100, 1) . '%';
        $tone = fn($v) => abs($v) < 0.005 ? '' : ($v < 0 ? 'text-danger' : 'text-success');
        $t = $summary['total'];
        $label = $ventures[$key]['label'];
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0">Freelance & Business</h2>
            <div class="text-muted small">Income, costs and profit per client. Entries are normal Income &amp; Expenses rows, so every report includes them.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('ventures.invoices.create', ['venture' => $key]) }}" class="btn btn-primary btn-sm"><i class="bi bi-receipt"></i> New invoice / bill / cash memo</a>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ventureEntry"><i class="bi bi-plus-lg"></i> Income / cost</button>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleNew"><i class="bi bi-bell"></i> New reminder</button>
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#letterhead"><i class="bi bi-person-vcard"></i> Letterhead</button>
        </div>
    </div>

    @php($soon = $schedules->whereIn('id', $sendSoon))
    @if ($soon->isNotEmpty())
        <div class="alert alert-warning d-flex flex-column gap-2" role="status">
            <div class="fw-semibold"><i class="bi bi-bell"></i> Bills to send</div>
            @foreach ($soon as $s)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><span class="fw-semibold">{{ $s->client }}</span> · {{ $s->title }} · Tk {{ number_format(\App\Services\VentureBilling::itemsTotal($s->items), 2) }}
                        · {{ $s->next_due->isPast() ? 'was due' : 'due' }} {{ $s->next_due->format('d M Y') }} ({{ $s->next_due->diffForHumans() }})</span>
                    <a href="{{ route('ventures.invoices.create', ['schedule' => $s->id]) }}" class="btn btn-sm btn-warning">Create bill</a>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ($ventures as $k => $v)
            @php($o = $overview[$k])
            <div class="col-12 col-md-6">
                <a href="{{ route('ventures.index', ['venture' => $k]) }}" class="card h-100 text-decoration-none {{ $k === $key ? 'border-primary' : '' }}"
                    @if ($k === $key) aria-current="page" @endif>
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between"><span class="fw-semibold">{{ $v['label'] }}</span>
                            <span class="small text-muted">margin {{ $pct($o['margin']) }}</span></div>
                        <div class="d-flex flex-wrap gap-3 mt-1 small">
                            <span>Income <span class="fw-bold text-success">{{ $tk($o['income']) }}</span></span>
                            <span>Cost <span class="fw-bold text-danger">{{ $tk($o['cost']) }}</span></span>
                            <span>Profit <span class="fw-bold {{ $tone($o['profit']) }}">{{ $tk($o['profit']) }}</span></span>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header">{{ $label }} · by client / project</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle text-nowrap mb-0">
                            <thead class="table-light"><tr><th>Client / project</th><th class="text-end">Income</th><th class="text-end">Cost</th><th class="text-end">Profit</th><th class="text-end">Margin</th><th>Period</th></tr></thead>
                            <tbody>
                                @forelse ($summary['clients'] as $name => $c)
                                    <tr>
                                        <td class="fw-semibold {{ $name === 'Not tagged' ? 'text-muted fst-italic' : '' }}">{{ $name }} <span class="small text-muted fw-normal">({{ $c['entries'] }})</span></td>
                                        <td class="text-end">{{ $tk($c['income']) }}</td><td class="text-end">{{ $tk($c['cost']) }}</td>
                                        <td class="text-end fw-semibold {{ $tone($c['profit']) }}">{{ $tk($c['profit']) }}</td><td class="text-end">{{ $pct($c['margin']) }}</td>
                                        <td class="small text-muted">{{ \Carbon\Carbon::parse($c['first'])->format('M Y') }}{{ substr($c['first'], 0, 7) !== substr($c['last'], 0, 7) ? ' – ' . \Carbon\Carbon::parse($c['last'])->format('M Y') : '' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">No {{ $label }} entries yet.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($summary['clients'])
                                <tfoot class="fw-bold"><tr><td>Total</td><td class="text-end">{{ $tk($t['income']) }}</td><td class="text-end">{{ $tk($t['cost']) }}</td>
                                    <td class="text-end {{ $tone($t['profit']) }}">{{ $tk($t['profit']) }}</td><td class="text-end">{{ $pct($t['margin']) }}</td><td></td></tr></tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header">{{ $label }} · by year</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle text-nowrap mb-0">
                            <thead class="table-light"><tr><th>Year</th><th class="text-end">Income</th><th class="text-end">Cost</th><th class="text-end">Profit</th></tr></thead>
                            <tbody>
                                @foreach ($summary['years'] as $year => $y)
                                    <tr><td>{{ $year }}</td><td class="text-end">{{ $tk($y['income']) }}</td><td class="text-end">{{ $tk($y['cost']) }}</td>
                                        <td class="text-end fw-semibold {{ $tone($y['profit']) }}">{{ $tk($y['profit']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header">{{ $label }} · due from clients</div>
                <div class="card-body">
                    @forelse ($dueByClient as $client => $d)
                        <div class="d-flex justify-content-between gap-2 py-1 border-bottom">
                            <span class="fw-semibold">{{ $client }} <span class="small text-muted fw-normal">({{ $d['invoices'] }} unpaid)</span></span>
                            <span class="text-nowrap {{ $d['overdue'] > 0 ? 'text-danger' : 'text-warning' }}">Tk {{ $tk($d['due']) }}{{ $d['overdue'] > 0 ? ' · ' . $tk($d['overdue']) . ' overdue' : '' }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Nothing due: every invoice is paid.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header">{{ $label }} · bill reminders</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle text-nowrap mb-0">
                            <thead class="table-light"><tr><th>Client</th><th>For</th><th class="text-end">Amount</th><th>Every</th><th>Next bill</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($schedules as $s)
                                    <tr class="{{ $s->is_active ? '' : 'opacity-50' }}">
                                        <td class="fw-semibold">{{ $s->client }}</td><td class="small text-wrap">{{ $s->title }}</td>
                                        <td class="text-end">{{ $tk(\App\Services\VentureBilling::itemsTotal($s->items)) }}</td>
                                        <td>{{ $s->every_months % 12 === 0 ? ($s->every_months === 12 ? 'year' : ($s->every_months / 12) . ' years') : ($s->every_months == 1 ? 'month' : $s->every_months . ' months') }}</td>
                                        <td class="{{ in_array($s->id, $sendSoon) ? 'text-warning fw-semibold' : '' }}">{{ $s->next_due->format('d M Y') }}<div class="small text-muted">remind {{ $s->remind_days }} days before</div></td>
                                        <td class="text-end">
                                            <a href="{{ route('ventures.invoices.create', ['schedule' => $s->id]) }}" class="btn btn-sm btn-outline-primary">Bill now</a>
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#schedule{{ $s->id }}" aria-label="Edit reminder for {{ $s->client }}"><i class="bi bi-pencil"></i></button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">No reminders yet. Add one for each yearly or monthly bill.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">{{ $label }} · invoices, bills &amp; cash memos</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle text-nowrap mb-0">
                    <thead class="table-light"><tr><th>Number</th><th>Type</th><th>Date</th><th>Client</th><th class="text-end">Total</th><th class="text-end">Received</th><th class="text-end">Due</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $i)
                            @php($m = $i['model'])
                            <tr>
                                <td><a href="{{ route('ventures.invoices.show', $m) }}" class="fw-semibold">{{ $m->number }}</a></td>
                                <td>{{ $m->type }}</td><td>{{ $m->date->format('d M Y') }}</td><td>{{ $m->client }}</td>
                                <td class="text-end">{{ $tk($m->total) }}</td><td class="text-end">{{ $i['paid'] ? $tk($i['paid']) : '' }}</td>
                                <td class="text-end {{ $i['due'] > 0 ? 'fw-semibold' : '' }}">{{ $i['due'] > 0 ? $tk($i['due']) : '' }}</td>
                                <td><span class="badge {{ ['Paid' => 'text-bg-success', 'Overdue' => 'text-bg-danger', 'Partly paid' => 'text-bg-warning', 'Due' => 'text-bg-warning'][$i['status']] }}">{{ $i['status'] }}</span>
                                    @if ($m->due_date && $i['due'] > 0)<span class="small text-muted">by {{ $m->due_date->format('d M') }}</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-3">No {{ $label }} invoices yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between"><span>{{ $label }} · entries</span><span class="small text-muted">Type a client and press Enter to tag a row</span></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Description</th><th style="min-width: 190px;">Client / project</th><th>Account</th><th class="text-end">Income</th><th class="text-end">Cost</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td class="text-nowrap">{{ \Carbon\Carbon::parse($r['date'])->format('d M Y') }}</td>
                                <td class="small">{{ $r['name'] }}</td>
                                <td>
                                    <form method="POST" action="{{ route('ventures.client', $r['id']) }}" class="m-0">
                                        @csrf
                                        <input type="hidden" name="venture" value="{{ $key }}">
                                        <input name="client" list="ventureClients" value="{{ $r['client'] }}" maxlength="120" placeholder="Not tagged"
                                            class="form-control form-control-sm text-uppercase" aria-label="Client for {{ $r['name'] }}" onchange="this.form.requestSubmit()">
                                    </form>
                                </td>
                                <td class="small text-nowrap">{{ $accounts[$r['account']] ?? $r['account'] ?? '–' }}</td>
                                <td class="text-end text-success text-nowrap">{{ $r['kind'] === 'income' ? $tk($r['amount']) : '' }}</td>
                                <td class="text-end text-danger text-nowrap">{{ $r['kind'] === 'cost' ? $tk($r['amount']) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <datalist id="ventureClients">@foreach ($clients as $c)<option value="{{ $c }}">@endforeach</datalist>

    <div class="modal fade" id="ventureEntry" tabindex="-1" aria-labelledby="ventureEntryLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down">
            <form class="modal-content" method="POST" action="{{ route('ventures.store') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="ventureEntryLabel">Add income / cost</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body row g-3">
                    <div class="col-6"><label class="form-label" for="ve_venture">Work</label>
                        <select id="ve_venture" name="venture" class="form-select">@foreach ($ventures as $k => $v)<option value="{{ $k }}" {{ old('venture', $key) === $k ? 'selected' : '' }}>{{ $v['label'] }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label" for="ve_kind">Type</label>
                        <select id="ve_kind" name="kind" class="form-select">@foreach (['income' => 'Income', 'cost' => 'Cost'] as $k => $v)<option value="{{ $k }}" {{ old('kind') === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label" for="ve_date">Date</label><input type="date" id="ve_date" name="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required></div>
                    <div class="col-6"><label class="form-label" for="ve_amount">Amount (Tk)</label><input type="number" id="ve_amount" name="amount" min="0.01" step="any" class="form-control" value="{{ old('amount') }}" required></div>
                    <div class="col-12"><label class="form-label" for="ve_name">Description</label><input id="ve_name" name="name" class="form-control text-uppercase" value="{{ old('name') }}" maxlength="255" required placeholder="e.g. WEBSITE HOSTING BILL"></div>
                    <div class="col-6"><label class="form-label" for="ve_client">Client / project</label><input id="ve_client" name="client" list="ventureClients" class="form-control text-uppercase" value="{{ old('client') }}" maxlength="120"></div>
                    <div class="col-6"><label class="form-label" for="ve_account">Paid into / from</label>
                        <select id="ve_account" name="account" class="form-select">@foreach ($accounts as $k => $v)<option value="{{ $k }}" {{ old('account', 'CITY_BANK') === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button></div>
            </form>
        </div>
    </div>

    {{-- Bill reminder: new + one edit form per reminder --}}
    @foreach (array_merge([null], $schedules->all()) as $s)
        <div class="modal fade" id="{{ $s ? 'schedule' . $s->id : 'scheduleNew' }}" tabindex="-1" aria-labelledby="{{ $s ? 'schedule' . $s->id : 'scheduleNew' }}Label" aria-hidden="true">
            <div class="modal-dialog modal-fullscreen-sm-down">
                <form class="modal-content" method="POST" action="{{ $s ? route('ventures.schedules.update', $s) : route('ventures.schedules.store') }}">
                    @csrf
                    @if ($s) @method('PUT') @endif
                    <input type="hidden" name="venture" value="{{ $s->venture ?? $key }}">
                    <div class="modal-header"><h5 class="modal-title" id="{{ $s ? 'schedule' . $s->id : 'scheduleNew' }}Label">{{ $s ? 'Edit reminder · ' . $s->client : 'New bill reminder · ' . $label }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body row g-3">
                        <div class="col-6"><label class="form-label">Client<input name="client" list="ventureClients" class="form-control text-uppercase" value="{{ $s->client ?? '' }}" required maxlength="120"></label></div>
                        <div class="col-6"><label class="form-label">Amount (Tk)<input type="number" name="amount" min="0.01" step="any" class="form-control" value="{{ $s ? \App\Services\VentureBilling::itemsTotal($s->items) : '' }}" required></label></div>
                        <div class="col-12"><label class="form-label w-100">What the bill is for<input name="title" class="form-control" value="{{ $s->title ?? '' }}" required maxlength="191" placeholder="Domain, hosting & yearly maintenance"></label></div>
                        <div class="col-12"><label class="form-label w-100">Bill to (as printed)<textarea name="bill_to" rows="2" class="form-control">{{ $s->bill_to ?? '' }}</textarea></label></div>
                        <div class="col-4"><label class="form-label">Every<select name="every_months" class="form-select">@foreach ([1 => 'month', 3 => '3 months', 6 => '6 months', 12 => 'year', 24 => '2 years', 36 => '3 years'] as $m => $t)<option value="{{ $m }}" {{ ($s->every_months ?? 12) == $m ? 'selected' : '' }}>{{ $t }}</option>@endforeach</select></label></div>
                        <div class="col-4"><label class="form-label">Next bill on<input type="date" name="next_due" class="form-control" value="{{ $s ? $s->next_due->toDateString() : '' }}" required></label></div>
                        <div class="col-4"><label class="form-label">Remind days before<input type="number" name="remind_days" min="0" max="120" class="form-control" value="{{ $s->remind_days ?? 15 }}" required></label></div>
                        @if ($s)<div class="col-12 form-check ms-2"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="sa{{ $s->id }}" {{ $s->is_active ? 'checked' : '' }}><label class="form-check-label" for="sa{{ $s->id }}">Active</label></div>@endif
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button></div>
                </form>
            </div>
        </div>
    @endforeach

    {{-- Letterhead printed on this venture's invoices (kept in storage/, not in the code) --}}
    @php($p = \App\Services\VentureBilling::profile($key))
    <div class="modal fade" id="letterhead" tabindex="-1" aria-labelledby="letterheadLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down">
            <form class="modal-content" method="POST" action="{{ route('ventures.letterhead') }}">
                @csrf
                <input type="hidden" name="venture" value="{{ $key }}">
                <div class="modal-header"><h5 class="modal-title" id="letterheadLabel">{{ $label }} letterhead</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body row g-3">
                    <div class="col-12"><label class="form-label w-100">Name<input name="name" class="form-control" value="{{ $p['name'] }}" required maxlength="191"></label></div>
                    <div class="col-12"><label class="form-label w-100">Address<textarea name="address" rows="2" class="form-control">{{ $p['address'] }}</textarea></label></div>
                    <div class="col-6"><label class="form-label w-100">Mobile / phone<input name="phone" class="form-control" value="{{ $p['phone'] }}" maxlength="191"></label></div>
                    <div class="col-6"><label class="form-label w-100">Email<input name="email" class="form-control" value="{{ $p['email'] }}" maxlength="191"></label></div>
                    <div class="col-12"><label class="form-label w-100">Payment / bank details<textarea name="payment" rows="5" class="form-control">{{ $p['payment'] }}</textarea></label></div>
                    <div class="col-12"><label class="form-label w-100">Closing line<input name="footer" class="form-control" value="{{ $p['footer'] }}" maxlength="500"></label></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button></div>
            </form>
        </div>
    </div>

    @if ($errors->any())
        <script>document.addEventListener('DOMContentLoaded', function () { bootstrap.Modal.getOrCreateInstance(document.getElementById(@json(old('title') ? 'scheduleNew' : 'ventureEntry'))).show(); });</script>
    @endif
</x-backend.layouts.master>
