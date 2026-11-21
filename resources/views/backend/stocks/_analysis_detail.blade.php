@php
    $n = fn($v, $d = 2) => $v === null ? '-' : number_format($v, $d);
    $pct = fn($v) => $v === null ? '-' : number_format($v * 100, 1) . '%';
    $badge = fn($v) => str_starts_with($v, 'BUY') ? 'bg-success' : (str_starts_with($v, 'WATCH') ? 'bg-info text-dark' : (str_starts_with($v, 'AVOID') ? 'bg-danger' : 'bg-secondary'));
    $callBadge = ['Buy' => 'bg-success', 'Sell' => 'bg-danger', 'Hold' => 'bg-secondary'];
    $f = $a['fund'];
    $p = $a['plan'];
    $latest = $calls[$a['date']] ?? null;
    // Public market figures only: no holdings or cost go to the AI.
    $metrics = ['close' => $a['close'], 'rsi' => round($a['rsi'] ?? 0, 1), 'ma14' => round($a['ma14'], 2), 'support' => $a['support'], 'resistance' => $a['resistance'],
        'target' => $p['target'], 'stop' => $p['stop'], 'signal' => $latest['call'] ?? 'Hold', 'verdict' => $a['verdict']];
    $template = ['insights' => [$sym . ': today\'s signal is ' . ($latest['call'] ?? 'Hold') . '. ' . ($latest['meaning'] ?? '')],
        'actions' => ['Buy only inside ' . $n($p['buy_low'], 1) . ' – ' . $n($p['buy_high'], 1) . ', with a stop loss at ' . $n($p['stop'], 1) . '.']];
@endphp
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <span class="text-muted">{{ $f['sector'] ?? '' }}</span>
    <span><span class="badge {{ $badge($a['verdict']) }}">{{ $a['verdict'] }}</span>
        <span class="small text-muted">Tech score {{ $n($a['tech_score'], 1) }} · Fundamental score {{ $a['fund_score'] }} · data to {{ $a['date'] }}</span></span>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <h6>Fundamental</h6>
        <table class="table table-sm mb-2">
            <tr><td>Category</td><td class="text-end">{{ $f['category'] ?? '-' }}</td></tr>
            <tr><td>P/E (audited)</td><td class="text-end">{{ $n($a['pe'], 1) }}</td></tr>
            <tr><td>EPS (price ÷ P/E)</td><td class="text-end">{{ $n($a['eps']) }}</td></tr>
            <tr><td>NAV / P/B</td><td class="text-end">{{ $n($f['nav'] ?? null) }} / {{ $n($a['pb']) }}</td></tr>
            <tr><td>52-week range</td><td class="text-end">{{ $n($f['low52'] ?? null, 1) }} – {{ $n($f['high52'] ?? null, 1) }}</td></tr>
            <tr><td>Sponsor holding</td><td class="text-end">{{ isset($f['sponsor']) ? $f['sponsor'] . '%' : '-' }}</td></tr>
            <tr><td>Year end</td><td class="text-end">{{ $f['year_end'] ?? '-' }}</td></tr>
        </table>
        <ul class="small mb-0">@foreach ($a['notes'] as $note)<li>{{ $note }}</li>@endforeach</ul>
    </div>
    <div class="col-lg-4">
        <h6>Technical</h6>
        <table class="table table-sm mb-2">
            <tr><td>MA14</td><td class="text-end">{{ $n($a['ma14']) }} {{ $a['close'] > $a['ma14'] ? '(price above)' : '(price below)' }}</td></tr>
            <tr><td>MACD / signal / histogram</td><td class="text-end">{{ $n($a['macd']) }} / {{ $n($a['signal']) }} / {{ $n($a['hist']) }}</td></tr>
            <tr><td>RSI 14</td><td class="text-end">{{ $n($a['rsi'], 1) }} {{ $a['rsi'] > 70 ? 'overbought' : ($a['rsi'] < 30 ? 'oversold' : '') }}</td></tr>
            <tr><td>Support / resistance (20 days)</td><td class="text-end">{{ $n($a['support'], 1) }} / {{ $n($a['resistance'], 1) }}</td></tr>
            <tr><td>ATR 14</td><td class="text-end">{{ $n($a['atr']) }}</td></tr>
            <tr><td>Volume / 20-day avg</td><td class="text-end">{{ number_format($a['volume']) }} / {{ number_format($a['vol_avg']) }}</td></tr>
            <tr><td>OBV last 10 days</td><td class="text-end {{ $a['obv10'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $a['obv10'] >= 0 ? 'Buying pressure' : 'Selling pressure' }}</td></tr>
        </table>
    </div>
    <div class="col-lg-4">
        <h6>Trade plan</h6>
        <table class="table table-sm mb-2">
            <tr><td>Buy range</td><td class="text-end">{{ $n($p['buy_low'], 1) }} – {{ $n($p['buy_high'], 1) }}</td></tr>
            <tr><td>Stop loss</td><td class="text-end text-danger">{{ $n($p['stop'], 1) }}</td></tr>
            <tr><td>Sell range</td><td class="text-end text-success">{{ $n($p['target'], 1) }} – {{ $n($p['sell_high'], 1) }}</td></tr>
            <tr><td>Reward : risk</td><td class="text-end">{{ $n($p['rr'], 1) }}</td></tr>
            <tr><td>Waiting period</td><td class="text-end">about {{ $p['days'] }} trading days</td></tr>
            <tr><td>Tomorrow's price limit</td><td class="text-end">{{ $n($p['lower_limit'], 1) }} – {{ $n($p['upper_limit'], 1) }}</td></tr>
            @if ($a['zero'])
                <tr class="table-primary"><td>You hold {{ number_format($a['zero']['qty']) }} @ {{ $n($a['zero']['avg_cost']) }}</td><td class="text-end">break-even {{ $n($a['zero']['break_even']) }}</td></tr>
                <tr class="table-primary"><td><strong>Zero-loss sell</strong></td><td class="text-end"><strong>{{ $n($a['zero']['zero_loss_sell'], 1) }}</strong> ({{ $pct($a['zero']['rise_needed']) }})</td></tr>
            @endif
        </table>
    </div>
