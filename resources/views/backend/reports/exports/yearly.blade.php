<table>
    <thead>
        <tr>
            <th colspan="7">Yearly Report - {{ $year }}</th>
        </tr>
        <tr>
            <th>Month</th>
            <th>Income</th>
            <th>Expense</th>
            <th>Net</th>
            <th>Needs (Actual)</th>
            <th>Wants (Actual)</th>
            <th>Savings (Actual)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($monthlyData as $data)
            <tr>
                <td>{{ $data['month'] }}</td>
                <td>{{ $data['income'] }}</td>
                <td>{{ $data['expense'] }}</td>
                <td>{{ $data['net'] }}</td>
                <td>{{ $data['needs'] }}</td>
                <td>{{ $data['wants'] }}</td>
                <td>{{ $data['savings'] }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Total</th>
            <th>{{ array_sum(array_column($monthlyData, 'income')) }}</th>
            <th>{{ array_sum(array_column($monthlyData, 'expense')) }}</th>
            <th>{{ array_sum(array_column($monthlyData, 'net')) }}</th>
            <th>{{ array_sum(array_column($monthlyData, 'needs')) }}</th>
            <th>{{ array_sum(array_column($monthlyData, 'wants')) }}</th>
            <th>{{ array_sum(array_column($monthlyData, 'savings')) }}</th>
        </tr>
    </tbody>
</table>
