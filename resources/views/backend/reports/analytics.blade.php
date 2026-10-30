<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Analytics
    </x-slot>

    @php
        $fmt = fn($v) => number_format((float) $v);
        $sourceBadge = fn($i) => $i['source'] === 'template'
            ? '<span class="badge text-bg-secondary" title="' . e($i['rejected'] ?? 'AI is off') . '">Built-in text</span>'
            : '<span class="badge text-bg-info">AI: ' . e($i['model']) . ' · numbers checked</span>';
    @endphp

    <div class="container-fluid pt-4">

        @include('backend.reports.partials.report_nav')

        <div class="gradient-header mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="mb-1">Analytics</h2>
                <div class="small">Forecasts with back-tested accuracy, unusual spending, regular payments, cash runway and Eid planning.
                    @if ($run) Last computed {{ \Carbon\Carbon::parse($runAt)->format('d M Y, H:i') }}. @endif</div>
            </div>
            <div class="d-flex gap-2 no-print">
                @can('Admin')
                    <form method="POST" action="{{ route('analytics.recompute') }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerHTML = 'Recomputing…';">
                        @csrf
                        <button class="btn btn-light btn-sm"><i class="bi bi-arrow-clockwise"></i> Recompute</button>
                    </form>
                @endcan
                @if ($run)
                    <a href="{{ route('analytics.export_excel') }}" class="btn btn-light btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                @endif
                <button type="button" class="btn btn-light btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>
            </div>
        </div>

        @foreach (['success', 'error'] as $flash)
            @if (session($flash))
                <div class="alert alert-{{ $flash === 'success' ? 'success' : 'danger' }}">{{ session($flash) }}</div>
            @endif
        @endforeach

        @if (!$run)
            <div class="alert alert-info">No analytics yet. Click <strong>Recompute</strong> or run <code>php artisan analytics:run</code>.</div>
        @else
            {{-- Plain-language explanations: AI-written (numbers checked against the data) or built-in text. --}}
            @php
                $explain = function ($report, $title, $icon) use ($insights, $sourceBadge) {
                    $i = $insights[$report] ?? null;
                    if (!$i) return '';
                    $html = '<div class="card mb-4"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-1">'
                        . '<span><i class="bi ' . $icon . '"></i> ' . e($title) . '</span>' . $sourceBadge($i) . '</div><div class="card-body"><ul class="mb-2">';
                    foreach ($i['insights'] as $s) $html .= '<li>' . e($s) . '</li>';
                    $html .= '</ul><div class="small fw-bold mb-1">What to do</div><ul class="mb-0">';
                    foreach ($i['actions'] as $s) $html .= '<li>' . e($s) . '</li>';
                    return $html . '</ul></div></div>';
                };
                $exec = $insights['executive']['metrics'] ?? [];
            @endphp

            {{-- ===== Executive summary ===== --}}
            <h5 class="mb-3"><i class="bi bi-clipboard-data"></i> Summary — {{ $exec['month'] ?? '' }}</h5>
            <div class="row g-3 mb-3">
                @foreach ([
                    ['Income', $exec['income'] ?? 0, null],
                    ['Spending', $exec['spending'] ?? 0, ($exec['one_off_spending'] ?? 0) ? 'incl. ' . $fmt($exec['one_off_spending']) . ' one-off' : null],
                    ['Kept', $exec['kept'] ?? 0, ($exec['savings_rate_percent'] ?? '–') . '% of income (guide: 20%)'],
                    ['Liquid money', $exec['liquid_money'] ?? 0, 'covers ~' . ($exec['emergency_months_covered'] ?? '–') . ' months of spending'],
                ] as [$title, $value, $sub])
                    <div class="col-6 col-lg-3">
                        <div class="card h-100"><div class="card-body">
                            <div class="small text-muted">{{ $title }}</div>
                            <div class="fs-4 fw-bold {{ $value < 0 ? 'text-danger' : '' }}">{{ $fmt($value) }}</div>
                            @if ($sub)<div class="small text-muted">{{ $sub }}</div>@endif
                        </div></div>
                    </div>
                @endforeach
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="card h-100"><div class="card-body">
                        <div class="small text-muted mb-2">Share of income vs the 50/30/20 guide</div>
                        @foreach (['Needs' => [$exec['needs_percent_of_income'] ?? 0, 50], 'Wants' => [$exec['wants_percent_of_income'] ?? 0, 30]] as $name => [$pct, $guide])
                            <div class="d-flex justify-content-between small"><span>{{ $name }}</span><span>{{ $pct }}% (guide {{ $guide }}%)</span></div>
                            <div class="progress mb-2" role="progressbar" aria-label="{{ $name }} share of income" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar {{ $pct > $guide ? 'bg-warning' : 'bg-success' }}" style="width: {{ min(100, max(0, $pct)) }}%"></div>
                            </div>
                        @endforeach
                    </div></div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100"><div class="card-body">
                        <div class="small text-muted mb-2">Next month</div>
                        @foreach (['next_month_income' => 'Income', 'next_month_core_spending' => 'Everyday spending'] as $key => $name)
                            @if ($f = $exec[$key] ?? null)
                                <div class="d-flex justify-content-between flex-wrap">
                                    <span>{{ $name }} {{ $f['month'] }}</span>
                                    <strong>{{ $fmt($f['expected']) }}</strong>
                                </div>
                                <div class="small text-muted mb-2">likely {{ $fmt($f['range_80'][0]) }} – {{ $fmt($f['range_80'][1]) }} ·
                                    <span class="badge text-bg-light border">Model: {{ $f['model'] }} · MASE {{ $f['backtest_mase'] }}</span></div>
                            @endif
                        @endforeach
                    </div></div>
                </div>
            </div>
            {!! $explain('executive', 'Summary insights', 'bi-lightbulb') !!}

            {{-- ===== Trend + forecast chart ===== --}}
            <h5 class="mb-3"><i class="bi bi-graph-up-arrow"></i> Trend and forecast</h5>
            <div class="card mb-4"><div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2 no-print">
                    <label for="seriesPick" class="small text-muted">Series</label>
                    <select id="seriesPick" class="form-select form-select-sm w-auto">
                        @foreach ($series as $name => $s)
                            <option value="{{ $name }}">{{ $s['label'] }}</option>
                        @endforeach
                    </select>
                    <span id="seriesModel" class="badge text-bg-light border"></span>
                    <label for="windowPick" class="small text-muted ms-md-2">Show</label>
                    <select id="windowPick" class="form-select form-select-sm w-auto">
                        <option value="12">last 12 months</option>
                        <option value="24" selected>last 24 months</option>
                        <option value="0">all months</option>
                    </select>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="oneOffToggle">
                        <label class="form-check-label small" for="oneOffToggle">Include one-offs</label>
                    </div>
                    <div class="form-check form-switch ms-md-auto mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="realToggle">
                        <label class="form-check-label small" for="realToggle" title="Bangladesh CPI inflation, config/cpi.php (some months estimated)">Today's taka (after inflation)</label>
                    </div>
                </div>
                <div style="position: relative; height: 340px"><canvas id="forecastChart" aria-label="Monthly actuals and forecast" role="img"></canvas></div>
                <div id="seriesNote" class="small text-muted mt-2"></div>
                <div class="small text-muted">Shaded bands: 80% and 95% forecast ranges from the back-test errors. ▲ unusual month · ◆ start of a lasting level change. Forecasts are for spending without one-off purchases; "Include one-offs" adds them back to the actual line only.</div>
            </div></div>

            <div class="row g-3">
                {{-- ===== Hand cash ===== --}}
                <div class="col-lg-6">
                    @php($h = $sims['hand_cash'] ?? null)
                    <h5 class="mb-3"><i class="bi bi-cash-coin"></i> Hand cash</h5>
                    @if ($h)
                        <div class="row g-2 mb-3">
                            <div class="col-6"><div class="card h-100"><div class="card-body py-2">
                                <div class="small text-muted">In hand now</div><div class="fs-5 fw-bold">{{ $fmt($h['balance']) }}</div>
                                <div class="small text-muted">~{{ $fmt($h['avg_daily_burn_90d']) }} spent a day</div>
                            </div></div></div>
                            <div class="col-6"><div class="card h-100"><div class="card-body py-2">
                                <div class="small text-muted">Lasts about</div><div class="fs-5 fw-bold">{{ $h['days_until_empty']['p50'] }} days</div>
                                <div class="small text-muted">{{ $h['days_until_empty']['p10'] }}–{{ $h['days_until_empty']['p90'] }} days in {{ $fmt($h['paths']) }} simulations</div>
                            </div></div></div>
                            <div class="col-6"><div class="card h-100"><div class="card-body py-2">
                                <div class="small text-muted">Withdraw for 7 / 30 days</div>
                                <div class="fs-5 fw-bold">{{ $fmt($h['suggested_withdrawal']['7']) }} / {{ $fmt($h['suggested_withdrawal']['30']) }}</div>
                            </div></div></div>
                            <div class="col-6"><div class="card h-100"><div class="card-body py-2">
                                <div class="small text-muted">Withdraw again below</div><div class="fs-5 fw-bold">{{ $fmt($h['reorder_point']) }}</div>
                                @isset($h['history'])<div class="small text-muted">you usually take {{ $fmt($h['history']['median_withdrawal']) }} every {{ $h['history']['median_days_between'] }} days</div>@endisset
                            </div></div></div>
                        </div>
                    @endif
                    {!! $explain('hand_cash', 'Hand cash insights', 'bi-lightbulb') !!}
                </div>

                {{-- ===== Savings goal & runway ===== --}}
                <div class="col-lg-6">
                    @php($s = $sims['savings'] ?? null)
                    <h5 class="mb-3"><i class="bi bi-piggy-bank"></i> Savings goal and runway</h5>
                    @if ($s)
                        <div class="card mb-4"><div class="card-body">
                            <p class="small mb-2">Liquid money {{ $fmt($s['liquid']) }} covers about <strong>{{ $s['emergency_months']['p50'] }} months</strong>
                                ({{ $s['emergency_months']['p10'] }}–{{ $s['emergency_months']['p90'] }}) of spending if income stopped; typical month {{ $fmt($s['median_monthly_spend']) }}.</p>
                            <div class="row g-2 align-items-end no-print">
                                <div class="col-6"><label for="goalAmount" class="form-label small mb-0">Save this much</label>
                                    <input id="goalAmount" type="number" min="0" step="1000" class="form-control form-control-sm" value="200000"></div>
                                <div class="col-6"><label for="goalMonths" class="form-label small mb-0">within (months)</label>
                                    <input id="goalMonths" type="number" min="1" max="24" class="form-control form-control-sm" value="12"></div>
                            </div>
                            <div id="goalResult" class="mt-2 fw-bold" aria-live="polite"></div>
                            <div class="small text-muted">From {{ $fmt($s['paths']) }} simulated futures built from your last 24 months of income minus spending.</div>
                        </div></div>
                    @endif
                </div>
            </div>

            {{-- ===== Bank / account details ===== --}}
            <h5 class="mb-3"><i class="bi bi-bank"></i> Accounts</h5>
            <div class="row g-3 mb-2">
                <div class="col-lg-7">
                    <div class="card"><div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>Account</th><th class="text-end">Balance</th><th class="text-end">In (12 mo)</th><th class="text-end">Out (12 mo)</th><th class="text-end">Net (12 mo)</th><th>Last entry</th></tr></thead>
                            <tbody>
                                @foreach ($accounts as $key => $a)
                                    <tr>
                                        <td>{{ $a['name'] }}
                                            <span class="badge {{ ['everyday' => 'text-bg-success', 'savings' => 'text-bg-primary', 'debt' => 'text-bg-warning'][$a['role']] }}">{{ $a['role'] }}</span>
                                            @if ($a['idle'])<span class="badge text-bg-secondary" title="1,000 or more sitting in an everyday account with no entries for 90+ days">idle</span>@endif
                                        </td>
                                        <td class="text-end {{ $a['balance'] < 0 ? 'text-danger' : '' }}">{{ $fmt($a['balance']) }}</td>
                                        <td class="text-end">{{ $fmt($a['in'] + $a['transfer_in']) }}@if ($a['transfer_in'])<div class="small text-muted">{{ $fmt($a['transfer_in']) }} transfers</div>@endif</td>
                                        <td class="text-end">{{ $fmt($a['out'] + $a['transfer_out']) }}@if ($a['transfer_out'])<div class="small text-muted">{{ $fmt($a['transfer_out']) }} transfers</div>@endif</td>
                                        <td class="text-end {{ $a['net'] < 0 ? 'text-danger' : 'text-success' }}">{{ $a['net'] > 0 ? '+' : '' }}{{ $fmt($a['net']) }}</td>
                                        <td class="small text-nowrap">{{ $a['last_activity'] ?? '–' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div></div>
                    <p class="small text-muted mt-1">"Transfers" are moves between your own accounts (e.g. bank to hand cash). Debt accounts show what is owed as a negative balance. Account roles are guessed from names; adjust them in <code>LedgerSeries::LIQUID_ACCOUNTS</code>.</p>
                </div>
                <div class="col-lg-5">
                    <div class="card"><div class="card-body">
                        <div class="d-flex gap-2 align-items-center mb-2">
                            <label for="accountPick" class="small text-muted">Balance of</label>
                            <select id="accountPick" class="form-select form-select-sm w-auto">
                                @foreach ($accounts as $key => $a)<option value="{{ $key }}">{{ $a['name'] }}</option>@endforeach
                            </select>
                        </div>
                        <div style="position: relative; height: 240px"><canvas id="accountChart" aria-label="Account balance at the end of each month" role="img"></canvas></div>
                    </div></div>
                </div>
            </div>

            {{-- ===== Recurring commitments ===== --}}
            <h5 class="mb-3"><i class="bi bi-arrow-repeat"></i> Regular payments</h5>
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card mb-4"><div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>Payment</th><th>Every</th><th class="text-end">Amount</th><th class="text-end">Per month</th><th>Next due</th></tr></thead>
                            <tbody>
                                @forelse ($recurring as $r)
                                    <tr><td>{{ $r['label'] }}<div class="small text-muted">{{ $r['name'] }} · seen {{ $r['occurrences'] }}×</div></td>
                                        <td>{{ $r['cadence'] }}</td><td class="text-end">{{ $fmt($r['amount']) }}</td>
                                        <td class="text-end">{{ $fmt($r['monthly_equivalent']) }}</td><td>{{ $r['next_date'] }}</td></tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">No regular payments detected.</td></tr>
                                @endforelse
                            </tbody>
                            @if (count($recurring))
                                <tfoot><tr><th colspan="3">Committed per month</th><th class="text-end">{{ $fmt(collect($recurring)->sum('monthly_equivalent')) }}</th><th></th></tr></tfoot>
                            @endif
                        </table>
                    </div></div>
                </div>
                <div class="col-lg-5">
                    <div class="card mb-4"><div class="card-body">
                        <div class="small text-muted mb-2">Next 12 months of committed payments</div>
                        <div style="position: relative; height: 200px"><canvas id="calendarChart" aria-label="Committed payments per month" role="img"></canvas></div>
                    </div></div>
                </div>
            </div>
            {!! $explain('recurring', 'Regular payment insights', 'bi-lightbulb') !!}

            {{-- ===== Eid / Ramadan ===== --}}
            <h5 class="mb-3"><i class="bi bi-moon-stars"></i> Ramadan and Eid planning</h5>
            <div class="card mb-3"><div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Event</th><th class="text-end">Usual extra spend</th><th>Past years (extra)</th><th>Next</th><th class="text-end">Save per month</th></tr></thead>
                    <tbody>
                        @foreach ($sims['events'] ?? [] as $e)
                            <tr><td>{{ $e['event'] }}</td><td class="text-end">{{ $e['median_extra'] !== null ? $fmt($e['median_extra']) : '–' }}</td>
                                <td class="small">@foreach ($e['past'] as $p){{ $p['month'] }}: {{ $fmt($p['extra']) }}@if (!$loop->last), @endif @endforeach</td>
                                <td>{{ $e['next_month'] ?? '–' }}</td><td class="text-end fw-bold">{{ $e['monthly_saving'] !== null ? $fmt($e['monthly_saving']) : '–' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>
            <p class="small text-muted">Extra spend = needs + wants in the event month minus the usual of nearby non-event months. Dates use the Umm al-Qura calendar; Bangladesh can be a day later.</p>
            {!! $explain('events', 'Event insights', 'bi-lightbulb') !!}

            {{-- ===== Unusual spending ===== --}}
            <h5 class="mb-3"><i class="bi bi-exclamation-triangle"></i> Unusual transactions</h5>
            <div class="card mb-3"><div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Item</th><th>Category</th><th class="text-end">Amount</th><th>Why flagged</th></tr></thead>
                    <tbody>
                        @forelse ($transactions as $t)
                            <tr><td class="text-nowrap">{{ $t->date }}</td><td>{{ $t->name }}</td><td class="small">{{ $t->category }}</td>
                                <td class="text-end">{{ $fmt(str_replace(',', '', $t->amount)) }}</td><td class="small">{{ $t->reason }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Nothing unusual.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div>
            {!! $explain('anomalies', 'Unusual spending insights', 'bi-lightbulb') !!}

            {{-- ===== Back-test accuracy ===== --}}
            <h5 class="mb-3"><i class="bi bi-bullseye"></i> Forecast accuracy (back-test)</h5>
            <div class="card mb-3"><div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Series</th><th>Model used</th><th class="text-end">MASE</th><th class="text-end">sMAPE</th><th class="text-end">80% / 95% range hit</th><th class="text-end">Months</th><th>Note</th></tr></thead>
                    <tbody>
                        @foreach ($backtest as $name => $rows)
                            @php($c = $rows->firstWhere('chosen', true))
                            <tr>
                                <td>{{ $c['label'] }}</td>
                                <td>{{ $c['model'] }}
                                    @if ($rows->count() > 1)
                                        <div class="small text-muted">tried: {{ $rows->map(fn($r) => $r['model'] . ($r['mase'] !== null ? ' ' . number_format($r['mase'], 2) : ''))->implode(', ') }}</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ $c['mase'] !== null ? number_format($c['mase'], 2) : '–' }}</td>
                                <td class="text-end">{{ $c['smape'] !== null ? number_format($c['smape'], 0) . '%' : '–' }}</td>
                                <td class="text-end">{{ $c['cover80'] !== null ? round($c['cover80'] * 100) . '% / ' . round($c['cover95'] * 100) . '%' : '–' }}</td>
                                <td class="text-end">{{ $c['n_points'] }}</td>
                                <td class="small text-muted">{{ $c['note'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>
            <p class="small text-muted">MASE below 1 means the model beat "same month last year" when tested on months it had not seen (rolling origin, 1–3 months ahead). The range-hit columns show how often the real month landed inside the 80% and 95% ranges.</p>

            {{-- ===== Data quality ===== --}}
            <div class="alert alert-light border small">
                <strong>Data quality.</strong>
                {{ $quality['months'] }} completed months ({{ $quality['from'] }} to {{ $quality['to'] }}); the current month is left out until it ends.
                Amounts are read exactly (including entries like "5,315").
                {{ $quality['one_offs'] }} purchases are treated as one-offs and kept out of the forecasts ({{ $quality['overrides'] }} set by hand).
                @if ($quality['future_rows']) {{ $quality['future_rows'] }} future-dated entries are ignored. @endif
                Transfers between your own accounts are not counted as income or spending.
            </div>
        @endif
    </div>

    @if ($run)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <script>
            (function () {
                const series = @json($series);
                const quantiles = @json($sims['savings']['quantiles'] ?? []);
                const cumulative = @json($sims['savings']['cumulative_net'] ?? []);
                const calendar = @json($calendar);
                const nf = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });
                const css = getComputedStyle(document.documentElement);
                const text = css.getPropertyValue('--bs-body-color').trim() || '#555';
                const grid = css.getPropertyValue('--bs-border-color').trim() || '#ddd';
                Chart.defaults.color = text;
                Chart.defaults.borderColor = grid;

                const deflate = @json($deflate);
                const realToggle = document.getElementById('realToggle');
                let chart;
                function draw(name) {
                    // Real mode: past months in today's taka (forecast months are already today's money).
                    const raw = series[name];
                    const k = m => realToggle.checked ? (deflate[m] ?? 1) : 1;
                    const base = document.getElementById('oneOffToggle').checked ? raw.with_one_offs : raw.actual;
                    const s = { ...raw,
                        actual: base.map((v, i) => v * k(raw.months[i])),
                        forecast: raw.forecast.map(f => ({ ...f, mean: f.mean * k(f.month), lo80: f.lo80 * k(f.month), hi80: f.hi80 * k(f.month), lo95: f.lo95 * k(f.month), hi95: f.hi95 * k(f.month) })) };
                    const recent = parseInt(document.getElementById('windowPick').value) || s.months.length; // 0 = all months
                    const months = s.months.slice(-recent);
                    const actual = s.actual.slice(-recent);
                    const fm = s.forecast.map(f => f.month);
                    const labels = months.concat(fm);
                    const pad = (vals) => new Array(months.length).fill(null).concat(vals);
                    // The forecast line starts from the last actual so the two connect.
                    const join = (key) => s.forecast.length ? new Array(months.length - 1).fill(null).concat([actual[actual.length - 1]], s.forecast.map(f => f[key])) : [];
                    const marks = (list) => labels.map((m, i) => list.some(a => a.month === m) && i < actual.length ? actual[i] : null);
                    const reasons = {};
                    s.anomalies.concat(s.changepoints).forEach(a => reasons[a.month] = (reasons[a.month] ? reasons[a.month] + '; ' : '') + a.reason);

                    const data = {
                        labels,
                        datasets: [
                            { label: '95% range', data: pad(s.forecast.map(f => f.hi95)), borderWidth: 0, pointRadius: 0, fill: false },
                            { label: '95% range low', data: pad(s.forecast.map(f => f.lo95)), borderWidth: 0, pointRadius: 0, fill: '-1', backgroundColor: 'rgba(13,110,253,.12)' },
                            { label: '80% range', data: pad(s.forecast.map(f => f.hi80)), borderWidth: 0, pointRadius: 0, fill: false },
                            { label: '80% range low', data: pad(s.forecast.map(f => f.lo80)), borderWidth: 0, pointRadius: 0, fill: '-1', backgroundColor: 'rgba(13,110,253,.22)' },
                            { label: 'Actual', data: actual, borderColor: '#198754', backgroundColor: '#198754', tension: .2, pointRadius: 2 },
                            { label: 'Forecast', data: join('mean'), borderColor: '#0d6efd', borderDash: [6, 4], pointRadius: 3 },
                            { label: 'Unusual month', data: marks(s.anomalies), showLine: false, pointStyle: 'triangle', pointRadius: 8, borderColor: '#dc3545', backgroundColor: '#dc3545' },
                            { label: 'Level change', data: marks(s.changepoints), showLine: false, pointStyle: 'rectRot', pointRadius: 8, borderColor: '#fd7e14', backgroundColor: '#fd7e14' },
                        ],
                    };
                    const options = {
                        maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { labels: { filter: i => !i.text.endsWith(' low') } },
                            tooltip: {
                                filter: i => i.raw !== null && !i.dataset.label.endsWith(' low') && !i.dataset.label.startsWith('95') && !i.dataset.label.startsWith('80'),
                                callbacks: {
                                    label: c => `${c.dataset.label}: ${nf.format(c.raw)}`,
                                    afterBody: items => {
                                        const m = items[0].label, f = s.forecast.find(x => x.month === m);
                                        const out = [];
                                        if (f) out.push(`80% range: ${nf.format(f.lo80)} – ${nf.format(f.hi80)}`, `95% range: ${nf.format(f.lo95)} – ${nf.format(f.hi95)}`);
                                        if (reasons[m]) out.push(reasons[m]);
                                        return out;
                                    },
                                },
                            },
                        },
                        scales: { y: { beginAtZero: true, ticks: { callback: v => nf.format(v) } } },
                    };
                    if (chart) chart.destroy();
                    chart = new Chart(document.getElementById('forecastChart'), { type: 'line', data, options });
                    document.getElementById('seriesModel').textContent = s.model + (s.mase !== null ? ' · MASE ' + s.mase.toFixed(2) : '');
                    document.getElementById('seriesNote').textContent = s.note || '';
                }
                const pick = document.getElementById('seriesPick');
                pick.addEventListener('change', () => draw(pick.value));
                ['realToggle', 'oneOffToggle', 'windowPick'].forEach(id => document.getElementById(id).addEventListener('change', () => draw(pick.value)));

                const accounts = @json($accounts);
                const accountPick = document.getElementById('accountPick');
                let accountChart;
                function drawAccount() {
                    const a = accounts[accountPick.value];
                    if (accountChart) accountChart.destroy();
                    accountChart = new Chart(document.getElementById('accountChart'), {
                        type: 'line',
                        data: { labels: a.months, datasets: [{ label: a.name, data: a.balances, borderColor: '#6f42c1', backgroundColor: 'rgba(111,66,193,.15)', fill: true, tension: .2, pointRadius: 2 }] },
                        options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => nf.format(c.raw) } } },
                            scales: { y: { ticks: { callback: v => nf.format(v) } } } },
                    });
                }
                accountPick.addEventListener('change', drawAccount);
                drawAccount();
                draw(pick.value);

                new Chart(document.getElementById('calendarChart'), {
                    type: 'bar',
                    data: { labels: Object.keys(calendar), datasets: [{ label: 'Committed', data: Object.values(calendar), backgroundColor: '#6f42c1' }] },
                    options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => nf.format(c.raw) } } },
                        scales: { y: { beginAtZero: true, ticks: { callback: v => nf.format(v) } } } },
                });

                // Chance of saving a target within N months, read off the simulated quantiles.
                function goal() {
                    const target = parseFloat(document.getElementById('goalAmount').value) || 0;
                    const months = Math.min(24, Math.max(1, parseInt(document.getElementById('goalMonths').value) || 1));
                    const q = cumulative[months - 1];
                    const out = document.getElementById('goalResult');
                    if (!q) { out.textContent = ''; return; }
                    let pct;
                    if (target <= q[0]) pct = '95% or more';
                    else if (target > q[q.length - 1]) pct = 'under 5%';
                    else {
                        let i = q.findIndex(v => v >= target);
                        const lo = quantiles[i - 1], hi = quantiles[i];
                        const level = lo + (hi - lo) * (target - q[i - 1]) / (q[i] - q[i - 1]);
                        pct = Math.round(100 - level) + '%';
                    }
                    out.textContent = `Chance of saving ${nf.format(target)} in ${months} months: ${pct} (typical: ${nf.format(q[9])})`;
                }
                ['goalAmount', 'goalMonths'].forEach(id => document.getElementById(id).addEventListener('input', goal));
                goal();
            })();
        </script>
    @endif
</x-backend.layouts.master>
