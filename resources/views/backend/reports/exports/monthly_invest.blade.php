<table>
    <thead>
        <tr>
            <th colspan="6">Monthly Investment Report: {{ $startDate }} to {{ $endDate }}</th>
        </tr>
        <tr>
            <th>Date</th>
            <th>Salary Amount</th>
            <th>Investment</th>
            <th>Needs (50%)</th>
            <th>Wants (10%)</th>
            <th>Future (10%)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($incomes as $income)
            <tr>
                <td>{{ $income['date'] }}</td>
                <td>{{ $income['amount'] }}</td>
                <td>{{ $income['investment'] }}</td>
                <td>{{ $income['needs'] }}</td>
                <td>{{ $income['wants'] }}</td>
                <td>{{ $income['future'] }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Total</th>
            <th>{{ $incomes->sum('amount') }}</th>
            <th>{{ $incomes->sum('investment') }}</th>
            <th>{{ $incomes->sum('needs') }}</th>
            <th>{{ $incomes->sum('wants') }}</th>
            <th>{{ $incomes->sum('future') }}</th>
        </tr>
    </tbody>
</table>
