<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Income vs Expense Sheet
    </x-slot>

    @php
        $fmt = fn($v) => abs($v) < 0.005 ? '–' : number_format($v, 2);
        $sum = fn($rows) => array_sum(array_map(fn($r) => array_sum($r['months']), $rows));
        $income = array_merge(...array_column($groups, 'income'));
        $expense = array_merge(...array_column($groups, 'expense'));
        $totalIncome = $sum($income);
        $totalExpense = $sum($expense);
        $totalSavings = $sum($rules);
        $line = 0; // next hand-cash account to print in the savings column
        $yearLabel = $year === 'all' ? 'all years' : $year;
    @endphp

    <div class="container-fluid pt-4">
        @include('backend.reports.partials.report_nav')

        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-3 no-print">
            <label for="year" class="fw-semibold">Year</label>
            <select name="year" id="year" class="form-select w-auto" onchange="this.form.submit()">
                <option value="all" {{ $year === 'all' ? 'selected' : '' }}>All years</option>
                @foreach ($years as $y)
                    <option value="{{ $y }}" {{ $y === $year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>
            <span class="small text-muted">Click a column heading for its totals and {{ $year === 'all' ? 'year' : 'month' }}-by-{{ $year === 'all' ? 'year' : 'month' }} breakdown.</span>
        </form>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead>
                            <tr class="table-warning">
                                @foreach ([['sheet-income', 'Income'], ['sheet-expense', 'Expense'], ['sheet-savings', 'Savings (hand cash)'], ['sheet-difference', 'Difference']] as [$modal, $head])
                                    <th class="w-25">
                                        <button type="button" class="btn btn-link p-0 fw-bold text-decoration-none" data-bs-toggle="modal" data-bs-target="#{{ $modal }}">
                                            {{ $head }} <i class="bi bi-box-arrow-up-right small"></i>
                                        </button>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groups as $gi => $g)
                                @php($n = max(count($g['income']), count($g['expense']), 1))
                                @for ($i = 0; $i < $n; $i++)
                                    <tr>
                                        @foreach ([$g['income'][$i] ?? null, $g['expense'][$i] ?? null, $rules[$line++] ?? null] as $cell)
                                            <td>
                                                @if ($cell)
                                                    @php($v = array_sum($cell['months']))
                                                    <div class="d-flex justify-content-between gap-2">
                                                        <span>{{ $cell['name'] }}</span>
                                                        <span class="text-nowrap {{ $v < 0 ? 'text-danger' : ($v == 0 ? 'text-muted' : '') }}">{{ $fmt($v) }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                        @if ($i === 0)
                                            @php($d = array_sum($g['months']))
                                            <td rowspan="{{ $n }}" class="text-end fw-bold {{ $d < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($d) }}</td>
                                        @endif
                                    </tr>
                                @endfor
                                @if (!$loop->last)
                                    <tr><td colspan="4" class="p-1 bg-body-secondary"></td></tr>
                                @endif
                            @endforeach
                            @for (; $line < count($rules); $line++)
                                @php($v = array_sum($rules[$line]['months']))
                                <tr><td></td><td></td>
                                    <td><div class="d-flex justify-content-between gap-2"><span>{{ $rules[$line]['name'] }}</span><span class="{{ $v < 0 ? 'text-danger' : '' }}">{{ $fmt($v) }}</span></div></td>
                                    <td></td></tr>
                            @endfor
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold table-light">
                                <td class="text-end">{{ $fmt($totalIncome) }}</td>
                                <td class="text-end">{{ $fmt($totalExpense) }}</td>
                                <td class="text-end {{ $totalSavings < 0 ? 'text-danger' : '' }}">{{ $fmt($totalSavings) }}</td>
                                <td class="text-end {{ $totalIncome - $totalExpense < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($totalIncome - $totalExpense) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="small text-muted mb-0">
                    Difference = income minus expense for each block. Savings = money added minus money taken out of each hand-cash account during {{ $yearLabel }}.
                </p>
            </div>
        </div>
    </div>

    @include('backend.reports.partials._sheet_modal', ['id' => 'sheet-income', 'title' => 'Income', 'rowLabel' => 'category', 'rows' => $income])
    @include('backend.reports.partials._sheet_modal', ['id' => 'sheet-expense', 'title' => 'Expense', 'rowLabel' => 'category', 'rows' => $expense])
    @include('backend.reports.partials._sheet_modal', ['id' => 'sheet-savings', 'title' => 'Savings (hand cash)', 'rowLabel' => 'account', 'rows' => $rules,
        'extra' => 'In = money added (SAVE), Out = money taken out (WIDROWS); the totals and months show In minus Out.'])
    @include('backend.reports.partials._sheet_modal', ['id' => 'sheet-difference', 'title' => 'Difference (income − expense)', 'rowLabel' => 'block', 'rows' => array_map(fn($g) => ['name' => $g['label'], 'months' => $g['months']], $groups)])
</x-backend.layouts.master>
