<table>
    <thead>
        <tr>
            <th colspan="2">Monthly Report: {{ $startDate }} to {{ $endDate }}</th>
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
        <tr><th colspan="2">Income by Category</th></tr>
        <tr><th>Category</th><th>Amount</th></tr>
        @foreach ($incomeByCategory as $row)
            <tr>
                <td>{{ $row->category_name }}</td>
                <td>{{ $row->total }}</td>
            </tr>
        @endforeach

        <tr><td colspan="2"></td></tr>
        <tr><th colspan="2">Expense by Category</th></tr>
        <tr><th>Category</th><th>Amount</th></tr>
        @foreach ($expenseByCategory as $row)
            <tr>
                <td>{{ $row->category_name }}</td>
                <td>{{ $row->total }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
