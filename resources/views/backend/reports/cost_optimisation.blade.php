<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Cost Optimisation
    </x-slot>

    <div class="container-fluid pt-4">

        @include('backend.reports.partials.report_nav')

        <div class="gradient-header mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="mb-1">Cost Optimisation</h2>
                <div class="small">Live view of where this month's spending is running hot — auto-refreshes every 30s.</div>
            </div>
            <div class="text-end">
                <button type="button" id="refreshCostOptBtn" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <div class="small mt-1" id="costOptUpdatedAt" style="color: rgba(255,255,255,0.75);"></div>
            </div>
        </div>

        <h5 class="mb-3"><i class="bi bi-speedometer2"></i> Spending Pace This Month</h5>
        <div id="paceSection">
            @include('backend.reports.partials._cost_optimisation_pace', ['paceRows' => $paceRows, 'totalProjectedOverspend' => $totalProjectedOverspend])
        </div>

        <h5 class="mb-3 mt-4"><i class="bi bi-graph-up"></i> Full-Month Historical Anomalies</h5>
        <div id="anomalySection">
            @include('backend.reports.partials._cost_optimisation_anomalies', ['suggestions' => $suggestions, 'totalPotentialSaving' => $totalPotentialSaving])
        </div>

    </div>

    <script>
        function renderPaceSection(data) {
            const container = document.getElementById('paceSection');

            if (!data.paceRows.length) {
                container.innerHTML = `
                    <div class="alert alert-info">
                        Nothing is running hot yet this month compared to its historical daily pace. This section
                        updates live as new expenses are entered — check back after a few more transactions, or as
                        soon as any category starts spending faster than its usual rate.
                    </div>`;
                return;
            }

            const cards = data.paceRows.map(row => `
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0">${row.category_name}</h5>
                                <span class="badge bg-danger">+${row.variance_percent.toFixed(0)}% pace</span>
                            </div>
                            <p class="text-muted small mb-3">${row.suggestion}</p>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="small text-muted">Spent So Far (${row.days_elapsed}d)</div>
                                    <div class="fw-bold">${Number(row.month_to_date).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                                </div>
                                <div class="col-4">
                                    <div class="small text-muted">Projected Month Total</div>
                                    <div class="fw-bold text-danger">${Number(row.projected_month_total).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                                </div>
                                <div class="col-4">
                                    <div class="small text-muted">Usual Daily Rate</div>
                                    <div class="fw-bold">${Number(row.historical_daily_pace).toLocaleString(undefined, {minimumFractionDigits: 2})}/day</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = `
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card grad-2">
                            <div class="small opacity-75">Projected Overspend If This Pace Continues</div>
                            <div class="fs-3 fw-bold">${Number(data.totalProjectedOverspend).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mb-4">${cards}</div>
            `;
        }

        function renderAnomalySection(data) {
            const container = document.getElementById('anomalySection');

            if (!data.suggestions.length) {
                container.innerHTML = `
                    <div class="alert alert-light border">
                        No categories have crossed their 90th-percentile <em>full month</em> historical spend yet.
                        This check needs at least 4 completed months of history per category and looks at full-month
                        totals — it's a stricter, longer-horizon signal than the live pace section above, so it
                        naturally stays quiet more often, especially early in a month.
                    </div>`;
                return;
            }

            const cards = data.suggestions.map(row => `
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0">${row.category_name}</h5>
                                <span class="badge bg-danger">+${row.over_percent.toFixed(0)}%</span>
                            </div>
                            <p class="text-muted small mb-3">${row.suggestion}</p>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="small text-muted">This Month</div>
                                    <div class="fw-bold">${Number(row.current).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                                </div>
                                <div class="col-4">
                                    <div class="small text-muted">Typical Month (P90)</div>
                                    <div class="fw-bold">${Number(row.p90_historical).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                                </div>
                                <div class="col-4">
                                    <div class="small text-muted">Potential Saving</div>
                                    <div class="fw-bold text-success">${Number(Math.max(row.potential_saving, 0)).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = `
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card grad-4">
                            <div class="small opacity-75">Total Potential Saving This Month</div>
                            <div class="fs-3 fw-bold">${Number(Math.max(data.totalPotentialSaving, 0)).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                        </div>
                    </div>
                </div>
                <div class="row g-3">${cards}</div>
            `;
        }

        async function refreshCostOptimisation() {
            try {
                const res = await fetch('{{ route('cost_optimisation.data') }}');
                const data = await res.json();
                renderPaceSection(data);
                renderAnomalySection(data);
                document.getElementById('costOptUpdatedAt').textContent = 'Updated ' + new Date(data.generated_at).toLocaleTimeString();
            } catch (e) {
                console.warn('Could not refresh Cost Optimisation data', e);
            }
        }

        document.getElementById('refreshCostOptBtn').addEventListener('click', refreshCostOptimisation);
        setInterval(refreshCostOptimisation, 30000);
    </script>
</x-backend.layouts.master>
