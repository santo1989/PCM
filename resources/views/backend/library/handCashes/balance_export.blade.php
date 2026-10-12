@php
    $headerStyle = 'background-color:#4F46E5;color:#FFFFFF;font-weight:bold;text-align:center;padding:6px;border:1px solid #D1D5DB;';
    $sectionStyle = 'background-color:#1F2937;color:#FFFFFF;font-size:13px;font-weight:bold;text-align:left;padding:8px;border:1px solid #D1D5DB;';
    $cellStyle = 'padding:4px 8px;border:1px solid #D1D5DB;text-align:right;';
    $labelStyle = 'padding:4px 8px;border:1px solid #D1D5DB;text-align:left;';
    $totalStyle = 'background-color:#E0E7FF;font-weight:bold;';
    $money = fn($v) => number_format((float) $v, 2);
    $period = $balanceDateStart && $balanceDateEnd ? "{$balanceDateStart} to {$balanceDateEnd}" : 'All Time';
@endphp
<table style="border-collapse:collapse;font-family:Calibri, Arial, sans-serif;">
    <thead>
        @include('backend.reports.exports.partials._title_band', [
            'title' => 'Hand Cash Balance Summary',
            'subtitle' => 'Balance period: ' . $period . ' — Generated on ' . now()->format('d M Y, h:i A'),
            'colspan' => 4,
        ])
    </thead>
    <tbody>
        {{-- Mobile Cash --}}
        <tr><th colspan="4" style="{{ $sectionStyle }}">Mobile Cash</th></tr>
        <tr><th colspan="3" style="{{ $headerStyle }}">Account</th><th style="{{ $headerStyle }}">Balance</th></tr>
        @foreach ($mobile_cash as $row)
            <tr><td colspan="3" style="{{ $labelStyle }}">{{ str_replace('_', ' ', $row->rules) }}</td><td style="{{ $cellStyle }}">{{ $money($row->Balance) }}</td></tr>
        @endforeach
        <tr style="{{ $totalStyle }}"><td colspan="3" style="{{ $labelStyle }}">Total</td><td style="{{ $cellStyle }}">{{ $money($handCashes_Mobile_balence) }}</td></tr>
        <tr><td colspan="4"></td></tr>

        {{-- Credit Card --}}
        <tr><th colspan="4" style="{{ $sectionStyle }}">Credit Card</th></tr>
        <tr><th colspan="3" style="{{ $headerStyle }}">Item</th><th style="{{ $headerStyle }}">Amount</th></tr>
        <tr><td colspan="3" style="{{ $labelStyle }}">Credit Pay</td><td style="{{ $cellStyle }}">{{ $money($CreditCard_Credit) }}</td></tr>
        <tr><td colspan="3" style="{{ $labelStyle }}">Borrow</td><td style="{{ $cellStyle }}">{{ $money($CreditCard_withdraw) }}</td></tr>
        <tr style="{{ $totalStyle }}"><td colspan="3" style="{{ $labelStyle }}">Card Balance</td><td style="{{ $cellStyle }}">{{ $money($CreditCard_balance) }}</td></tr>
        <tr><td colspan="4"></td></tr>

        {{-- Loan To Other --}}
        <tr><th colspan="4" style="{{ $sectionStyle }}">Loan To Other (Receivable by Me)</th></tr>
        <tr style="{{ $totalStyle }}"><td colspan="3" style="{{ $labelStyle }}">Balance</td><td style="{{ $cellStyle }}">{{ $money($handCashes_loan_balence) }}</td></tr>
        <tr><td colspan="4"></td></tr>

        {{-- My Loan --}}
        <tr><th colspan="4" style="{{ $sectionStyle }}">My Loan (Need to Pay by Me)</th></tr>
        <tr>
            <th style="{{ $headerStyle }}">Name</th>
            <th style="{{ $headerStyle }}">Pay</th>
            <th style="{{ $headerStyle }}">Borrow</th>
            <th style="{{ $headerStyle }}">Balance</th>
        </tr>
        <tr>
            <td style="{{ $labelStyle }}">My Loan</td>
            <td style="{{ $cellStyle }}">{{ $money($MyLoan_pay) }}</td>
            <td style="{{ $cellStyle }}">{{ $money($MyLoan_borrow) }}</td>
            <td style="{{ $cellStyle }}">{{ $money($MyLoan_balance) }}</td>
        </tr>
        <tr>
            <td style="{{ $labelStyle }}">DPS Loan</td>
            <td style="{{ $cellStyle }}">{{ $money($DPSLoan_pay) }}</td>
            <td style="{{ $cellStyle }}">{{ $money($DPSLoan_borrow) }}</td>
            <td style="{{ $cellStyle }}">{{ $money($DPSLoan_balance) }}</td>
        </tr>
        <tr><td colspan="4"></td></tr>

        {{-- Bank / DSE / DPS / FD --}}
        <tr><th colspan="4" style="{{ $sectionStyle }}">Bank Savings / Investment in Dhaka Stock Exchange / DPS / FD</th></tr>
        <tr><th colspan="3" style="{{ $headerStyle }}">Account</th><th style="{{ $headerStyle }}">Balance</th></tr>
        @foreach ($bank_cash as $row)
            <tr><td colspan="3" style="{{ $labelStyle }}">{{ str_replace('_', ' ', $row->rules) }}</td><td style="{{ $cellStyle }}">{{ $money($row->Balance) }}</td></tr>
        @endforeach
        <tr style="{{ $totalStyle }}"><td colspan="3" style="{{ $labelStyle }}">Total</td><td style="{{ $cellStyle }}">{{ $money($handCashes_Bank_balence) }}</td></tr>
        <tr><td colspan="4"></td></tr>

        {{-- Cash in Hand --}}
        <tr><th colspan="4" style="{{ $sectionStyle }}">Cash in Hand</th></tr>
        <tr><th colspan="3" style="{{ $headerStyle }}">Cash</th><th style="{{ $headerStyle }}">Balance</th></tr>
        <tr><td colspan="3" style="{{ $labelStyle }}">Locker Cash Balance</td><td style="{{ $cellStyle }}">{{ $money($handCashes_Cash_balence) }}</td></tr>
        <tr><td colspan="3" style="{{ $labelStyle }}">Peti Cash Balance</td><td style="{{ $cellStyle }}">{{ $money($handCashes_Peti_balence) }}</td></tr>
        <tr><td colspan="4"></td></tr>

        {{-- Total Balance --}}
        <tr><th colspan="4" style="{{ $sectionStyle }}">Total Balance</th></tr>
        <tr><th colspan="3" style="{{ $headerStyle }}">Type</th><th style="{{ $headerStyle }}">Balance</th></tr>
        <tr style="{{ $totalStyle }}"><td colspan="3" style="{{ $labelStyle }}">Net Cash</td><td style="{{ $cellStyle }}">{{ $money($hands) }}</td></tr>
        <tr style="{{ $totalStyle }}"><td colspan="3" style="{{ $labelStyle }}">Gross Cash</td><td style="{{ $cellStyle }}">{{ $money($total) }}</td></tr>
    </tbody>
</table>
