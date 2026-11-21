<x-backend.layouts.master>
    <x-slot name="pageTitle">Stock Analysis</x-slot>

    @php
        $n = fn($v, $d = 2) => $v === null ? '-' : number_format($v, $d);
        $pct = fn($v) => $v === null ? '-' : number_format($v * 100, 1) . '%';
        $badge = fn($v) => str_starts_with($v, 'BUY') ? 'bg-success' : (str_starts_with($v, 'WATCH') ? 'bg-info text-dark' : (str_starts_with($v, 'AVOID') ? 'bg-danger' : 'bg-secondary'));
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0">Stock Analysis · DSE</h2>
            <div class="text-muted small">Daily prices from the dse.com.bd day-end archive, fundamentals from the DSE company page (cached a few hours).
                Signals: MA14, MACD(12,26,9), RSI14, last 15 candles. DSE daily limit ±10%.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('stocks.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Portfolio</a>
            <form method="GET" action="{{ route('stocks.analysis') }}" class="d-flex gap-2">
                <input name="symbols" value="{{ $watch }}" class="form-control form-control-sm" style="min-width:260px" placeholder="Watchlist e.g. GP,BRACBANK" title="Comma-separated trading codes">
                <button class="btn btn-primary btn-sm">Analyse</button>
            </form>
            <a href="{{ route('stocks.analysis', ['scan' => 'all']) }}" class="btn btn-outline-success btn-sm" title="Screens every DSE share; the first run takes about a minute"
               onclick="this.classList.add('disabled');this.innerText='Scanning all shares…'"><i class="bi bi-search"></i> Scan all DSE shares</a>
        </div>
    </div>

    @if ($failed)
        <div class="alert alert-warning py-2">No price history from DSE for: {{ implode(', ', $failed) }}.</div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ $scan ? 'Top 20 from the whole market' : 'Holdings and watchlist' }}</strong>
            <span class="text-muted small">· buy range, stop loss, sell range and waiting period · zero-loss sell for shares you hold</span></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle text-nowrap mb-0">
                    <thead><tr>
                        <th>Symbol</th><th>Verdict</th><th class="text-end">Close</th><th class="text-end">Day</th>
                        <th>MA14</th><th>MACD</th><th>Hammer</th><th>Spin. top</th><th>Escal.</th><th class="text-end">RSI</th>
                        <th class="text-end">Buy range</th><th class="text-end">Stop loss</th><th class="text-end">Sell range</th><th class="text-end">R:R</th><th class="text-end">Wait</th>
                        <th class="text-end">Zero-loss sell</th><th class="text-end">Rise needed</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($rows as $sym => $a)
                        @php($p = $a['plan'])
                        <tr>
                            <td><button type="button" class="btn btn-link btn-sm p-0 fw-bold" data-symbol="{{ $sym }}" data-stock-detail="{{ route('stocks.analysis.detail', ['symbol' => $sym] + ($scan ? ['scan' => 1] : [])) }}" title="Show all details">{{ $sym }}</button> <span class="badge bg-light text-dark">{{ $a['fund']['category'] ?? '?' }}</span>
                                @if ($a['held']) <span class="badge bg-primary">held</span> @endif</td>
                            <td><span class="badge {{ $badge($a['verdict']) }}">{{ $a['verdict'] }}</span></td>
                            <td class="text-end">{{ $n($a['close'], 1) }}</td>
                            <td class="text-end {{ $a['change'] < 0 ? 'text-danger' : 'text-success' }}">{{ $pct($a['change']) }}</td>
                            @foreach ($a['signals'] as $on)
                                <td>{!! $on ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' !!}</td>
                            @endforeach
                            <td class="text-end">{{ $n($a['rsi'], 0) }}</td>
                            <td class="text-end">{{ $n($p['buy_low'], 1) }} – {{ $n($p['buy_high'], 1) }}</td>
                            <td class="text-end text-danger">{{ $n($p['stop'], 1) }}</td>
                            <td class="text-end text-success">{{ $n($p['target'], 1) }} – {{ $n($p['sell_high'], 1) }}</td>
                            <td class="text-end">{{ $n($p['rr'], 1) }}</td>
                            <td class="text-end">{{ $p['days'] }} days</td>
                            <td class="text-end">{{ $a['zero'] ? $n($a['zero']['zero_loss_sell'], 1) : '' }}</td>
                            <td class="text-end {{ ($a['zero']['rise_needed'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                                @if ($a['zero']) {{ $pct($a['zero']['rise_needed']) }} <span class="text-muted small">(≥{{ $a['zero']['circuit_days'] }} day{{ $a['zero']['circuit_days'] == 1 ? '' : 's' }})</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="17" class="text-muted">Nothing to show.{{ $scan ? ' No share passed the screen today.' : '' }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="small text-muted mt-2">Zero-loss sell = break-even after both 0.40% commissions + 1% net profit. "≥ N days" = the fewest trading days to reach it at the +10% daily limit; real moves are usually slower.
                Wait = estimated days to reach the sell range from the 14-day average true range. Verdicts are rule-based, not advice.</div>
        </div>
    </div>

    {{-- One popup; each share's details load only when clicked. --}}
    <div class="modal fade" id="stockDetailModal" tabindex="-1" aria-labelledby="stockDetailTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="stockDetailTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body" id="stockDetailBody"></div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var seen = {};
            var body = document.getElementById('stockDetailBody');
            // innerHTML does not run scripts; the AI box needs its script to load the answer.
            var show = function (html) {
                body.innerHTML = html;
                body.querySelectorAll('script').forEach(function (old) {
                    var s = document.createElement('script');
                    s.textContent = old.textContent;
                    old.replaceWith(s);
                });
            };
            document.addEventListener('click', function (e) {
                var b = e.target.closest('[data-stock-detail]');
                if (!b) return;
                var url = new URL(b.dataset.stockDetail, location.href);
                url = location.origin + url.pathname + url.search; // APP_URL may differ from this host
                document.getElementById('stockDetailTitle').textContent = b.dataset.symbol;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('stockDetailModal')).show();
                if (seen[url]) return show(seen[url]);
                body.innerHTML = '<div class="text-center py-5" role="status"><span class="spinner-border" aria-hidden="true"></span><div class="small text-muted mt-2">Loading ' + b.dataset.symbol + '…</div></div>';
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { if (!r.ok) throw new Error(); return r.text(); })
                    .then(function (html) { seen[url] = html; if (document.getElementById('stockDetailTitle').textContent === b.dataset.symbol) show(html); })
                    .catch(function () { body.innerHTML = '<div class="alert alert-warning mb-0">Could not load ' + b.dataset.symbol + ' from DSE. Please try again.</div>'; });
            });
        })();
    </script>
</x-backend.layouts.master>
