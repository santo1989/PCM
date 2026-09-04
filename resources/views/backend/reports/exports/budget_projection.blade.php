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
            'title' => 'Budget Projection — ' . now()->format('F Y'),
            'subtitle' => 'Generated on ' . now()->format('d M Y, h:i A'),
            'colspan' => 4,
        ])
        <tr>
            <th style="{{ $headerStyle }}">Category</th>
            <th style="{{ $headerStyle }}">Avg Expense ({{ $avgRangeLabel }})</th>
            <th style="{{ $headerStyle }}">Last Month Expense</th>
            <th style="{{ $headerStyle }}">This Month Projected</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr style="background-color:{{ $loop->even ? $zebra : '#FFFFFF' }};">
                <td style="{{ $labelStyle }}">{{ $row['category'] }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($row['avg'], 2) }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($row['last'], 2) }}</td>
                <td style="{{ $cellStyle }}color:#4F46E5;font-weight:bold;">{{ number_format($row['projected'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="{{ $labelStyle }}color:#6B7280;">No projection data available.</td></tr>
        @endforelse
        <tr style="{{ $totalStyle }}">
            <td style="{{ $labelStyle }}">Total</td>
            <td style="{{ $cellStyle }}">{{ number_format($rows->sum('avg'), 2) }}</td>
            <td style="{{ $cellStyle }}">{{ number_format($rows->sum('last'), 2) }}</td>
            <td style="{{ $cellStyle }}">{{ number_format($rows->sum('projected'), 2) }}</td>
        </tr>
    </tbody>
</table>