</div>

<x-ai-recommendations :page="'stock:' . $sym" :metrics="$metrics" :template="$template" title="AI view of today's signal" />

<h6 class="mt-3">Last 15 candles</h6>
<div class="table-responsive">
    <table class="table table-sm align-middle text-nowrap mb-0">
        <thead><tr><th>Date</th><th></th><th class="text-end">Open</th><th class="text-end">High</th><th class="text-end">Low</th><th class="text-end">Close</th><th class="text-end">Change</th><th class="text-end">Volume</th><th>Pattern</th><th>AI prediction</th></tr></thead>
        <tbody>
        @foreach (array_reverse($a['candles']) as $k)
            @php($range = max($k['high'] - $k['low'], 0.0001))
            @php($call = $calls[$k['date']] ?? null)
            <tr>
                <td>{{ \Carbon\Carbon::parse($k['date'])->format('d M') }}</td>
                <td style="width:70px">
                    {{-- mini candle: wick = day range, body = open→close --}}
                    <svg width="60" height="14" aria-hidden="true">
                        <line x1="0" x2="60" y1="7" y2="7" stroke="#adb5bd" />
                        <rect x="{{ 60 * (min($k['open'], $k['close']) - $k['low']) / $range }}" y="2" height="10"
                              width="{{ max(2, 60 * abs($k['close'] - $k['open']) / $range) }}" fill="{{ $k['close'] >= $k['open'] ? '#198754' : '#dc3545' }}" />
                    </svg>
                </td>
                <td class="text-end">{{ $n($k['open'], 1) }}</td><td class="text-end">{{ $n($k['high'], 1) }}</td>
                <td class="text-end">{{ $n($k['low'], 1) }}</td><td class="text-end">{{ $n($k['close'], 1) }}</td>
                <td class="text-end {{ $k['change'] < 0 ? 'text-danger' : 'text-success' }}">{{ $pct($k['change']) }}</td>
                <td class="text-end">{{ number_format($k['volume']) }}</td>
                <td>{{ implode(', ', $k['patterns']) }}</td>
                <td style="white-space: normal; min-width: 280px;">
                    @if ($call)<span class="badge {{ $callBadge[$call['call']] }}">{{ $call['call'] }}</span> <span class="small">{{ $call['meaning'] }}</span>@else<span class="text-muted">-</span>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="small text-muted mt-2">AI prediction: Buy / Sell / Hold worked out from that day's price signals only (MA14, MACD, RSI, candles), so past rows show what was known then. The AI box explains today's signal. Not financial advice.</div>
