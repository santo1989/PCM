<x-backend.layouts.master>
    <x-slot name="pageTitle">Stock Portfolio</x-slot>

    <x-backend.layouts.elements.message :message="session('message')" />
    <x-backend.layouts.elements.errors />

    @php
        $t = $portfolio['totals'];
        $tk = fn($v, $d = 2) => $v === null ? '–' : number_format($v, $d);
        $pct = fn($v) => $v === null ? '–' : number_format($v * 100, 2) . '%';
        $tone = fn($v) => $v === null || abs($v) < 0.005 ? '' : ($v < 0 ? 'text-danger' : 'text-success');
        $today = $portfolio['today'];
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0">Stock Portfolio · DSE</h2>
            <div class="text-muted small">Average-cost method incl. 0.40% commission, same as LBSL. As of {{ \Carbon\Carbon::parse($today)->format('d M Y') }}.
                @php($priced = $symbols->whereIn('symbol', array_column($portfolio['holdings'], 'symbol'))->max('price_date'))
                @if ($priced) Prices from {{ $priced->format('d M Y') }}; fetched from DSE automatically Sun–Thu after 15:15.@endif</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tradeModal"><i class="bi bi-plus-lg"></i> Trade</button>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cashModal"><i class="bi bi-cash-coin"></i> Cash</button>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dividendModal"><i class="bi bi-gift"></i> Dividend</button>
            <form method="POST" action="{{ route('stocks.prices.fetch') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-success btn-sm" title="Latest price and yesterday's close from dse.com.bd for every share you hold">
                    <i class="bi bi-cloud-download"></i> Fetch DSE prices
                </button>
            </form>
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#priceModal"><i class="bi bi-tags"></i> Edit prices</button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['Total equity', $t['equity'], 'Cash ' . $tk($t['cash_balance'], 0) . ' + market value', null],
            ['Market value', $t['value'], 'Cost ' . $tk($t['cost'], 0) . ($t['unpriced'] ? " · {$t['unpriced']} without price" : ''), null],
            ['Unrealized P/L', $t['unrealized'], $pct($t['unrealized_pct']) . ' after sell commission', $t['unrealized']],
            ['Realized P/L', $t['realized'], 'Commission paid ' . $tk($t['commission'], 0), $t['realized']],
            ['Gain vs capital', $t['gain'], $pct($t['return_pct']) . ' of ' . $tk($t['capital'], 0) . ' invested', $t['gain']],
            ['Dividends', $t['dividends_received'], 'Receivable ' . $tk($t['dividends_receivable'], 0), null],
        ] as [$label, $value, $hint, $sign])
            <div class="col-6 col-lg-4 col-xl-2">
                <div class="card h-100">
                    <div class="card-body p-3">
                        <div class="small text-muted">{{ $label }}</div>
                        <div class="fs-5 fw-bold {{ $sign === null ? '' : $tone($sign) }}">{{ $tk($value, 0) }}</div>
                        <div class="small text-muted">{{ $hint }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto" role="tablist">
        @foreach (['holdings' => 'Holdings', 'realized' => 'Realized P/L', 'trades' => 'Trades', 'cash' => 'Cash & dividends'] as $id => $label)
            <li class="nav-item" role="presentation">
                <button class="nav-link text-nowrap {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $id }}" type="button" role="tab">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-holdings" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="small text-muted mb-2">
                        {{ number_format($t['shares']) }} shares · {{ number_format($t['sellable']) }} sellable today · {{ number_format($t['immature']) }} not matured yet (T+2, Z = T+3, Spot = T+1).
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle text-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Symbol</th><th class="text-end">Qty</th><th class="text-end">Sellable</th><th class="text-end">Avg cost</th>
                                    <th class="text-end">Cost</th><th class="text-end">Price</th><th class="text-end">Day</th><th class="text-end">Market value</th>
                                    <th class="text-end">Unrealized</th><th class="text-end">%</th><th class="text-end">Break-even</th><th class="text-end">Avg days</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($portfolio['holdings'] as $h)
                                    <tr>
                                        <td><span class="fw-semibold">{{ $h['symbol'] }}</span> <span class="badge text-bg-light border">{{ $h['category'] }}</span>
                                            <div class="small text-muted">{{ $h['company'] }}</div></td>
                                        <td class="text-end">{{ number_format($h['qty']) }}</td>
                                        <td class="text-end">{{ number_format($h['sellable']) }}
                                            @if ($h['immature'])<div class="small text-warning">{{ number_format($h['immature']) }} from {{ \Carbon\Carbon::parse($h['next_mature'])->format('d M') }}</div>@endif</td>
                                        <td class="text-end">{{ $tk($h['avg_cost'], 4) }}</td>
                                        <td class="text-end">{{ $tk($h['cost']) }}</td>
                                        <td class="text-end">{{ $tk($h['price']) }}</td>
                                        <td class="text-end {{ $tone($h['day_change_pct']) }}">{{ $pct($h['day_change_pct']) }}</td>
                                        <td class="text-end">{{ $tk($h['value']) }}</td>
                                        <td class="text-end fw-semibold {{ $tone($h['unrealized']) }}">{{ $tk($h['unrealized']) }}</td>
                                        <td class="text-end {{ $tone($h['unrealized_pct']) }}">{{ $pct($h['unrealized_pct']) }}</td>
                                        <td class="text-end">{{ $tk($h['break_even'], 2) }}</td>
                                        <td class="text-end">{{ number_format($h['avg_days']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="12" class="text-center text-muted py-4">No open holdings. Import the workbook with <code>php artisan stocks:import</code> or add a trade.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($portfolio['holdings'])
                                <tfoot class="fw-bold">
                                    <tr><td>Total</td><td class="text-end">{{ number_format($t['shares']) }}</td><td class="text-end">{{ number_format($t['sellable']) }}</td><td></td>
                                        <td class="text-end">{{ $tk($t['cost']) }}</td><td></td><td></td><td class="text-end">{{ $tk($t['value']) }}</td>
                                        <td class="text-end {{ $tone($t['unrealized']) }}">{{ $tk($t['unrealized']) }}</td><td class="text-end {{ $tone($t['unrealized_pct']) }}">{{ $pct($t['unrealized_pct']) }}</td><td></td><td></td></tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-realized" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle text-nowrap mb-0">
                            <thead class="table-light">
                                <tr><th>Symbol</th><th class="text-end">Bought</th><th class="text-end">Buy cost</th><th class="text-end">Sold</th>
                                    <th class="text-end">Sell proceeds</th><th class="text-end">Commission</th><th class="text-end">Realized P/L</th><th>Position</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($portfolio['realized'] as $r)
                                    <tr>
                                        <td><span class="fw-semibold">{{ $r['symbol'] }}</span><div class="small text-muted">{{ $r['company'] }}</div></td>
                                        <td class="text-end">{{ number_format($r['buy_qty']) }}</td><td class="text-end">{{ $tk($r['buy_cost']) }}</td>
                                        <td class="text-end">{{ number_format($r['sell_qty']) }}</td><td class="text-end">{{ $tk($r['sell_net']) }}</td>
                                        <td class="text-end">{{ $tk($r['commission']) }}</td>
                                        <td class="text-end fw-semibold {{ $tone($r['realized']) }}">{{ $tk($r['realized']) }}</td>
                                        <td><span class="badge {{ $r['open'] ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ $r['open'] ? 'Open' : 'Closed' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="fw-bold">
                                <tr><td>Total</td><td></td><td class="text-end">{{ $tk($t['buy']) }}</td><td></td><td class="text-end">{{ $tk($t['sell']) }}</td>
                                    <td class="text-end">{{ $tk($t['commission']) }}</td><td class="text-end {{ $tone($t['realized']) }}">{{ $tk($t['realized']) }}</td><td></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-trades" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 70vh;">
                        <table class="table table-sm table-hover align-middle text-nowrap mb-0">
                            <thead class="table-light sticky-top">
                                <tr><th>Date</th><th>Side</th><th>Symbol</th><th>Market</th><th class="text-end">Qty</th><th class="text-end">Rate</th>
                                    <th class="text-end">Amount</th><th class="text-end">Commission</th><th class="text-end">Net</th><th class="text-end">Realized P/L</th></tr>
                            </thead>
                            <tbody>
                                @foreach (array_reverse($portfolio['trades']) as $tr)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($tr['date'])->format('d M Y') }}</td>
                                        <td><span class="badge {{ $tr['side'] === 'Buy' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $tr['side'] }}</span></td>
                                        <td class="fw-semibold">{{ $tr['symbol'] }}</td><td>{{ $tr['market'] }}</td>
                                        <td class="text-end">{{ number_format($tr['qty']) }}</td><td class="text-end">{{ $tk($tr['rate'], 4) }}</td>
                                        <td class="text-end">{{ $tk($tr['amount']) }}</td><td class="text-end">{{ $tk($tr['commission']) }}</td><td class="text-end">{{ $tk($tr['net']) }}</td>
                                        <td class="text-end {{ $tone($tr['realized']) }}">{{ $tr['realized'] === null ? '' : $tk($tr['realized']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-cash" role="tabpanel">
            <div class="row g-3">
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header d-flex flex-wrap gap-2 justify-content-between">
                            <span>BO account cash</span>
                            <span class="small">Balance <span class="fw-bold">{{ $tk($t['cash_balance']) }}</span></span>
                        </div>
                        <div class="card-body">
                            <div class="small text-muted mb-2">Deposits {{ $tk($t['deposit'], 0) }} − withdrawals {{ $tk($t['withdraw'], 0) }} − fees {{ $tk($t['fees'], 0) }} + dividends {{ $tk($t['dividends_received'], 0) }} − buys {{ $tk($t['buy'], 0) }} + sells {{ $tk($t['sell'], 0) }}</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Particulars</th><th class="text-end">In</th><th class="text-end">Out</th></tr></thead>
                                    <tbody>
                                        @foreach ($cash as $c)
                                            <tr><td class="text-nowrap">{{ $c->date->format('d M Y') }}</td><td>{{ $c->type }}</td><td class="small">{{ $c->particulars }}</td>
                                                <td class="text-end text-success">{{ $c->cash_in ? $tk($c->cash_in) : '' }}</td><td class="text-end text-danger">{{ $c->cash_out ? $tk($c->cash_out) : '' }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header">Dividends</div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 text-nowrap">
                                    <thead class="table-light"><tr><th>Symbol</th><th>Record date</th><th class="text-end">%</th><th class="text-end">Qty</th><th class="text-end">Gross</th><th class="text-end">Net</th><th>Status</th></tr></thead>
                                    <tbody>
                                        @foreach ($dividends as $d)
                                            <tr><td class="fw-semibold">{{ $d->symbol }}<div class="small text-muted fw-normal">{{ $d->bo_account }}</div></td>
                                                <td>{{ optional($d->record_date)->format('d M Y') }}</td><td class="text-end">{{ $d->percent + 0 }}</td><td class="text-end">{{ $d->qty }}</td>
                                                <td class="text-end">{{ $tk($d->gross) }}</td><td class="text-end">{{ $tk($d->net) }}</td>
                                                <td><span class="badge {{ $d->status === 'Received' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $d->status }}</span>
                                                    @if ($d->received_date)<div class="small text-muted">{{ $d->received_date->format('d M Y') }}</div>@endif</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <datalist id="stockSymbols">
        @foreach ($symbols as $s)<option value="{{ $s->symbol }}">{{ $s->company }}</option>@endforeach
    </datalist>

    {{-- Add trade --}}
    <div class="modal fade" id="tradeModal" tabindex="-1" aria-labelledby="tradeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down">
            <form class="modal-content" method="POST" action="{{ route('stocks.trades.store') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="tradeModalLabel">Add trade</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body row g-3">
                    <div class="col-6"><label class="form-label" for="tr_date">Date</label><input type="date" id="tr_date" name="date" class="form-control" value="{{ old('date', $today) }}" required></div>
                    <div class="col-6"><label class="form-label" for="tr_side">Buy / Sell</label>
                        <select id="tr_side" name="side" class="form-select">@foreach (['Buy', 'Sell'] as $o)<option {{ old('side') === $o ? 'selected' : '' }}>{{ $o }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label" for="tr_symbol">Symbol</label><input id="tr_symbol" name="symbol" list="stockSymbols" class="form-control text-uppercase" value="{{ old('symbol') }}" required maxlength="32" autocomplete="off"></div>
                    <div class="col-6"><label class="form-label" for="tr_market">Market</label>
                        <select id="tr_market" name="market" class="form-select">@foreach (['Public', 'Spot'] as $o)<option {{ old('market') === $o ? 'selected' : '' }}>{{ $o }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label" for="tr_qty">Quantity</label><input type="number" id="tr_qty" name="qty" min="1" step="1" class="form-control" value="{{ old('qty') }}" required></div>
                    <div class="col-6"><label class="form-label" for="tr_rate">Rate (Tk)</label><input type="number" id="tr_rate" name="rate" min="0.0001" step="any" class="form-control" value="{{ old('rate') }}" required></div>
                    <div class="col-12"><label class="form-label" for="tr_comm">Commission (Tk)</label><input type="number" id="tr_comm" name="commission" min="0" step="any" class="form-control" value="{{ old('commission') }}" placeholder="Empty = 0.40% of amount">
                        <div class="form-text">Leave empty to use 0.40%, or type the amount from the LBSL ledger.</div></div>
                    <div class="col-12"><details><summary class="small">New stock? Company and DSE category</summary>
                        <div class="row g-2 mt-1">
                            <div class="col-8"><input name="company" class="form-control" placeholder="Company name" value="{{ old('company') }}" maxlength="191" aria-label="Company name"></div>
                            <div class="col-4"><select name="category" class="form-select" aria-label="DSE category">@foreach (array_keys(\App\Services\StockPortfolio::SETTLEMENT_DAYS) as $c)<option {{ old('category') === $c ? 'selected' : '' }}>{{ $c }}</option>@endforeach</select></div>
                        </div></details></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save trade</button></div>
            </form>
        </div>
    </div>

    {{-- Add cash movement --}}
    <div class="modal fade" id="cashModal" tabindex="-1" aria-labelledby="cashModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down">
            <form class="modal-content" method="POST" action="{{ route('stocks.cash.store') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="cashModalLabel">BO account cash</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body row g-3">
                    <div class="col-6"><label class="form-label" for="cs_date">Date</label><input type="date" id="cs_date" name="date" class="form-control" value="{{ $today }}" required></div>
                    <div class="col-6"><label class="form-label" for="cs_type">Type</label>
                        <select id="cs_type" name="type" class="form-select">@foreach (['Deposit', 'Withdraw', 'Fee', 'Dividend'] as $o)<option>{{ $o }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label" for="cs_amount">Amount (Tk)</label><input type="number" id="cs_amount" name="amount" min="0.01" step="any" class="form-control" required></div>
                    <div class="col-12"><label class="form-label" for="cs_text">Particulars</label><input id="cs_text" name="particulars" class="form-control" maxlength="191" placeholder="e.g. SCB NPS collection"></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button></div>
            </form>
        </div>
    </div>

    {{-- Add dividend --}}
    <div class="modal fade" id="dividendModal" tabindex="-1" aria-labelledby="dividendModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down">
            <form class="modal-content" method="POST" action="{{ route('stocks.dividends.store') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="dividendModalLabel">Dividend</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body row g-3">
                    <div class="col-6"><label class="form-label" for="dv_symbol">Symbol</label><input id="dv_symbol" name="symbol" list="stockSymbols" class="form-control text-uppercase" required maxlength="32" autocomplete="off"></div>
                    <div class="col-6"><label class="form-label" for="dv_bo">BO account</label><input id="dv_bo" name="bo_account" class="form-control" value="LBSL" maxlength="191"></div>
                    <div class="col-6"><label class="form-label" for="dv_record">Record date</label><input type="date" id="dv_record" name="record_date" class="form-control" required></div>
                    <div class="col-6"><label class="form-label" for="dv_pct">Dividend %</label><input type="number" id="dv_pct" name="percent" min="0" step="any" class="form-control"></div>
                    <div class="col-6"><label class="form-label" for="dv_qty">Shares held</label><input type="number" id="dv_qty" name="qty" min="0" step="1" class="form-control"></div>
                    <div class="col-6"><label class="form-label" for="dv_gross">Gross (Tk)</label><input type="number" id="dv_gross" name="gross" min="0.01" step="any" class="form-control" required></div>
                    <div class="col-6"><label class="form-label" for="dv_tax">Tax at source (Tk)</label><input type="number" id="dv_tax" name="tax" min="0" step="any" class="form-control"></div>
                    <div class="col-6"><label class="form-label" for="dv_received">Received on</label><input type="date" id="dv_received" name="received_date" class="form-control">
                        <div class="form-text">Empty = still receivable. When set, the net amount is also added as BO cash.</div></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button></div>
            </form>
        </div>
    </div>

    {{-- Update prices --}}
    <div class="modal fade" id="priceModal" tabindex="-1" aria-labelledby="priceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
            <form class="modal-content" method="POST" action="{{ route('stocks.prices.update') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="priceModalLabel">Market prices (LTP)</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <p class="small text-muted">Today's last traded price from dsebd.org. The price it replaces becomes yesterday's close for the day change.</p>
                    @php($held = array_column($portfolio['holdings'], 'symbol'))
                    @foreach ($symbols->sortBy(fn($s) => in_array($s->symbol, $held, true) ? 0 : 1) as $s)
                        <div class="row g-2 align-items-center mb-2">
                            <label class="col-5 col-form-label col-form-label-sm" for="px_{{ $s->symbol }}"><span class="fw-semibold">{{ $s->symbol }}</span>
                                @if (in_array($s->symbol, $held, true))<span class="badge text-bg-primary">held</span>@endif</label>
                            <div class="col-7"><input type="number" id="px_{{ $s->symbol }}" name="prices[{{ $s->symbol }}]" min="0.0001" step="any" class="form-control form-control-sm text-end" value="{{ $s->price }}"
                                placeholder="no price"></div>
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save prices</button></div>
            </form>
        </div>
    </div>

    @if ($errors->any())
        {{-- Reopen the form that failed validation. --}}
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var id = @json(old('side') ? 'tradeModal' : (old('gross') ? 'dividendModal' : (old('prices') ? 'priceModal' : 'cashModal')));
                bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show();
            });
        </script>
    @endif
</x-backend.layouts.master>
