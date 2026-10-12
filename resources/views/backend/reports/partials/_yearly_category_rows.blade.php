{{--
    Category-wise Income / Expense breakdown rows for the Yearly report, shared by
    the Excel export and the print/PDF section. Outputs <tr> rows only, so the
    caller wraps them in its own <table>. Pass in:
      $monthlyData (array) from HandCashController::buildYearlyMonthlyData()
      $colspan     (int)   full width of the surrounding table
    Each table is Category x Year, with an "All Years" total column.
--}}
@php
    $headerStyle = 'background-color:#4F46E5;color:#FFFFFF;font-weight:bold;text-align:center;padding:6px;border:1px solid #D1D5DB;';
    $sectionStyle = 'background-color:#1F2937;color:#FFFFFF;font-size:14px;font-weight:bold;text-align:left;padding:8px;border:1px solid #D1D5DB;';
    $cellStyle = 'padding:4px 8px;border:1px solid #D1D5DB;text-align:right;';
    $labelStyle = 'padding:4px 8px;border:1px solid #D1D5DB;text-align:left;';
    $totalStyle = 'background-color:#E0E7FF;font-weight:bold;';
    $types = [
        'income' => ['title' => 'Income', 'color' => '#16A34A', 'heading' => '#DCFCE7'],
        'expense' => ['title' => 'Expense', 'color' => '#DC2626', 'heading' => '#FEF3C7'],
    ];

    // pivot([year => ['incomeByCategory' => [...], ...]], 'income') => [category => [year => amount, 'total' => sum]]
    $pivot = function (array $yearBuckets, string $type) {
        $rows = [];
        foreach ($yearBuckets as $year => $bucket) {
            foreach ($bucket[$type . 'ByCategory'] as $name => $amount) {
                $rows[$name][$year] = ($rows[$name][$year] ?? 0) + $amount;
                $rows[$name]['total'] = ($rows[$name]['total'] ?? 0) + $amount;
            }
        }
        uasort($rows, fn($a, $b) => $b['total'] <=> $a['total']);
        return $rows;
    };

    // Whole-range totals: merge every month's per-year buckets into one per year.
    $rangeYears = [];
    foreach ($monthlyData as $data) {
        foreach ($data['years'] as $year => $yearData) {
            $rangeYears[$year]['incomeByCategory'][] = $yearData['incomeByCategory'];
            $rangeYears[$year]['expenseByCategory'][] = $yearData['expenseByCategory'];
        }
    }
    ksort($rangeYears);
    $rangeBuckets = [];
    foreach ($rangeYears as $year => $lists) {
        foreach (['incomeByCategory', 'expenseByCategory'] as $key) {
            $merged = [];
            foreach ($lists[$key] as $list) {
                foreach ($list as $name => $amount) {
                    $merged[$name] = ($merged[$name] ?? 0) + $amount;
                }
            }
            $rangeBuckets[$year][$key] = $merged;
        }
    }

    $sections = [['title' => 'Total Category-wise', 'buckets' => $rangeBuckets]];
    foreach ($monthlyData as $data) {
        $sections[] = ['title' => $data['label'], 'buckets' => $data['years']];
    }
@endphp

@foreach ($sections as $section)
    <tr><td colspan="{{ $colspan }}"></td></tr>
    <tr>
        <th colspan="{{ $colspan }}" style="{{ $sectionStyle }}">{{ $section['title'] }} Income / Expense Breakdown</th>
    </tr>
    @foreach ($types as $type => $cfg)
        @php
            $years = array_keys($section['buckets']);
            $rows = $pivot($section['buckets'], $type);
        @endphp
        <tr>
            <th colspan="{{ $colspan }}" style="background-color:{{ $cfg['heading'] }};font-weight:bold;text-align:left;padding:6px;border:1px solid #D1D5DB;">
                {{ $section['title'] }} &mdash; {{ $cfg['title'] }} by Category
            </th>
        </tr>
        <tr>
            <th style="{{ $headerStyle }}">Category</th>
            @foreach ($years as $year)
                <th style="{{ $headerStyle }}">{{ $year }}</th>
            @endforeach
            <th style="{{ $headerStyle }}">All Years</th>
        </tr>
        @forelse ($rows as $name => $row)
            <tr style="background-color:{{ $loop->even ? '#F8F9FC' : '#FFFFFF' }};">
                <td style="{{ $labelStyle }}">{{ $name }}</td>
                @foreach ($years as $year)
                    <td style="{{ $cellStyle }}">{{ isset($row[$year]) ? number_format($row[$year], 2) : '-' }}</td>
                @endforeach
                <td style="{{ $cellStyle }}color:{{ $cfg['color'] }};font-weight:bold;">{{ number_format($row['total'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ count($years) + 2 }}" style="{{ $labelStyle }}color:#6B7280;">No records</td></tr>
        @endforelse
        <tr style="{{ $totalStyle }}">
            <td style="{{ $labelStyle }}">Total</td>
            @foreach ($years as $year)
                <td style="{{ $cellStyle }}">{{ number_format(array_sum($section['buckets'][$year][$type . 'ByCategory'] ?? []), 2) }}</td>
            @endforeach
            <td style="{{ $cellStyle }}">{{ number_format(array_sum(array_column($rows, 'total')), 2) }}</td>
        </tr>
    @endforeach
@endforeach
