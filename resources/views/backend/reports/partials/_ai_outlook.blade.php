{{--
    AI Outlook: income/expense/net forecast, suggested budgets and cost-cutting
    opportunities for the selected range. Pass $outlook = MLPipeline::run()['outlook']
    (App\Services\Analytics\ForecastingEngine::rangeOutlook). Local statistics only.
--}}
@if (!empty($outlook))
    @php
        $f = $outlook['forecast'];
        $arrow = fn($pct) => $pct > 1 ? ['bi-arrow-up-right', 'rising'] : ($pct < -1 ? ['bi-arrow-down-right', 'falling'] : ['bi-arrow-right', 'steady']);
        [$incomeIcon, $incomeWord] = $arrow($outlook['trend']['income']);
        [$expenseIcon, $expenseWord] = $arrow($outlook['trend']['expense']);
    @endphp
    <div class="card mt-4 ai-outlook">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="bi bi-stars"></i> AI Outlook · next {{ count($f['labels']) }} months</span>
            <span class="small text-muted">Learned from {{ $outlook['window']['from'] }} – {{ $outlook['window']['to'] }} · free, runs on this PC</span>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">Income next month ({{ $f['labels'][0] }})</div>
                        <div class="fs-4 fw-bold text-success">{{ number_format($f['income'][0], 0) }}</div>
                        <div class="small"><i class="bi {{ $incomeIcon }}"></i> {{ $incomeWord }} {{ number_format(abs($outlook['trend']['income']), 1) }}%/month</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">Expense next month</div>
                        <div class="fs-4 fw-bold text-danger">{{ number_format($f['expense'][0], 0) }}</div>
                        <div class="small"><i class="bi {{ $expenseIcon }}"></i> {{ $expenseWord }} {{ number_format(abs($outlook['trend']['expense']), 1) }}%/month</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">Net (what you keep)</div>
                        <div class="fs-4 fw-bold {{ $f['net'][0] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($f['net'][0], 0) }}</div>
                        @if ($outlook['savings_total'] > 0)
                            <div class="small">Could rise by ~{{ number_format($outlook['savings_total'], 0) }} with the cuts below</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-lg-4">
                    <h6 class="mb-2"><i class="bi bi-graph-up"></i> Forecast</h6>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Month</th><th class="text-end">Income</th><th class="text-end">Expense</th><th class="text-end">Net</th></tr></thead>
                        <tbody>
                            @foreach ($f['labels'] as $i => $label)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-end">{{ number_format($f['income'][$i], 0) }}</td>
                                    <td class="text-end">{{ number_format($f['expense'][$i], 0) }}</td>
                                    <td class="text-end {{ $f['net'][$i] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($f['net'][$i], 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="col-12 col-lg-4">
                    <h6 class="mb-2"><i class="bi bi-scissors"></i> Cost-cutting opportunities</h6>
                    @forelse ($outlook['savings'] as $s)
                        <div class="d-flex justify-content-between gap-2 small py-1 border-bottom">
                            <span class="text-truncate" title="{{ $s['category'] }}">{{ $s['category'] }}</span>
                            <span class="text-nowrap text-success fw-semibold">save ~{{ number_format($s['potential'], 0) }}/mo</span>
                        </div>
                        <div class="small text-muted mb-1">Recent {{ number_format($s['recent'], 0) }} vs usual {{ number_format($s['usual'], 0) }} a month</div>
                    @empty
                        <p class="small text-muted mb-0">No category is running above its usual level. Nice.</p>
                    @endforelse
                </div>

                <div class="col-12 col-lg-4">
                    <h6 class="mb-2"><i class="bi bi-bullseye"></i> Suggested budgets for {{ $f['labels'][0] }}</h6>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Category</th><th class="text-end">Avg</th><th class="text-end">Budget</th></tr></thead>
                        <tbody>
                            @foreach (array_slice($outlook['budgets'], 0, 8) as $b)
                                <tr>
                                    <td class="text-truncate" style="max-width: 160px;" title="{{ $b['category'] }}">{{ $b['category'] }}</td>
                                    <td class="text-end text-muted">{{ number_format($b['average'], 0) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($b['suggested'], 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="small text-muted mt-1">Lower of forecast and average, so the plan nudges spending down.
                        <a href="{{ route('categories.index') }}">Set as budgets</a></div>
                </div>
            </div>
        </div>
    </div>
@endif
