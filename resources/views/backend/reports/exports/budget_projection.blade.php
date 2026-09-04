<table>
    <thead>
        <tr>
            <th colspan="4">Budget Projection - {{ now()->format('F Y') }}</th>
        </tr>
        <tr>
            <th>Category</th>
            <th>Avg Expense (This Year)</th>
            <th>Last Month Expense</th>
            <th>This Month Projected</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['category'] }}</td>
                <td>{{ $row['avg'] }}</td>
                <td>{{ $row['last'] }}</td>
                <td>{{ $row['projected'] }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Total</th>
            <th>{{ $rows->sum('avg') }}</th>
            <th>{{ $rows->sum('last') }}</th>
            <th>{{ $rows->sum('projected') }}</th>
        </tr>
    </tbody>
</table>
