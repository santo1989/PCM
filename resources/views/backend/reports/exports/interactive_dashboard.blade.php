<table>
    <thead>
        <tr>
            <th colspan="2">Interactive Dashboard - {{ $year }}{{ $month ? ' / ' . $month : ' (Full Year)' }}</th>
        </tr>
        <tr>
            <th>Total Income</th>
            <td>{{ $totalIncome }}</td>
        </tr>
        <tr>
            <th>Total Expense</th>
            <td>{{ $totalExpense }}</td>
        </tr>
        <tr>
            <th>Net</th>
            <td>{{ $totalIncome - $totalExpense }}</td>
        </tr>
    </thead>
    <tbody>
        <tr><td colspan="2"></td></tr>
        <tr><th colspan="2">Expense Category Breakdown</th></tr>
        <tr><th>Category</th><th>Amount</th></tr>
        @foreach ($categoryBreakdown as $row)
            <tr>
                <td>{{ $row->category_name }}</td>
                <td>{{ $row->total }}</td>
            </tr>
        @endforeach

        <tr><td colspan="2"></td></tr>
        <tr><th colspan="2">Recent Transactions</th></tr>
        <tr><th>Date</th><th>Name</th><th>Category</th><th>Type</th><th>Amount</th></tr>
        @foreach ($recentTransactions as $row)
            <tr>
                <td>{{ $row->date }}</td>
                <td>{{ $row->name }}</td>
                <td>{{ optional($row->category)->name }}</td>
                <td>{{ $row->types }}</td>
                <td>{{ $row->amount }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
