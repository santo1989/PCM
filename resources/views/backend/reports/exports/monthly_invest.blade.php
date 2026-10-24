@php
    $headerStyle = 'background-color:#4F46E5;color:#FFFFFF;font-weight:bold;text-align:center;padding:8px;border:1px solid #D1D5DB;';
    $cellStyle = 'padding:6px 8px;border:1px solid #D1D5DB;text-align:right;';
    $labelStyle = 'padding:6px 8px;border:1px solid #D1D5DB;text-align:left;';
    $totalStyle = 'background-color:#E0E7FF;font-weight:bold;';
    $zebra = '#F8F9FC';
@endphp
<table style="border-collapse:collapse;font-family:Calibri, Arial, sans-serif;">
    <thead>
        @include('backend.reports.exports.partials._title_band', [
            'title' => 'Monthly Investment Report',
            'subtitle' => $startDate . ' to ' . $endDate . ' — Generated on ' . now()->format('d M Y, h:i A'),
            'colspan' => 6,
        ])
        <tr>
            <th style="{{ $headerStyle }}">Date</th>
            <th style="{{ $headerStyle }}">Salary Amount</th>
            <th style="{{ $headerStyle }}">Investment</th>
            <th style="{{ $headerStyle }}">Needs (50%)</th>
            <th style="{{ $headerStyle }}">Wants (10%)</th>
            <th style="{{ $headerStyle }}">Future (10%)</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($incomes as $income)
            <tr style="background-color:{{ $loop->even ? $zebra : '#FFFFFF' }};">
                <td style="{{ $labelStyle }}">{{ \Carbon\Carbon::parse($income['date'])->format('d M Y') }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($income['amount'], 2) }}</td>
                <td style="{{ $cellStyle }}color:#16A34A;">{{ number_format($income['investment'], 2) }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($income['needs'], 2) }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($income['wants'], 2) }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($income['future'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="{{ $labelStyle }}color:#6B7280;">No income recorded for this period.</td></tr>
        @endforelse
        <tr style="{{ $totalStyle }}">
            <td style="{{ $labelStyle }}">Total</td>
            <td style="{{ $cellStyle }}">{{ number_format($incomes->sum('amount'), 2) }}</td>
            <td style="{{ $cellStyle }}">{{ number_format($incomes->sum('investment'), 2) }}</td>
            <td style="{{ $cellStyle }}">{{ number_format($incomes->sum('needs'), 2) }}</td>
            <td style="{{ $cellStyle }}">{{ number_format($incomes->sum('wants'), 2) }}</td>
            <td style="{{ $cellStyle }}">{{ number_format($incomes->sum('future'), 2) }}</td>
        </tr>
    </tbody>
</table>
