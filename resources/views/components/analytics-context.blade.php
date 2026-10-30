{{-- Analytics context for a report period. Data: App\Services\Analytics\PeriodContext::build(). --}}
@php
    $fmt = fn($v) => number_format((float) $v);
    $cur = $ctx['current'];
    $names = [
        'previous_period' => 'Previous period',
        'same_period_last_year' => 'Same dates last year',
        'same_hijri_period_last_year' => 'Same Hijri dates last year',
        'average_3_months' => '3-month average',
        'average_6_months' => '6-month average',
        'average_12_months' => '12-month average',
    ];
    $change = function ($p, $higherIsGood) {
        if ($p === null) return '<span class="text-muted">–</span>';
        $good = $higherIsGood ? $p >= 0 : $p <= 0;
        return '<span class="' . ($p == 0 ? 'text-muted' : ($good ? 'text-success' : 'text-danger')) . '">' . ($p > 0 ? '+' : '') . $p . '%</span>';
    };
@endphp
<div class="card mb-4 analytics-context">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-1">
        <span><i class="bi bi-activity"></i> Analytics for {{ $ctx['from'] }} – {{ $ctx['to'] }}</span>
        <a href="{{ route('analytics.index') }}" class="small no-print">Full analytics →</a>
    </div>
    <div class="card-body">
        @if ($ctx['unusual'])
            <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle"></i>
                Spending this month ({{ $fmt($cur['expense']) }}) is unusual: about {{ $fmt($ctx['unusual']['usual']) }} is typical
                ({{ $ctx['unusual']['score'] }} robust SDs).</div>
        @endif

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-3">
                <thead>
                    <tr><th>Compared with</th><th class="text-end">Income</th><th class="text-end">Change</th>
                        <th class="text-end">Expense</th><th class="text-end">Change</th>
                        <th class="text-end">Everyday spending</th><th class="text-end">Change</th></tr>
                </thead>
                <tbody>
                    <tr class="fw-bold"><td>This period</td><td class="text-end">{{ $fmt($cur['income']) }}</td><td></td>
                        <td class="text-end">{{ $fmt($cur['expense']) }}</td><td></td><td class="text-end">{{ $fmt($cur['core']) }}</td><td></td></tr>
                    @foreach ($ctx['compare'] as $key => $c)
                        <tr>
                            <td>{{ $names[$key] ?? $key }}<div class="small text-muted">{{ $c['from'] }} – {{ $c['to'] }}@isset($c['months']) ({{ $c['months'] }} months, scaled)@endisset</div></td>
                            @foreach (['income' => true, 'expense' => false, 'core' => false] as $f => $up)
                                <td class="text-end">{{ $fmt($c[$f]) }}</td>
                                <td class="text-end">{!! $change($c["{$f}_change_percent"], $up) !!}
                                    @if (($c["{$f}_real_change_percent"] ?? null) !== null && $c["{$f}_real_change_percent"] !== $c["{$f}_change_percent"])
                                        <div class="small text-muted" title="In today's taka, using Bangladesh CPI inflation">real {!! $change($c["{$f}_real_change_percent"], $up) !!}</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($ctx['forecast'])
            <div class="row g-2 mb-3">
                @foreach (['income' => 'Income', 'core' => 'Everyday spending'] as $key => $label)
                    @if ($f = $ctx['forecast'][$key] ?? null)
                        <div class="col-md-6"><div class="border rounded p-2 h-100">
                            <div class="small text-muted">Forecast {{ $label }} {{ $f['month'] }}</div>
                            <div class="fw-bold">{{ $fmt($f['expected']) }} <span class="small fw-normal text-muted">likely {{ $fmt($f['range_80'][0]) }} – {{ $fmt($f['range_80'][1]) }}</span></div>
                            <span class="badge text-bg-light border">Model: {{ $f['model'] }}@if ($f['backtest_mase'] !== null) · MASE {{ $f['backtest_mase'] }}@endif</span>
                        </div></div>
                    @endif
                @endforeach
            </div>
        @endif

        @if ($i = $ctx['insights'])
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong class="small"><i class="bi bi-lightbulb"></i> Insights</strong>
                    @if ($i['source'] === 'template')
                        <span class="badge text-bg-secondary" title="{{ $i['rejected'] ?? 'AI is off' }}">Built-in text</span>
                    @else
                        <span class="badge text-bg-info">AI: {{ $i['model'] }} · numbers checked</span>
                    @endif
                </div>
                <ul class="mb-1">@foreach ($i['insights'] as $s)<li>{{ $s }}</li>@endforeach</ul>
                <ul class="mb-0 small">@foreach ($i['actions'] as $s)<li><strong>Do:</strong> {{ $s }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($ctx['transactions'])
            <div class="small fw-bold mb-1"><i class="bi bi-exclamation-triangle"></i> Unusual transactions in this period</div>
            <ul class="small mb-3">
                @foreach ($ctx['transactions'] as $t)
                    <li>{{ $t->date }} · {{ $t->name }} ({{ $t->category }}) · {{ $fmt(str_replace(',', '', $t->amount)) }} — {{ $t->reason }}</li>
                @endforeach
            </ul>
        @endif

        <div class="small text-muted">
            <strong>Data quality:</strong>
            Amounts read exactly from the ledger; transfers between your own accounts are not counted.
            @if ($ctx['quality']['cut_at_today']) Figures run to today; the rest of the period has not happened yet. @endif
            @if ($cur['one_off']) {{ $ctx['quality']['one_offs'] }} one-off purchases ({{ $fmt($cur['one_off']) }}) are in expense but not in everyday spending. @endif
            @if ($ctx['quality']['comma_amounts']) {{ $ctx['quality']['comma_amounts'] }} amount(s) written with a comma are counted in full here; the older totals above may count them as a smaller number. @endif
            @if ($ctx['quality']['months_without_entries']) {{ $ctx['quality']['months_without_entries'] }} month(s) in the range have no entries. @endif
            Comparison periods before the first entry are left out.
            "Real" changes compare both periods in today's taka using Bangladesh CPI inflation (config/cpi.php; some months estimated).
        </div>
    </div>
</div>
