@php
    $headerStyle = 'background-color:#4F46E5;color:#FFFFFF;font-weight:bold;text-align:center;padding:8px;border:1px solid #D1D5DB;';
    $sectionStyle = 'background-color:#EEF2FF;color:#333333;font-weight:bold;padding:6px 8px;border:1px solid #D1D5DB;';
    $cellStyle = 'padding:6px 8px;border:1px solid #D1D5DB;text-align:right;';
    $labelStyle = 'padding:6px 8px;border:1px solid #D1D5DB;text-align:left;';
    $summaryLabelStyle = 'background-color:#F8F9FC;font-weight:bold;padding:6px 8px;border:1px solid #D1D5DB;text-align:left;';
    $totalStyle = 'background-color:#E0E7FF;font-weight:bold;';
    $zebra = '#F8F9FC';
@endphp
<table style="border-collapse:collapse;font-family:Calibri, Arial, sans-serif;">
    <thead>
        @include('backend.reports.exports.partials._title_band', [
            'title' => 'Financial Analysis Dashboard',
            'subtitle' => $label . ' (' . $startDate . ' to ' . $endDate . ') — Generated on ' . now()->format('d M Y, h:i A'),
            'colspan' => 2,
        ])
    </thead>
    <tbody>
        <tr>
            <th colspan="2" style="{{ $sectionStyle }}text-align:center;">KPI Summary</th>
        </tr>
        <tr style="background-color:{{ $zebra }};">
            <td style="{{ $summaryLabelStyle }}background-color:transparent;">Cash Balance</td>
            <td style="{{ $cellStyle }}">{{ number_format($kpis['balance'], 2) }}</td>
        </tr>
        <tr>
            <td style="{{ $summaryLabelStyle }}background-color:transparent;">Projected Month-End Balance</td>
            <td style="{{ $cellStyle }}">{{ number_format($kpis['projected_balance'], 2) }}</td>
        </tr>
        <tr style="background-color:{{ $zebra }};">
            <td style="{{ $summaryLabelStyle }}background-color:transparent;">Period Income</td>
            <td style="{{ $cellStyle }}color:#16A34A;">{{ number_format($kpis['period_income'], 2) }}</td>
        </tr>
        <tr>
            <td style="{{ $summaryLabelStyle }}background-color:transparent;">Period Expense</td>
            <td style="{{ $cellStyle }}color:#DC2626;">{{ number_format($kpis['period_expense'], 2) }}</td>
        </tr>
        <tr style="background-color:#E0E7FF;">
            <td style="{{ $summaryLabelStyle }}background-color:transparent;">Period Net</td>
            <td style="{{ $cellStyle }}font-weight:bold;{{ $kpis['period_net'] >= 0 ? 'color:#16A34A;' : 'color:#DC2626;' }}">{{ number_format($kpis['period_net'], 2) }}</td>
        </tr>
        <tr style="background-color:{{ $zebra }};">
            <td style="{{ $summaryLabelStyle }}background-color:transparent;">Savings Rate (%)</td>
            <td style="{{ $cellStyle }}">{{ number_format($kpis['savings_rate'], 2) }}</td>
        </tr>
        <tr>
            <td style="{{ $summaryLabelStyle }}background-color:transparent;">Burn Rate (per day)</td>
            <td style="{{ $cellStyle }}">{{ number_format($kpis['burn_rate'], 2) }}</td>
        </tr>
        <tr style="background-color:{{ $zebra }};">
            <td style="{{ $summaryLabelStyle }}background-color:transparent;">Cash Runway (days)</td>
            <td style="{{ $cellStyle }}">{{ $kpis['runway_days'] ?? 'Unlimited' }}</td>
        </tr>
        <tr>
            <td style="{{ $summaryLabelStyle }}background-color:transparent;">Total Investments</td>
            <td style="{{ $cellStyle }}">{{ number_format($kpis['total_investments'], 2) }}</td>
        </tr>

        <tr><td colspan="2" style="border:none;padding:8px;"></td></tr>
        <tr>
            <th colspan="2" style="{{ $sectionStyle }}text-align:center;">Category Breakdown</th>
        </tr>
        <tr>
            <th style="{{ $headerStyle }}">Category</th>
            <th style="{{ $headerStyle }}">Amount (% of total)</th>
        </tr>
        @forelse ($categoryBreakdown as $row)
            <tr style="background-color:{{ $loop->even ? $zebra : '#FFFFFF' }};">
                <td style="{{ $labelStyle }}">{{ $row['category_name'] }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($row['amount'], 2) }} ({{ number_format($row['percent'], 1) }}%)</td>
            </tr>
        @empty
            <tr><td colspan="2" style="{{ $labelStyle }}color:#6B7280;">No expenses recorded for this period.</td></tr>
        @endforelse

        <tr><td colspan="2" style="border:none;padding:8px;"></td></tr>
        <tr>
            <th colspan="2" style="{{ $sectionStyle }}text-align:center;">Budget vs Actual (Overall Utilization: {{ number_format($budget['overall_utilization_percent'], 1) }}%)</th>
        </tr>
        <tr>
            <th style="{{ $headerStyle }}">Category</th>
            <th style="{{ $headerStyle }}">Projected</th>
        </tr>
        @forelse ($budget['rows'] as $row)
            <tr style="background-color:{{ $loop->even ? $zebra : '#FFFFFF' }};">
                <td style="{{ $labelStyle }}">{{ $row['category_name'] }}</td>
                <td style="{{ $cellStyle }}{{ $row['utilization_percent'] > 100 ? 'color:#DC2626;font-weight:bold;' : 'color:#16A34A;' }}">
                    {{ number_format($row['projected'], 2) }} → {{ number_format($row['actual'], 2) }} ({{ number_format($row['utilization_percent'], 0) }}%)
                </td>
            </tr>
        @empty
            <tr><td colspan="2" style="{{ $labelStyle }}color:#6B7280;">No budget projections set for this period.</td></tr>
        @endforelse

        <tr><td colspan="2" style="border:none;padding:8px;"></td></tr>
        <tr>
            <th colspan="6" style="{{ $sectionStyle }}text-align:center;">Transactions</th>
        </tr>
    </tbody>
</table>

<table style="border-collapse:collapse;font-family:Calibri, Arial, sans-serif;">
    <thead>
        <tr>
            <th style="{{ $headerStyle }}">Date</th>
            <th style="{{ $headerStyle }}">Name</th>
            <th style="{{ $headerStyle }}">Category</th>
            <th style="{{ $headerStyle }}">Type</th>
            <th style="{{ $headerStyle }}">Rule</th>
            <th style="{{ $headerStyle }}">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($transactions as $row)
            <tr style="background-color:{{ $loop->even ? $zebra : '#FFFFFF' }};">
                <td style="{{ $labelStyle }}">{{ $row['date'] }}</td>
                <td style="{{ $labelStyle }}">{{ $row['name'] }}</td>
                <td style="{{ $labelStyle }}">{{ $row['category'] }}</td>
                <td style="{{ $labelStyle }}">
                    <span style="color:{{ $row['types'] === 'INCOME' ? '#16A34A' : '#DC2626' }};font-weight:bold;">{{ $row['types'] }}</span>
                </td>
                <td style="{{ $labelStyle }}">{{ $row['rules'] }}</td>
                <td style="{{ $cellStyle }}">{{ number_format($row['amount'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="{{ $labelStyle }}color:#6B7280;">No transactions in this period.</td></tr>
        @endforelse
    </tbody>
</table>
