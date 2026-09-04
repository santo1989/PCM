<x-backend.layouts.master>
    <x-slot name="pageTitle">Interactive Dashboard</x-slot>

    <div class="container mt-4">

        @include('backend.reports.partials.report_nav')

        <h3>Interactive Financial Dashboard</h3>

        <div class="card mb-4 no-print">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label for="startDateInput" class="form-label small mb-1">Start Date</label>
                        <input type="date" id="startDateInput" class="form-control"
                            min="{{ $minDataDate }}" max="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <label for="endDateInput" class="form-label small mb-1">End Date</label>
                        <input type="date" id="endDateInput" class="form-control"
                            min="{{ $minDataDate }}" max="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-6 d-flex flex-wrap align-items-end gap-2 justify-content-md-end">
                        <button id="refreshBtn" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                        <a id="exportExcelBtn" class="btn btn-outline-success" href="{{ route('interactive.dashboard.export_excel') }}">
                            <i class="bi bi-file-earmark-excel"></i> Excel
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="budgetAlerts"></div>

        <div class="row mb-3" id="insightCards">
            <!-- Insight/analysis cards will be injected here -->
        </div>

        <div class="row" id="summaryCards">
            <!-- Summary cards will be injected here -->
        </div>

        <div class="row mt-4">
            <div class="col-md-8">
                <canvas id="trendChart" height="200"></canvas>
            </div>
            <div class="col-md-4">
                <canvas id="categoryChart" height="200"></canvas>
                <div class="small text-muted text-center">Click a slice to filter Recent Transactions below</div>
                <h5 class="mt-3">Cash Balances</h5>
                <table class="table table-sm table-striped" id="cashBalancesTable"></table>
            </div>
        </div>

        <!-- What-if scenario builder: pure client-side arithmetic on already-fetched category data -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header"><i class="bi bi-question-diamond"></i> What-If Scenario Builder</div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small">Category</label>
                                <select id="whatIfCategory" class="form-select"></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Reduce spending by: <span id="whatIfReduceLabel">20%</span></label>
                                <input type="range" class="form-range" id="whatIfReduce" min="0" max="100" value="20" step="5">
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="small text-muted">Estimated Annual Saving</div>
                                <div class="fs-5 fw-bold text-success" id="whatIfResult">—</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-4">
                <h5>Savings vs Withdrawals</h5>
                <canvas id="savingsChart" height="180"></canvas>
            </div>
            <div class="col-md-4">
                <h5>Top Expense Categories</h5>
                <canvas id="topCategoriesChart" height="180"></canvas>
            </div>
            <div class="col-md-4">
                <h5>Recent Transactions</h5>
                <div id="recentTransactions" style="max-height:300px; overflow:auto;"></div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <h5>Running Balance (HandCash)</h5>
                <div style="height:220px; overflow:auto;">
                    <table class="table table-sm table-striped" id="runningBalanceTable"></table>
                </div>
            </div>
        </div>

        <div id="aiInsightsPanel" class="card mt-4 border-info">
            <div class="card-header bg-info text-white d-flex justify-content-between">
                <span><i class="bi bi-robot"></i> AI Insights</span>
                <span class="badge bg-light text-dark" id="aiTimestamp"></span>
            </div>
            <div class="card-body" id="aiContent">
                <div class="text-center py-3">
                    <div class="spinner-border text-info" role="status"></div>
                    <p>Loading AI analysis...</p>
                </div>
            </div>
        </div>

    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
        const startDateInput = document.getElementById('startDateInput');
        const endDateInput = document.getElementById('endDateInput');
        const refreshBtn = document.getElementById('refreshBtn');

        // Default: the current calendar month
        (function setDefaultRange() {
            const pad = (n) => String(n).padStart(2, '0');
            const now = new Date();
            const firstOfMonth = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            const lastOfMonth = `${lastDay.getFullYear()}-${pad(lastDay.getMonth() + 1)}-${pad(lastDay.getDate())}`;
            startDateInput.value = firstOfMonth;
            endDateInput.value = lastOfMonth;
        })();

        let trendChart = null;
        let categoryChart = null;

        function rangeQuery() {
            return `start_date=${startDateInput.value}&end_date=${endDateInput.value}`;
        }

        async function fetchSummary() {
            const res = await fetch(`/interactive-dashboard/data/summary?${rangeQuery()}`);
            return res.json();
        }

        async function fetchTrend() {
            const res = await fetch(`/interactive-dashboard/data/monthly-trend`);
            return res.json();
        }

        async function fetchCategory() {
            const res = await fetch(`/interactive-dashboard/data/category-breakdown?${rangeQuery()}`);
            return res.json();
        }

        async function fetchSavingsLoans() {
            const res = await fetch(`/interactive-dashboard/data/savings-loans`);
            return res.json();
        }

        async function fetchTopCategories() {
            const res = await fetch(`/interactive-dashboard/data/top-categories?${rangeQuery()}`);
            return res.json();
        }

        async function fetchRunningBalance() {
            const res = await fetch(`/interactive-dashboard/data/running-balance`);
            return res.json();
        }

        async function fetchRecentTransactions() {
            const res = await fetch(`/interactive-dashboard/data/recent-transactions?limit=30`);
            return res.json();
        }

        async function fetchBudgetAlerts() {
            const res = await fetch(`/interactive-dashboard/data/budget-alerts?${rangeQuery()}`);
            return res.json();
        }

        async function fetchAiInsights() {
            const res = await fetch(`/interactive-dashboard/data/ai-insights?${rangeQuery()}`);
            return res.json();
        }

        let allRecentTransactions = [];
        let categoryChartData = [];

        function renderSummaryCards(data) {
            const container = document.getElementById('summaryCards');
            container.innerHTML = '';
            const cards = [{
                    title: 'Total Income (Selected Range)',
                    value: data.totalIncome
                },
                {
                    title: 'Total Expense (Selected Range)',
                    value: data.totalExpense
                },
                {
                    title: 'Net (Selected Range)',
                    value: data.net
                }
            ];
            for (const c of cards) {
                const col = document.createElement('div');
                col.className = 'col-md-2';
                col.innerHTML =
                    `<div class="card p-2"><div class="card-body"><h6>${c.title}</h6><h5>${Number(c.value).toLocaleString()}</h5></div></div>`;
                container.appendChild(col);
            }

            // cash balances table
            const table = document.getElementById('cashBalancesTable');
            table.innerHTML = '<tr><th>Account</th><th>Balance</th></tr>';
            for (const [k, v] of Object.entries(data.cashBalances || {})) {
                const row = document.createElement('tr');
                row.innerHTML = `<td>${k}</td><td>${Number(v).toLocaleString()}</td>`;
                table.appendChild(row);
            }
        }

        function renderTrendChart(payload) {
            const ctx = document.getElementById('trendChart').getContext('2d');
            if (trendChart) trendChart.destroy();
            trendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: payload.months,
                    datasets: [{
                            label: 'Income',
                            data: payload.income,
                            borderColor: 'green',
                            fill: false
                        },
                        {
                            label: 'Expense',
                            data: payload.expense,
                            borderColor: 'red',
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    }
                }
            });
        }

        function renderCategoryChart(items) {
            categoryChartData = items;
            const ctx = document.getElementById('categoryChart').getContext('2d');
            if (categoryChart) categoryChart.destroy();
            const labels = items.map(i => i.category);
            const data = items.map(i => i.total);
            categoryChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: labels.map((_, i) => ['#4e79a7', '#f28e2b', '#e15759', '#76b7b2',
                            '#59a14f', '#edc949', '#af7aa1', '#ff9da7', '#9c755f', '#bab0ac'
                        ][i % 10])
                    }]
                },
                options: {
                    responsive: true,
                    onClick: (evt, elements) => {
                        if (!elements.length) return;
                        const category = labels[elements[0].index];
                        renderRecentTransactions(allRecentTransactions, category);
                    }
                }
            });

            populateWhatIfCategories(items);
        }

        function populateWhatIfCategories(items) {
            const select = document.getElementById('whatIfCategory');
            const previousValue = select.value;
            select.innerHTML = items.map(i => `<option value="${i.category}" data-total="${i.total}">${i.category}</option>`).join('');
            if (previousValue && items.some(i => i.category === previousValue)) {
                select.value = previousValue;
            }
            recalcWhatIf();
        }

        function recalcWhatIf() {
            const select = document.getElementById('whatIfCategory');
            const reduceSlider = document.getElementById('whatIfReduce');
            const selected = select.options[select.selectedIndex];
            const resultEl = document.getElementById('whatIfResult');

            document.getElementById('whatIfReduceLabel').textContent = reduceSlider.value + '%';

            if (!selected) {
                resultEl.textContent = '—';
                return;
            }

            // categoryChart data is spend for that category over the selected range;
            // treat it as a monthly-equivalent rate scaled to a year for a simple estimate.
            const yearlyTotal = parseFloat(selected.dataset.total) || 0;
            const reducePct = parseFloat(reduceSlider.value) / 100;
            const annualSaving = yearlyTotal * reducePct;

            resultEl.textContent = annualSaving.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        document.getElementById('whatIfCategory').addEventListener('change', recalcWhatIf);
        document.getElementById('whatIfReduce').addEventListener('input', recalcWhatIf);

        function renderBudgetAlerts(rows) {
            const container = document.getElementById('budgetAlerts');
            if (!rows || !rows.length) {
                container.innerHTML = '';
                return;
            }

            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Budget exceeded this month:</strong>
                    ${rows.map(r => `${r.category_name} (${r.utilization_percent.toFixed(0)}%)`).join(', ')}
                </div>
            `;
        }

        function updateExportLink() {
            // The export endpoint takes year(+optional month), not a range — derive them
            // from the end of the selected range so it still reflects the visible period.
            const btn = document.getElementById('exportExcelBtn');
            const end = endDateInput.value ? new Date(endDateInput.value) : new Date();
            const url = `{{ route('interactive.dashboard.export_excel') }}?year=${end.getFullYear()}&month=${end.getMonth() + 1}`;
            btn.href = url;
        }

        function renderInsightCards(summary, trend) {
            const container = document.getElementById('insightCards');
            container.innerHTML = '';

            const savingsRate = summary.totalIncome > 0 ? ((summary.net / summary.totalIncome) * 100) : 0;
            const expenseRatio = summary.totalIncome > 0 ? ((summary.totalExpense / summary.totalIncome) * 100) : 0;

            let momIncomeChange = null, momExpenseChange = null;
            if (trend && trend.income && trend.income.length >= 2) {
                const lastIncome = trend.income[trend.income.length - 1];
                const prevIncome = trend.income[trend.income.length - 2];
                const lastExpense = trend.expense[trend.expense.length - 1];
                const prevExpense = trend.expense[trend.expense.length - 2];
                momIncomeChange = prevIncome > 0 ? (((lastIncome - prevIncome) / prevIncome) * 100) : null;
                momExpenseChange = prevExpense > 0 ? (((lastExpense - prevExpense) / prevExpense) * 100) : null;
            }

            const fmtPct = (v) => v === null || isNaN(v) ? 'N/A' : (v >= 0 ? '+' : '') + v.toFixed(1) + '%';

            const cards = [
                { title: 'Savings Rate (Range)', value: savingsRate.toFixed(1) + '%' },
                { title: 'Expense Ratio (Range)', value: expenseRatio.toFixed(1) + '%' },
                { title: 'Income vs Last Month', value: fmtPct(momIncomeChange) },
                { title: 'Expense vs Last Month', value: fmtPct(momExpenseChange) },
            ];

            for (const c of cards) {
                const col = document.createElement('div');
                col.className = 'col-md-3 col-6 mb-2';
                col.innerHTML =
                    `<div class="card text-center h-100"><div class="card-body p-2"><div class="text-muted small">${c.title}</div><div class="fw-bold">${c.value}</div></div></div>`;
                container.appendChild(col);
            }
        }

        function renderAiInsights(data) {
            const container = document.getElementById('aiContent');
            let html = (data.summary || []).map(i => `<div class="alert alert-${i.type} py-2 mb-2">${i.message}</div>`).join('');

            if (data.anomalies && data.anomalies.length > 0) {
                html += `<h6>⚠️ Anomalies</h6><ul>`;
                data.anomalies.forEach(a => {
                    const badge = a.severity === 'critical' ? 'danger' : (a.severity === 'high' ? 'warning' : 'info');
                    html += `<li>${a.category}: ${Number(a.current).toFixed(2)} <span class="badge bg-${badge}">${a.severity}</span></li>`;
                });
                html += `</ul>`;
            }

            if (data.recommendations && data.recommendations.length > 0) {
                html += `<h6>💡 Recommendations</h6><ul>`;
                data.recommendations.forEach(r => {
                    html += `<li>${r.message}</li>`;
                });
                html += `</ul>`;
            }

            container.innerHTML = html || '<p class="text-muted small">No notable insights for this period yet.</p>';
            document.getElementById('aiTimestamp').textContent = new Date().toLocaleTimeString();
        }

        async function refreshAll() {
            updateExportLink();
            const [summary, trend, categories, savings, topCategories, running, recent, alerts, ai] = await Promise.all([
                fetchSummary(), fetchTrend(), fetchCategory(), fetchSavingsLoans(), fetchTopCategories(),
                fetchRunningBalance(), fetchRecentTransactions(), fetchBudgetAlerts(), fetchAiInsights()
            ]);
            renderSummaryCards(summary);
            renderInsightCards(summary, trend);
            renderTrendChart(trend);
            renderCategoryChart(categories);
            renderSavingsChart(savings);
            renderTopCategoriesChart(topCategories);
            renderRunningBalance(running);
            allRecentTransactions = recent;
            renderRecentTransactions(recent);
            renderBudgetAlerts(alerts);
            renderAiInsights(ai);
        }

        function renderSavingsChart(data) {
            const ctx = document.getElementById('savingsChart').getContext('2d');
            const labels = ['Savings', 'Withdrawals'];
            const values = [data.savings_total || 0, data.withdrawals_total || 0];
            if (window._savingsChart) window._savingsChart.destroy();
            window._savingsChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#4caf50', '#f44336']
                    }]
                },
                options: {
                    responsive: true
                }
            });
        }

        function renderTopCategoriesChart(items) {
            const ctx = document.getElementById('topCategoriesChart').getContext('2d');
            const labels = items.map(i => i.category);
            const data = items.map(i => i.total);
            if (window._topCategoriesChart) window._topCategoriesChart.destroy();
            window._topCategoriesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Amount',
                        data,
                        backgroundColor: '#4e79a7'
                    }]
                },
                options: {
                    responsive: true
                }
            });
        }

        function renderRunningBalance(items) {
            const table = document.getElementById('runningBalanceTable');
            table.innerHTML =
                '<tr><th>Date</th><th>Name</th><th>Rules</th><th>Type</th><th>Amount</th><th>Balance</th></tr>';
            for (const r of items) {
                const row = document.createElement('tr');
                row.innerHTML =
                    `<td>${r.date}</td><td>${r.name}</td><td>${r.rules}</td><td>${r.types}</td><td>${Number(r.amount).toLocaleString()}</td><td>${Number(r.balance).toLocaleString()}</td>`;
                table.appendChild(row);
            }
        }

        function renderRecentTransactions(items, filterCategory) {
            const div = document.getElementById('recentTransactions');
            div.innerHTML = '';

            const filtered = filterCategory ? items.filter(r => r.category === filterCategory) : items;

            if (filterCategory) {
                const banner = document.createElement('div');
                banner.className = 'small mb-2';
                banner.innerHTML = `Filtered to <strong>${filterCategory}</strong> — <a href="#" id="clearFilterLink">clear</a>`;
                div.appendChild(banner);
                document.getElementById('clearFilterLink').addEventListener('click', (e) => {
                    e.preventDefault();
                    renderRecentTransactions(allRecentTransactions);
                });
            }

            if (!filtered.length) {
                div.innerHTML += '<div class="text-muted small">No transactions in this category recently.</div>';
                return;
            }

            for (const r of filtered) {
                const el = document.createElement('div');
                el.className = 'p-2 border-bottom';
                el.innerHTML =
                    `<div><strong>${r.name}</strong> <small class="text-muted">(${r.category || r.types})</small></div><div>${r.date} - ${Number(r.amount).toLocaleString()}</div>`;
                div.appendChild(el);
            }
        }

        // initial load
        refreshAll();
        refreshBtn.addEventListener('click', refreshAll);
        startDateInput.addEventListener('change', refreshAll);
        endDateInput.addEventListener('change', refreshAll);

        // near-real-time: re-poll every 30s (matches the app-wide AJAX-polling
        // approach chosen over WebSockets, since no Pusher/Echo/websocket
        // infrastructure is set up in this environment)
        setInterval(refreshAll, 30000);
    </script>
</x-backend.layouts.master>
