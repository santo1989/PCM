<x-backend.layouts.master>
    <x-slot name="pageTitle">Financial Analysis Dashboard</x-slot>

    <div class="container-fluid mt-4">

        @include('backend.reports.partials.report_nav')

        @include('backend.reports.financial_analysis.partials._header')

        @include('backend.reports.financial_analysis.partials._kpi_cards')

        @include('backend.reports.financial_analysis.partials._charts')

        @include('backend.reports.financial_analysis.partials._insights')

        @include('backend.reports.financial_analysis.partials._tables')

    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
        const dataUrl = '{{ route('financial_analysis.data') }}';
        const exportUrl = '{{ route('financial_analysis.export') }}';

        const periodSelect = document.getElementById('periodSelect');
        const categorySelect = document.getElementById('categorySelect');
        const startDateInput = document.getElementById('startDateInput');
        const endDateInput = document.getElementById('endDateInput');
        const refreshBtn = document.getElementById('refreshBtn');
        const autoRefreshSelect = document.getElementById('autoRefreshSelect');
        const lastUpdatedEl = document.getElementById('lastUpdated');
        const exportLink = document.getElementById('exportLink');

        let refreshTimer = null;
        let latestPayload = null;

        const charts = {
            trend: null,
            category: null,
            rule: null,
            dayOfWeek: null,
            budget: null,
            investmentAllocation: null,
            investmentProjection: null,
        };

        const palette = ['#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f', '#edc949', '#af7aa1', '#ff9da7', '#9c755f', '#bab0ac'];

        function fmt(value, decimals = 2) {
            if (value === null || value === undefined || isNaN(value)) return '—';
            return Number(value).toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        }

        function buildParams() {
            const params = new URLSearchParams();
            // The Start/End Date inputs are always visible; filling both in implies "custom"
            // regardless of whatever preset is still showing in the Period dropdown.
            const hasCustomRange = !!(startDateInput.value && endDateInput.value);
            const period = hasCustomRange ? 'custom' : periodSelect.value;
            params.set('period', period);

            if (hasCustomRange) {
                params.set('start_date', startDateInput.value);
                params.set('end_date', endDateInput.value);
            }

            if (categorySelect.value) params.set('category_id', categorySelect.value);

            return params;
        }

        function updateExportLink() {
            exportLink.href = `${exportUrl}?${buildParams().toString()}`;
        }

        async function fetchDashboard() {
            const res = await fetch(`${dataUrl}?${buildParams().toString()}`);
            return res.json();
        }

        function destroy(chart) {
            if (chart) chart.destroy();
        }

        function renderKpis(data) {
            const kpis = data.kpis;
            const health = data.health_score;

            document.getElementById('kpi-balance').textContent = fmt(kpis.balance);
            document.getElementById('kpi-projected-balance').textContent = fmt(kpis.projected_balance);
            document.getElementById('kpi-period-income').textContent = fmt(kpis.period_income);
            document.getElementById('kpi-period-expense').textContent = fmt(kpis.period_expense);
            document.getElementById('kpi-savings-rate').textContent = fmt(kpis.savings_rate, 1) + '%';
            document.getElementById('kpi-burn-rate').textContent = fmt(kpis.burn_rate);
            document.getElementById('kpi-runway').textContent = kpis.runway_days === null ? 'Unlimited' : Math.round(kpis.runway_days) + ' days';
            document.getElementById('kpi-health-score').textContent = `${health.score} / 100`;
            document.getElementById('kpi-health-label').textContent = health.label;

            const netEl = document.getElementById('kpi-period-net');
            const net = kpis.period_net;
            netEl.textContent = fmt(net);
            netEl.classList.toggle('text-success', net >= 0);
            netEl.classList.toggle('text-danger', net < 0);
        }

        function renderTrendChart(trend) {
            const ctx = document.getElementById('trendChart').getContext('2d');
            destroy(charts.trend);

            const labels = [...trend.labels, ...trend.forecast.labels];
            const income = [...trend.income, ...trend.forecast.labels.map(() => null)];
            const expense = [...trend.expense, ...trend.forecast.labels.map(() => null)];
            const forecastExpense = [...trend.labels.map(() => null), ...trend.forecast.expense];
            // bridge the gap so the dashed forecast line connects to the last actual point
            if (trend.expense.length) forecastExpense[trend.labels.length - 1] = trend.expense[trend.expense.length - 1];

            charts.trend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'Income', data: income, borderColor: '#59a14f', fill: false, tension: 0.2 },
                        { label: 'Expense', data: expense, borderColor: '#e15759', fill: false, tension: 0.2 },
                        { label: 'Expense Forecast', data: forecastExpense, borderColor: '#e15759', borderDash: [6, 4], fill: false, pointRadius: 2 },
                    ],
                },
                options: { responsive: true, interaction: { mode: 'index', intersect: false } },
            });
        }

        function renderCategoryChart(rows) {
            const ctx = document.getElementById('categoryChart').getContext('2d');
            destroy(charts.category);

            const labels = rows.map(r => r.category_name);
            const values = rows.map(r => r.amount);

            charts.category = new Chart(ctx, {
                type: 'pie',
                data: { labels, datasets: [{ data: values, backgroundColor: labels.map((_, i) => palette[i % palette.length]) }] },
                options: {
                    responsive: true,
                    onClick: (evt, elements) => {
                        if (!elements.length) return;
                        renderRecentTransactions(latestPayload.transactions, labels[elements[0].index]);
                    },
                },
            });
        }

        function renderRuleChart(rows) {
            const ctx = document.getElementById('ruleChart').getContext('2d');
            destroy(charts.rule);

            charts.rule = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: rows.map(r => r.rule),
                    datasets: [{ data: rows.map(r => r.total), backgroundColor: rows.map((_, i) => palette[i % palette.length]) }],
                },
                options: { responsive: true },
            });
        }

        function renderDayOfWeekChart(rows) {
            const ctx = document.getElementById('dayOfWeekChart').getContext('2d');
            destroy(charts.dayOfWeek);

            charts.dayOfWeek = new Chart(ctx, {
                type: 'bar',
                data: { labels: rows.map(r => r.day), datasets: [{ label: 'Total Spent', data: rows.map(r => r.total), backgroundColor: '#4e79a7' }] },
                options: { responsive: true },
            });
        }

        function renderBudgetChart(budget) {
            const ctx = document.getElementById('budgetChart').getContext('2d');
            destroy(charts.budget);

            const rows = budget.rows;

            charts.budget = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: rows.map(r => r.category_name),
                    datasets: [
                        { label: 'Projected', data: rows.map(r => r.projected), backgroundColor: '#bab0ac' },
                        {
                            label: 'Actual',
                            data: rows.map(r => r.actual),
                            backgroundColor: rows.map(r => (r.utilization_percent > 100 ? '#e15759' : '#59a14f')),
                        },
                    ],
                },
                options: { responsive: true },
            });
        }

        function renderInvestmentAllocationChart(investment) {
            const ctx = document.getElementById('investmentAllocationChart').getContext('2d');
            destroy(charts.investmentAllocation);

            const rows = investment.asset_allocation;

            charts.investmentAllocation = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: rows.map(r => r.rule),
                    datasets: [{ data: rows.map(r => r.balance), backgroundColor: rows.map((_, i) => palette[i % palette.length]) }],
                },
                options: { responsive: true },
            });
        }

        function renderInvestmentProjectionChart(investment) {
            const ctx = document.getElementById('investmentProjectionChart').getContext('2d');
            destroy(charts.investmentProjection);

            const projections = investment.compound_growth_projection.projections;
            const target = investment.four_percent_rule.target_amount;

            charts.investmentProjection = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: projections.map(p => `${p.years}yr`),
                    datasets: [
                        { type: 'bar', label: 'Projected Value', data: projections.map(p => p.projected_value), backgroundColor: '#4facfe' },
                        { type: 'line', label: '4% Rule Target', data: projections.map(() => target), borderColor: '#f5576c', borderDash: [6, 4], pointRadius: 0, fill: false },
                    ],
                },
                options: { responsive: true },
            });
        }

        function renderInsights(payload) {
            const ai = payload.ai_insights;
            const health = payload.health_score;

            // Health score breakdown bars
            const componentsEl = document.getElementById('healthComponents');
            componentsEl.innerHTML = Object.entries(health.components).map(([key, c]) => {
                const label = key.replace(/_/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase());
                const barClass = c.score >= 70 ? 'bg-success' : (c.score >= 40 ? 'bg-warning' : 'bg-danger');
                return `
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small">
                            <span>${label} <span class="text-muted">(${c.weight}%)</span></span>
                            <span>${c.score}</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar ${barClass}" style="width:${c.score}%"></div>
                        </div>
                    </div>`;
            }).join('');

            // Natural-language summary
            const summaryEl = document.getElementById('insightSummary');
            summaryEl.innerHTML = ai.summary.length
                ? ai.summary.map(i => `<div class="alert alert-${i.type} py-2 mb-2">${i.message}</div>`).join('')
                : '<div class="text-muted small">No notable insights for this period yet.</div>';

            // Anomalies
            const anomaliesEl = document.getElementById('anomalyList');
            anomaliesEl.innerHTML = ai.anomalies.length
                ? ai.anomalies.map(a => `
                    <div class="p-2 border-bottom">
                        <div class="d-flex justify-content-between">
                            <strong>${a.category}</strong>
                            <span class="badge bg-${a.severity === 'critical' ? 'danger' : (a.severity === 'high' ? 'warning' : 'info')}">${a.severity}</span>
                        </div>
                        <div class="small text-muted">${a.suggestion}</div>
                    </div>`).join('')
                : '<div class="text-muted small p-2">No anomalies detected.</div>';

            // Recommendations
            const recEl = document.getElementById('recommendationList');
            recEl.innerHTML = ai.recommendations.length
                ? ai.recommendations.map(r => `
                    <div class="p-2 border-bottom">
                        <div class="d-flex justify-content-between">
                            <strong>${r.title}</strong>
                            <span class="badge bg-${r.priority === 'critical' ? 'danger' : (r.priority === 'high' ? 'warning' : 'secondary')}">${r.priority}</span>
                        </div>
                        <div class="small text-muted">${r.message}</div>
                    </div>`).join('')
                : '<div class="text-muted small p-2">No recommendations right now.</div>';
        }

        let allTransactions = [];

        function renderRecentTransactions(items, filterCategory) {
            const div = document.getElementById('recentActivity');
            div.innerHTML = '';

            const filtered = filterCategory ? items.filter(r => r.category === filterCategory) : items;

            if (filterCategory) {
                const banner = document.createElement('div');
                banner.className = 'small mb-2';
                banner.innerHTML = `Filtered to <strong>${filterCategory}</strong> — <a href="#" id="clearActivityFilter">clear</a>`;
                div.appendChild(banner);
                document.getElementById('clearActivityFilter').addEventListener('click', (e) => {
                    e.preventDefault();
                    renderRecentTransactions(allTransactions);
                });
            }

            if (!filtered.length) {
                div.innerHTML += '<div class="text-muted small">No transactions in this category for the selected period.</div>';
                return;
            }

            for (const r of filtered.slice(0, 30)) {
                const el = document.createElement('div');
                el.className = 'p-2 border-bottom';
                el.innerHTML = `<div><strong>${r.name}</strong> <small class="text-muted">(${r.category})</small></div><div class="small">${r.date} — ${fmt(r.amount)}</div>`;
                div.appendChild(el);
            }
        }

        function render(payload) {
            latestPayload = payload;
            allTransactions = payload.transactions;

            renderKpis(payload);
            renderTrendChart(payload.trend);
            renderCategoryChart(payload.category_breakdown);
            renderRuleChart(payload.spending_by_rule);
            renderDayOfWeekChart(payload.day_of_week);
            renderBudgetChart(payload.budget);
            renderInvestmentAllocationChart(payload.investment);
            renderInvestmentProjectionChart(payload.investment);
            renderInsights(payload);
            renderRecentTransactions(payload.transactions);

            lastUpdatedEl.textContent = new Date().toLocaleTimeString();
        }

        async function refreshAll() {
            updateExportLink();
            try {
                const payload = await fetchDashboard();
                render(payload);
            } catch (e) {
                console.error('Failed to refresh Financial Analysis dashboard', e);
            }
        }

        function scheduleAutoRefresh() {
            if (refreshTimer) clearInterval(refreshTimer);
            const seconds = parseInt(autoRefreshSelect.value, 10);
            if (seconds > 0) {
                refreshTimer = setInterval(refreshAll, seconds * 1000);
            }
        }

        function setPeriodSelectValue(value) {
            if (window.jQuery && jQuery.fn.select2) {
                jQuery(periodSelect).val(value).trigger('change.select2');
            } else {
                periodSelect.value = value;
            }
        }

        periodSelect.addEventListener('change', () => {
            // Picking a preset clears any manually-entered custom range so the preset actually applies.
            if (periodSelect.value !== 'custom') {
                startDateInput.value = '';
                endDateInput.value = '';
            }
            refreshAll();
        });
        categorySelect.addEventListener('change', refreshAll);
        startDateInput.addEventListener('change', () => { setPeriodSelectValue('custom'); refreshAll(); });
        endDateInput.addEventListener('change', () => { setPeriodSelectValue('custom'); refreshAll(); });
        refreshBtn.addEventListener('click', refreshAll);
        autoRefreshSelect.addEventListener('change', scheduleAutoRefresh);

        refreshAll();
        scheduleAutoRefresh();
    </script>
</x-backend.layouts.master>
