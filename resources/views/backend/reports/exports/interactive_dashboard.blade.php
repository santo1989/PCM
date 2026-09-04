@php
    $headerStyle = 'background-color:#4F46E5;color:#FFFFFF;font-weight:bold;text-align:center;padding:8px;border:1px solid #D1D5DB;';
    $sectionStyle = 'background-color:#EEF2FF;color:#333333;font-weight:bold;padding:6px 8px;border:1px solid #D1D5DB;';
    $cellStyle = 'padding:6px 8px;border:1px solid #D1D5DB;text-align:right;';
    $labelStyle = 'padding:6px 8px;border:1px solid #D1D5DB;text-align:left;';
    $summaryLabelStyle = 'background-color:#F8F9FC;font-weight:bold;padding:6px 8px;border:1px solid #D1D5DB;text-align:left;';
    $net = $totalIncome - $totalExpense;
    $zebra = '#F8F9FC';
@endphp
<table style="border-collapse:collapse;font-family:Calibri, Arial, sans-serif;">
    <thead>
        @include('backend.reports.exports.partials._title_band', [
            'title' => 'Interactive Dashboard — ' . $year . ($month ? ' / ' . \Carbon\Carbon::create()->month($month)->format('F') : ' (Full Year)'),
            'subtitle' => 'Generated on ' . now()->format('d M Y, h:i A'),
            'colspan' => 5,
        ])
        <tr>
            <td colspan="4" style="{{ $summaryLabelStyle }}">Total Income</td>
            <td style="{{ $cellStyle }}color:#16A34A;font-weight:bold;">{{ number_format($totalIncome, 2) }}</td>
        </tr>
        <tr>
            <td colspan="4" style="{{ $summaryLabelStyle }}">Total Expense</td>
            <td style="{{ $cellStyle }}color:#DC2626;font-weight:bold;">{{ number_format($totalExpense, 2) }}</td>
        </tr>
        <tr style="background-color:#E0E7FF;">
            <td colspan="4" style="{{ $summaryLabelStyle }}">Net</td>
            <td style="{{ $cellStyle }}font-weight:bold;{{ $net >= 0 ? 'color:#16A34A;' : 'color:#DC2626;' }}">{{ number_format($net, 2) }}</td>
        </tr>
    </thead>
    <tbody>
        <tr><td colspan="5" style="border:none;padding:4px;"></td></tr>
        <tr>
            <th colspan="5" style="{{ $sectionStyle }}text-align:center;">Expense Category Breakdown</th>
        </tr>
        <tr>
            <th colspan="4" style="{{ $headerStyle }}">Category</th>
            <th style="{{ $headerStyle }}">Amount</th>
        </tr>
        @forelse ($categoryBreakdown as $row)
            <tr style="background-color:{{ $loop->even ? $zebra : '#FFFFFF' }};">
                <td colspan="4" style="{{ $labelStyle }}">{{ $row->category_name }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($row->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="{{ $labelStyle }}color:#6B7280;">No expenses recorded for this period.</td></tr>
        @endforelse

        <tr><td colspan="5" style="border:none;padding:4px;"></td></tr>
        <tr>
            <th colspan="5" style="{{ $sectionStyle }}text-align:center;">Recent Transactions</th>
        </tr>
        <tr>
            <th style="{{ $headerStyle }}">Date</th>
            <th style="{{ $headerStyle }}">Name</th>
            <th style="{{ $headerStyle }}">Category</th>
            <th style="{{ $headerStyle }}">Type</th>
            <th style="{{ $headerStyle }}">Amount</th>
        </tr>
        @forelse ($recentTransactions as $row)
            <tr style="background-color:{{ $loop->even ? $zebra : '#FFFFFF' }};">
                <td style="{{ $labelStyle }}">{{ \Carbon\Carbon::parse($row->date)->format('d M Y') }}</td>
                <td style="{{ $labelStyle }}">{{ $row->name }}</td>
                <td style="{{ $labelStyle }}">{{ optional($row->category)->name ?? 'Unknown' }}</td>
                <td style="{{ $labelStyle }}">
                    <span style="color:{{ $row->types === 'INCOME' ? '#16A34A' : '#DC2626' }};font-weight:bold;">{{ $row->types }}</span>
                </td>
                <td style="{{ $cellStyle }}">{{ number_format($row->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="{{ $labelStyle }}color:#6B7280;">No transactions recorded.</td></tr>
        @endforelse
    </tbody>
</table>
