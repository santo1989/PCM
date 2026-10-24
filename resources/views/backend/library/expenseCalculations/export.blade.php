@php
    $headerStyle = 'background-color:#4F46E5;color:#FFFFFF;font-weight:bold;text-align:center;padding:8px;border:1px solid #D1D5DB;';
    $cellStyle = 'padding:6px 8px;border:1px solid #D1D5DB;text-align:right;';
    $labelStyle = 'padding:6px 8px;border:1px solid #D1D5DB;text-align:left;';
    $totalStyle = 'background-color:#E0E7FF;font-weight:bold;';
    $zebra = '#F8F9FC';
    $total = $search_cashes->sum('amount');
@endphp
<table id="cashesTable" style="border-collapse:collapse;font-family:Calibri, Arial, sans-serif;">
    <thead>
        @include('backend.reports.exports.partials._title_band', [
            'title' => 'Expense Calculation Report',
            'subtitle' => 'Generated on ' . \Carbon\Carbon::now()->format('d M Y, h:i A'),
            'colspan' => 7,
        ])
        <tr>
            <th style="{{ $headerStyle }}">Sl</th>
            <th style="{{ $headerStyle }}">Date</th>
            <th style="{{ $headerStyle }}">Name</th>
            <th style="{{ $headerStyle }}">Category</th>
            <th style="{{ $headerStyle }}">Types</th>
            <th style="{{ $headerStyle }}">Rules of Cost</th>
            <th style="{{ $headerStyle }}">Cash Amount BDT</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($search_cashes as $cash)
            <tr style="background-color:{{ $loop->even ? $zebra : '#FFFFFF' }};">
                <td style="{{ $labelStyle }}">{{ $loop->iteration }}</td>
                <td style="{{ $labelStyle }}">{{ \Carbon\Carbon::parse($cash->date)->format('d-M-Y') }}</td>
                <td style="{{ $labelStyle }}">{{ $cash->name }}</td>
                <td style="{{ $labelStyle }}">{{ optional($cash->category)->name ?? 'Unknown' }}</td>
                <td style="{{ $labelStyle }}">
                    <span style="color:{{ $cash->types === 'INCOME' ? '#16A34A' : '#DC2626' }};font-weight:bold;">{{ $cash->types }}</span>
                </td>
                <td style="{{ $labelStyle }}">{{ $cash->rules }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($cash->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="{{ $labelStyle }}color:#6B7280;">No transactions found.</td></tr>
        @endforelse
        <tr style="{{ $totalStyle }}">
            <td colspan="6" style="{{ $labelStyle }}text-align:right;">Total</td>
            <td style="{{ $cellStyle }}">{{ number_format($total, 2) }}</td>
        </tr>
    </tbody>
</table>
