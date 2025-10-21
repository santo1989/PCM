<x-backend.layouts.master>
    <x-slot name="pageTitle">Interactive Dashboard</x-slot>

    <div class="container mt-4">
        <h3>Interactive Financial Dashboard</h3>

        <div class="row mb-3">
            <div class="col-md-3">
                <label for="yearSelect">Year</label>
                <select id="yearSelect" class="form-control"></select>
            </div>
            <div class="col-md-3">
                <label for="monthSelect">Month (optional)</label>
                <select id="monthSelect" class="form-control">
                    <option value="">All Year</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button id="refreshBtn" class="btn btn-primary">Refresh</button>
            </div>
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
                <h5 class="mt-3">Cash Balances</h5>
                <table class="table table-sm table-striped" id="cashBalancesTable"></table>
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

    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const yearSelect = document.getElementById('yearSelect');
        const monthSelect = document.getElementById('monthSelect');
        const refreshBtn = document.getElementById('refreshBtn');

        // populate year select (last 5 years)
        const currentYear = new Date().getFullYear();
        for (let y = currentYear; y >= currentYear - 5; y--) {
            const opt = document.createElement('option');
            opt.value = y;
            opt.text = y;
            yearSelect.appendChild(opt);
        }
        yearSelect.value = currentYear;

        // populate months
        const months = ["", "01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "11", "12"];
        const monthNames = ["All Year", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        for (let i = 0; i < months.length; i++) {
            const opt = document.createElement('option');
            opt.value = months[i];
            opt.text = monthNames[i];
            monthSelect.appendChild(opt);
        }

        let trendChart = null;
        let categoryChart = null;

        async function fetchSummary() {
            const year = yearSelect.value;
            const month = monthSelect.value;
            const url = `/interactive-dashboard/data/summary?year=${year}${month ? `&month=${month}` : ''}`;
            const res = await fetch(url);
            return res.json();
        }

        async function fetchTrend() {
            const res = await fetch(`/interactive-dashboard/data/monthly-trend`);
            return res.json();
        }

        async function fetchCategory() {
            const year = yearSelect.value;
            const month = monthSelect.value;
            const url = `/interactive-dashboard/data/category-breakdown?year=${year}${month ? `&month=${month}` : ''}`;
            const res = await fetch(url);
            return res.json();
        }

        async function fetchSavingsLoans() {
            const res = await fetch(`/interactive-dashboard/data/savings-loans`);
            return res.json();
        }

        async function fetchTopCategories() {
            const year = yearSelect.value;
            const res = await fetch(`/interactive-dashboard/data/top-categories?year=${year}`);
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

        function renderSummaryCards(data) {
            const container = document.getElementById('summaryCards');
            container.innerHTML = '';
            const cards = [{
                    title: 'Year Total Income',
                    value: data.totalIncome
                },
                {
                    title: 'Year Total Expense',
                    value: data.totalExpense
                },
                {
                    title: 'Year Net',
                    value: data.net
                },
                {
                    title: 'Month Income',
                    value: data.monthIncome
                },
                {
                    title: 'Month Expense',
                    value: data.monthExpense
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
                    responsive: true
                }
            });
        }

        async function refreshAll() {
            const [summary, trend, categories, savings, topCategories, running, recent] = await Promise.all([
                fetchSummary(), fetchTrend(), fetchCategory(), fetchSavingsLoans(), fetchTopCategories(),
                fetchRunningBalance(), fetchRecentTransactions()
            ]);
            renderSummaryCards(summary);
            renderTrendChart(trend);
            renderCategoryChart(categories);
            renderSavingsChart(savings);
            renderTopCategoriesChart(topCategories);
            renderRunningBalance(running);
            renderRecentTransactions(recent);
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

        function renderRecentTransactions(items) {
            const div = document.getElementById('recentTransactions');
            div.innerHTML = '';
            for (const r of items) {
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
    </script>
</x-backend.layouts.master>
