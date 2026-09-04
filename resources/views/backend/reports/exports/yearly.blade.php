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
            'title' => 'Yearly Report — ' . $year,
            'subtitle' => 'Generated on ' . now()->format('d M Y, h:i A'),
            'colspan' => 7,
        ])
        <tr>
            <th style="{{ $headerStyle }}">Month</th>
            <th style="{{ $headerStyle }}">Income</th>
            <th style="{{ $headerStyle }}">Expense</th>
            <th style="{{ $headerStyle }}">Net</th>
            <th style="{{ $headerStyle }}">Needs (Actual)</th>
            <th style="{{ $headerStyle }}">Wants (Actual)</th>
            <th style="{{ $headerStyle }}">Savings (Actual)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($monthlyData as $data)
            <tr style="background-color:{{ $loop->even ? $zebra : '#FFFFFF' }};">
                <td style="{{ $labelStyle }}">{{ $data['month'] }}</td>
                <td style="{{ $cellStyle }}color:#16A34A;">{{ number_format($data['income'], 2) }}</td>
                <td style="{{ $cellStyle }}color:#DC2626;">{{ number_format($data['expense'], 2) }}</td>
                <td style="{{ $cellStyle }}{{ $data['net'] >= 0 ? 'color:#16A34A;' : 'color:#DC2626;' }}">{{ number_format($data['net'], 2) }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($data['needs'], 2) }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($data['wants'], 2) }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($data['savings'], 2) }}</td>
            </tr>
        @endforeach
        <tr style="{{ $totalStyle }}">
            <td style="{{ $labelStyle }}">Total</td>
            <td style="{{ $cellStyle }}">{{ number_format(array_sum(array_column($monthlyData, 'income')), 2) }}</td>
            <td style="{{ $cellStyle }}">{{ number_format(array_sum(array_column($monthlyData, 'expense')), 2) }}</td>
            <td style="{{ $cellStyle }}">{{ number_format(array_sum(array_column($monthlyData, 'net')), 2) }}</td>
            <td style="{{ $cellStyle }}">{{ number_format(array_sum(array_column($monthlyData, 'needs')), 2) }}</td>
            <td style="{{ $cellStyle }}">{{ number_format(array_sum(array_column($monthlyData, 'wants')), 2) }}</td>
            <td style="{{ $cellStyle }}">{{ number_format(array_sum(array_column($monthlyData, 'savings')), 2) }}</td>
        </tr>
    </tbody>
</table>
