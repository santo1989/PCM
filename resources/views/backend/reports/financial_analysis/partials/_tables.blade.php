<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-table"></i> Transactions</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm" id="datatablesSimple">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Rule</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $row)
                                <tr>
                                    <td>{{ $row['date'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['category'] }}</td>
                                    <td>
                                        <span class="badge {{ $row['types'] === 'INCOME' ? 'bg-success' : 'bg-danger' }}">{{ $row['types'] }}</span>
                                    </td>
                                    <td>{{ $row['rules'] }}</td>
                                    <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> Recent Activity</div>
            <div class="card-body p-0" id="recentActivity" style="max-height: 420px; overflow-y: auto;">
                <div class="text-muted small p-2">Loading…</div>
            </div>
        </div>
    </div>
</div>
