@php
    $headerStyle = 'background-color:#4F46E5;color:#FFFFFF;font-weight:bold;text-align:center;padding:8px;border:1px solid #D1D5DB;';
    $cellStyle = 'padding:6px 8px;border:1px solid #D1D5DB;text-align:right;';
    $labelStyle = 'padding:6px 8px;border:1px solid #D1D5DB;text-align:left;';
    $totalStyle = 'background-color:#E0E7FF;font-weight:bold;';
    $zebra = '#F8F9FC';
    $totalSave = $search_cashes->where('types', 'SAVE')->sum('amount');
    $totalWithdraw = $search_cashes->where('types', 'WIDROWS')->sum('amount');
@endphp
<table style="border-collapse:collapse;font-family:Calibri, Arial, sans-serif;">
    <thead>
        @include('backend.reports.exports.partials._title_band', [
            'title' => 'HandCash Report',
            'subtitle' => ($rangeStart ?? 'All Time') . ' to ' . ($rangeEnd ?? 'All Time') . ' — Generated on ' . \Carbon\Carbon::now()->format('d M Y, h:i A'),
            'colspan' => 5,
        ])
        <tr>
            <th style="{{ $headerStyle }}">Date</th>
            <th style="{{ $headerStyle }}">Name</th>
            <th style="{{ $headerStyle }}">Rules</th>
            <th style="{{ $headerStyle }}">Type</th>
            <th style="{{ $headerStyle }}">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($search_cashes as $cash)
            <tr style="background-color:{{ $loop->even ? $zebra : '#FFFFFF' }};">
                <td style="{{ $labelStyle }}">{{ \Carbon\Carbon::parse($cash->date)->format('d-M-Y') }}</td>
                <td style="{{ $labelStyle }}">{{ $cash->name }}</td>
                <td style="{{ $labelStyle }}">{{ str_replace('_', ' ', $cash->rules) }}</td>
                <td style="{{ $labelStyle }}">
                    <span style="color:{{ $cash->types === 'SAVE' ? '#16A34A' : '#DC2626' }};font-weight:bold;">{{ $cash->types }}</span>
                </td>
                <td style="{{ $cellStyle }}">{{ number_format($cash->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="{{ $labelStyle }}color:#6B7280;">No transactions found.</td></tr>
        @endforelse
        <tr style="{{ $totalStyle }}">
            <td colspan="4" style="{{ $labelStyle }}text-align:right;">Total Saved</td>
            <td style="{{ $cellStyle }}color:#16A34A;">{{ number_format($totalSave, 2) }}</td>
        </tr>
        <tr style="{{ $totalStyle }}">
            <td colspan="4" style="{{ $labelStyle }}text-align:right;">Total Withdrawn</td>
            <td style="{{ $cellStyle }}color:#DC2626;">{{ number_format($totalWithdraw, 2) }}</td>
        </tr>
        <tr style="{{ $totalStyle }}">
            <td colspan="4" style="{{ $labelStyle }}text-align:right;">Net Balance</td>
            <td style="{{ $cellStyle }}{{ ($totalSave - $totalWithdraw) >= 0 ? 'color:#16A34A;' : 'color:#DC2626;' }}">{{ number_format($totalSave - $totalWithdraw, 2) }}</td>
        </tr>
    </tbody>
</table>
