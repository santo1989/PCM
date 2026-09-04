<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-up"></i> Income vs Expense Trend</div>
            <div class="card-body">
                <canvas id="trendChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart"></i> Category Breakdown</div>
            <div class="card-body">
                <canvas id="categoryChart" height="220"></canvas>
                <div class="small text-muted text-center mt-2">Click a slice to filter Recent Activity below</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart"></i> Spending by Rule (Needs / Wants / Savings)</div>
            <div class="card-body">
                <canvas id="ruleChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar-week"></i> Day-of-Week Spending</div>
            <div class="card-body">
                <canvas id="dayOfWeekChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-piggy-bank"></i> Budget Utilization</div>
            <div class="card-body">
                <canvas id="budgetChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-wallet2"></i> Investment Allocation</div>
            <div class="card-body">
                <canvas id="investmentAllocationChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-up-arrow"></i> Investment Growth Projection (5 / 10 / 20 yr, vs. 4% Rule Target)</div>
            <div class="card-body">
                <canvas id="investmentProjectionChart" height="220"></canvas>
                <div class="small text-muted mt-2">
                    Assumes a flat 7% annual return compounded monthly on current holdings plus recent average monthly contribution —
                    this app has no market-valuation data source, so this is a projection, not a tracked return.
                </div>
            </div>
        </div>
    </div>
</div>
