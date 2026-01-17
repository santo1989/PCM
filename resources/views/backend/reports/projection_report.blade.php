{{-- <x-backend.layouts.master>
    <x-slot name="title">
        Projection Report
    </x-slot>

    <h3 class="text-center">Projected Monthly Budget</h3>
    <form action="{{ route('calculate_and_save_budget') }}" method="post" id="projectionForm">
        @csrf
        <div class="row">
            <div class="mb-3 row">
                <label for="reduction_percent" class="col-sm-2 col-form-label">Reduction target (%)</label>
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" max="0.9" class="form-control"
                            name="reduction_percent" id="reduction_percent" value="0.10">
                        <span class="input-group-text">(0.10 = 10%)</span>
                    </div>
                </div>
                <div class="col-sm-6 d-flex gap-2">
                    <button type="button" id="previewBtn" class="btn btn-secondary">Preview</button>
                    <button type="submit" class="btn btn-primary">Calculate &amp; Save Next Month's Budget</button>
                </div>
            </div>
            <input type="hidden" name="date" value="{{ date('Y-m-d') }}">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Avg Expense (This Year)</th>
                        <th>Last Month Expensed</th>
                        <th>This Month Projected Expense</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        @php
                            $thisYearExpenseCategory = collect($thisYearExpense)->firstWhere(
                                'category_id',
                                $category->id,
                            );
                            $lastMonthExpenseCategory = $lastMonthExpense->firstWhere('category_id', $category->id);

                            // Get the projected expense for this category from the database
                            $projectedAmount = $thisMonthProjectedExpenses->get($category->id)->amount ?? 0;
                            $avg = floatval($thisYearExpenseCategory['averageExpense'] ?? 0);
                            $last = floatval($lastMonthExpenseCategory->totalExpense ?? 0);
                        @endphp

                        @if (
                            (!empty($thisYearExpenseCategory) && $thisYearExpenseCategory['averageExpense'] > 0) ||
                                (!empty($lastMonthExpenseCategory) && $lastMonthExpenseCategory->totalExpense > 0))
                            <tr>
                                <td>{{ $category->name }}<input type="hidden" name="category_id[]"
                                        value="{{ $category->id }}"></td>
                                <td>{{ number_format($avg, 2) }}</td>
                                <td>{{ number_format($last, 2) }}</td>
                                <td>
                                    <input type="text" name="projectedExpense[{{ $category->id }}]"
                                        id="projectedExpense{{ $category->id }}"
                                        class="form-control projectedExpenseInput"
                                        value="{{ number_format($projectedAmount, 2) }}" readonly
                                        data-avg="{{ $avg }}" data-last="{{ $last }}"
                                        data-cat="{{ $category->id }}" data-catname="{{ $category->name }}">
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    <tr>
                        <td class="text-right"><strong>Monthly Income:</strong> <strong
                                id="monthlyIncomeOutput">{{ number_format($totalMonthlyIncome, 2) }}</strong></td>
                        <td class="text-right"><strong>Budget Limit (90%):</strong> <strong
                                id="monthlyLimitOutput">{{ number_format($MonthlyactualLimitExpense, 2) }}</strong>
                        </td>
                        <td class="text-right"><strong>Total Last Month:</strong> <strong
                                id="lastMonthExpenseOutput">{{ number_format($totallastMonthExpense, 2) }}</strong>
                        </td>
                        <td class="text-right"><strong>Total Projected:</strong> <strong
                                id="totalProjectedOutput">0.00</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </form>

    <!-- Preview & edit modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">Preview & Edit Projected Expenses</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">You can tweak per-category projected amounts here before saving.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="previewTable">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Base (last / avg)</th>
                                    <th>Projected</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- populated dynamically -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="text-right"><strong>Total</strong></td>
                                    <td><strong id="modalTotal">0.00</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" id="applyModalBtn" class="btn btn-primary">Apply & Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function calculateTotal() {
            var total = 0;
            var projectedExpenseInputs = document.getElementsByClassName('projectedExpenseInput');
            for (var i = 0; i < projectedExpenseInputs.length; i++) {
                var value = parseFloat(projectedExpenseInputs[i].value);
                total += isNaN(value) ? 0 : value;
            }
            document.getElementById('totalProjectedOutput').innerText = total.toFixed(2);
        }

        function previewProjection() {
            var reduction = parseFloat(document.getElementById('reduction_percent').value) || 0.10;
            var monthlyLimit = parseFloat(document.getElementById('monthlyLimitOutput').innerText.replace(/,/g, '')) || 0;

            var inputs = document.getElementsByClassName('projectedExpenseInput');
            var bases = [];
            var baseSum = 0;
            for (var i = 0; i < inputs.length; i++) {
                var avg = parseFloat(inputs[i].dataset.avg) || 0;
                var last = parseFloat(inputs[i].dataset.last) || 0;
                var base = (last > 0) ? last : avg;
                bases.push({
                    el: inputs[i],
                    base: base
                });
                baseSum += base * (1 - reduction);
            }

            if (baseSum <= 0) {
                // fallback proportional allocation using avg values
                var avgSum = 0;
                for (var i = 0; i < inputs.length; i++) {
                    avgSum += parseFloat(inputs[i].dataset.avg) || 0;
                }
                if (avgSum <= 0) {
                    // nothing to project
                    for (var i = 0; i < inputs.length; i++) {
                        inputs[i].value = (0).toFixed(2);
                    }
                } else {
                    for (var i = 0; i < inputs.length; i++) {
                        var share = (parseFloat(inputs[i].dataset.avg) || 0) / avgSum;
                        inputs[i].value = (monthlyLimit * share).toFixed(2);
                    }
                }
            } else {
                var scale = monthlyLimit / baseSum;
                for (var i = 0; i < bases.length; i++) {
                    var projected = Math.max(0, bases[i].base * (1 - reduction)) * scale;
                    bases[i].el.value = projected.toFixed(2);
                }
            }

            // Populate modal table for editing
            var tbody = document.querySelector('#previewTable tbody');
            tbody.innerHTML = '';
            var modalTotal = 0;
            for (var i = 0; i < bases.length; i++) {
                var inp = bases[i].el;
                var cat = inp.dataset.cat;
                var catname = inp.dataset.catname || ('Category ' + cat);
                var baseVal = bases[i].base;
                var projVal = parseFloat(inp.value) || 0;

                var tr = document.createElement('tr');
                var tdName = document.createElement('td');
                tdName.textContent = catname;
                var tdBase = document.createElement('td');
                tdBase.textContent = baseVal.toFixed(2);
                var tdProj = document.createElement('td');
                var modalInput = document.createElement('input');
                modalInput.type = 'number';
                modalInput.step = '0.01';
                modalInput.min = '0';
                modalInput.className = 'form-control modalProjectedInput';
                modalInput.value = projVal.toFixed(2);
                modalInput.dataset.cat = cat;
                tdProj.appendChild(modalInput);

                tr.appendChild(tdName);
                tr.appendChild(tdBase);
                tr.appendChild(tdProj);
                tbody.appendChild(tr);

                modalTotal += projVal;
            }
            document.getElementById('modalTotal').innerText = modalTotal.toFixed(2);

            // show modal (support Bootstrap 4 & 5)
            try {
                if (window.jQuery && typeof jQuery('#previewModal').modal === 'function') {
                    jQuery('#previewModal').modal('show');
                } else if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
                    var m = new bootstrap.Modal(document.getElementById('previewModal'));
                    m.show();
                }
            } catch (e) {
                console.warn('Could not show modal', e);
            }

            calculateTotal();
        }

        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
            document.getElementById('previewBtn').addEventListener('click', function(e) {
                e.preventDefault();
                previewProjection();
            });

            // Apply modal edits back to main inputs
            document.getElementById('applyModalBtn').addEventListener('click', function() {
                var modalInputs = document.getElementsByClassName('modalProjectedInput');
                var modalSum = 0;
                for (var i = 0; i < modalInputs.length; i++) {
                    var m = modalInputs[i];
                    var cat = m.dataset.cat;
                    var val = parseFloat(m.value) || 0;
                    modalSum += val;
                    var mainInp = document.querySelector('input.projectedExpenseInput[data-cat="' + cat +
                        '"]');
                    if (mainInp) mainInp.value = val.toFixed(2);
                }
                document.getElementById('modalTotal').innerText = modalSum.toFixed(2);
                calculateTotal();
                // hide modal
                try {
                    if (window.jQuery && typeof jQuery('#previewModal').modal === 'function') {
                        jQuery('#previewModal').modal('hide');
                    } else if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
                        var m = bootstrap.Modal.getInstance(document.getElementById('previewModal'));
                        if (m) m.hide();
                    }
                } catch (e) {
                    console.warn('Could not hide modal', e);
                }
            });
        });
    </script>
</x-backend.layouts.master> --}}
<x-backend.layouts.master>
    <x-slot name="title">
        Projection Report
    </x-slot>

    <h3 class="text-center">Projected Monthly Budget</h3>
    <form action="{{ route('calculate_and_save_budget') }}" method="post" id="projectionForm">
        @csrf
        <div class="row">
            <div class="mb-3 row">
                <label for="reduction_percent" class="col-sm-2 col-form-label">Reduction target (%)</label>
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" max="0.9" class="form-control"
                            name="reduction_percent" id="reduction_percent" value="0.10">
                        <span class="input-group-text">(0.10 = 10%)</span>
                    </div>
                </div>
                <div class="col-sm-6 d-flex gap-2">
                    <button type="button" id="previewBtn" class="btn btn-secondary">Preview</button>
                    <button type="submit" class="btn btn-primary">Calculate &amp; Save Next Month's Budget</button>
                </div>
            </div>
            <input type="hidden" name="date" value="{{ date('Y-m-d') }}">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Avg Expense (This Year)</th>
                        <th>Last Month Expensed</th>
                        <th>This Month Projected Expense</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        @php
                            $thisYearExpenseCategory = collect($thisYearExpense)->firstWhere(
                                'category_id',
                                $category->id,
                            );
                            $lastMonthExpenseCategory = $lastMonthExpense->firstWhere('category_id', $category->id);

                            // Get the projected expense for this category from the database
                            $projectedAmount = $thisMonthProjectedExpenses->get($category->id)->amount ?? 0;
                            $avg = floatval($thisYearExpenseCategory['averageExpense'] ?? 0);
                            $last = floatval($lastMonthExpenseCategory->totalExpense ?? 0);
                        @endphp

                        @if (
                            (!empty($thisYearExpenseCategory) && $thisYearExpenseCategory['averageExpense'] > 0) ||
                                (!empty($lastMonthExpenseCategory) && $lastMonthExpenseCategory->totalExpense > 0))
                            <tr>
                                <td>{{ $category->name }}<input type="hidden" name="category_id[]"
                                        value="{{ $category->id }}"></td>
                                <td>{{ number_format($avg, 2) }}</td>
                                <td>{{ number_format($last, 2) }}</td>
                                <td>
                                    <input type="text" name="projectedExpense[{{ $category->id }}]"
                                        id="projectedExpense{{ $category->id }}"
                                        class="form-control projectedExpenseInput"
                                        value="{{ number_format($projectedAmount, 2) }}" readonly
                                        data-avg="{{ $avg }}" data-last="{{ $last }}"
                                        data-cat="{{ $category->id }}" data-catname="{{ $category->name }}">
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    <tr>
                        <td class="text-right"><strong>Monthly Income:</strong> <strong
                                id="monthlyIncomeOutput">{{ number_format($totalMonthlyIncome, 2) }}</strong></td>
                        <td class="text-right"><strong>Budget Limit (70%):</strong> <strong
                                id="monthlyLimitOutput">{{ number_format($MonthlyactualLimitExpense, 2) }}</strong>
                        </td>
                        <td class="text-right"><strong>Total Last Month:</strong> <strong
                                id="lastMonthExpenseOutput">{{ number_format($totallastMonthExpense, 2) }}</strong>
                        </td>
                        <td class="text-right"><strong>Total Projected:</strong> <strong
                                id="totalProjectedOutput">0.00</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </form>

    <!-- Preview & edit modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">Preview & Edit Projected Expenses</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">You can tweak per-category projected amounts here before saving.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="previewTable">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Base (last / avg)</th>
                                    <th>Projected</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- populated dynamically -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="text-right"><strong>Total</strong></td>
                                    <td><strong id="modalTotal">0.00</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" id="applyModalBtn" class="btn btn-primary">Apply & Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Multi-Year Monthly Averages Section -->
    <div class="card mt-4">
        <div class="card-header">
            <h4 class="card-title">Multi-Year Monthly Averages</h4>
            <p class="card-subtitle">Historical spending patterns across all years</p>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs" id="multiYearTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="category-tab" data-bs-toggle="tab" data-bs-target="#category-tab-pane" type="button" role="tab">
                        Category-wise Averages
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="overall-tab" data-bs-toggle="tab" data-bs-target="#overall-tab-pane" type="button" role="tab">
                        Overall Averages
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary-tab-pane" type="button" role="tab">
                        Summary Insights
                    </button>
                </li>
            </ul>
            
            <div class="tab-content mt-3" id="multiYearTabContent">
                <!-- Category-wise Tab -->
                <div class="tab-pane fade show active" id="category-tab-pane" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    @for($month = 1; $month <= 12; $month++)
                                        <th class="text-center">{{ \Carbon\Carbon::create()->month($month)->format('M') }}</th>
                                    @endfor
                                    <th class="text-center">Yearly Avg</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($categories as $category)
                                    @php
                                        $categoryAverages = $formattedMultiYearAverages[$category->id] ?? [];
                                        $yearlyTotal = 0;
                                        $monthCount = 0;
                                    @endphp
                                    @if(!empty($categoryAverages))
                                        <tr>
                                            <td><strong>{{ $category->name }}</strong></td>
                                            @for($month = 1; $month <= 12; $month++)
                                                <td class="text-center">
                                                    @if(isset($categoryAverages[$month]))
                                                        @php
                                                            $yearlyTotal += $categoryAverages[$month]['avg_amount'];
                                                            $monthCount++;
                                                        @endphp
                                                        <span class="badge bg-info" 
                                                              data-bs-toggle="tooltip" 
                                                              title="Years: {{ $categoryAverages[$month]['years_count'] }}
Transactions: {{ $categoryAverages[$month]['transaction_count'] }}
Total: {{ number_format($categoryAverages[$month]['total_amount'], 0) }}">
                                                            {{ number_format($categoryAverages[$month]['avg_amount'], 0) }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                            @endfor
                                            <td class="text-center bg-light">
                                                @if($monthCount > 0)
                                                    <strong>{{ number_format($yearlyTotal / $monthCount, 0) }}</strong>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Overall Averages Tab -->
                <div class="tab-pane fade" id="overall-tab-pane" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Month</th>
                                            <th>Avg Amount</th>
                                            <th>Total Amount</th>
                                            <th>Years Data</th>
                                            <th>Transactions</th>
                                            <th>Trend</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $totalAvg = 0;
                                            $monthCount = 0;
                                        @endphp
                                        @for($month = 1; $month <= 12; $month++)
                                            @if(isset($formattedOverallMultiYearAverages[$month]))
                                                @php
                                                    $data = $formattedOverallMultiYearAverages[$month];
                                                    $totalAvg += $data['avg_amount'];
                                                    $monthCount++;
                                                @endphp
                                                <tr>
                                                    <td><strong>{{ $data['month_name'] }}</strong></td>
                                                    <td class="text-right">
                                                        <span class="badge bg-primary">
                                                            {{ number_format($data['avg_amount'], 0) }}
                                                        </span>
                                                    </td>
                                                    <td class="text-right">
                                                        {{ number_format($data['total_amount'], 0) }}
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info">{{ $data['years_count'] }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        {{ $data['transaction_count'] }}
                                                    </td>
                                                    <td>
                                                        @php
                                                            $maxAvg = collect($formattedOverallMultiYearAverages)->max('avg_amount');
                                                            $percentage = $maxAvg > 0 ? ($data['avg_amount'] / $maxAvg) * 100 : 0;
                                                        @endphp
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-{{ $percentage > 80 ? 'danger' : ($percentage > 50 ? 'warning' : 'success') }}" 
                                                                 role="progressbar" 
                                                                 style="width: {{ $percentage }}%"
                                                                 aria-valuenow="{{ $percentage }}" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100">
                                                                {{ round($percentage) }}%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endfor
                                        @if($monthCount > 0)
                                            <tr class="table-primary">
                                                <td><strong>Average</strong></td>
                                                <td class="text-right">
                                                    <strong>{{ number_format($totalAvg / $monthCount, 0) }}</strong>
                                                </td>
                                                <td colspan="4"></td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Monthly Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="monthlyAveragesChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Insights Tab -->
                <div class="tab-pane fade" id="summary-tab-pane" role="tabpanel">
                    <div class="row">
                        @php
                            $monthsWithData = collect($formattedOverallMultiYearAverages)->filter(function($item) {
                                return $item['avg_amount'] > 0;
                            });
                            $highestMonth = $monthsWithData->sortByDesc('avg_amount')->first();
                            $lowestMonth = $monthsWithData->sortBy('avg_amount')->first();
                            $totalMonthsData = $monthsWithData->count();
                            $averagePerMonth = $monthsWithData->avg('avg_amount');
                        @endphp
                        
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">Key Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="text-center p-3 border rounded">
                                                <h2 class="text-primary">{{ $totalMonthsData }}</h2>
                                                <p class="mb-0">Months with Data</p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center p-3 border rounded">
                                                <h2 class="text-success">{{ $formattedOverallMultiYearAverages ? count(array_unique(array_column($formattedOverallMultiYearAverages, 'years_count'))) : 0 }}</h2>
                                                <p class="mb-0">Years Analyzed</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <h6>Average Monthly Spending:</h6>
                                        <div class="display-4 text-center text-success">
                                            {{ number_format($averagePerMonth, 0) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-info text-white">
                                    <h5 class="card-title mb-0">Highest & Lowest Months</h5>
                                </div>
                                <div class="card-body">
                                    @if($highestMonth)
                                        <div class="alert alert-danger">
                                            <h6>📈 Highest Spending Month:</h6>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong>{{ $highestMonth['month_name'] }}</strong>
                                                <span class="badge bg-danger">
                                                    {{ number_format($highestMonth['avg_amount'], 0) }}
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                Based on {{ $highestMonth['years_count'] }} years of data
                                            </small>
                                        </div>
                                    @endif
                                    
                                    @if($lowestMonth)
                                        <div class="alert alert-success">
                                            <h6>📉 Lowest Spending Month:</h6>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong>{{ $lowestMonth['month_name'] }}</strong>
                                                <span class="badge bg-success">
                                                    {{ number_format($lowestMonth['avg_amount'], 0) }}
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                Based on {{ $lowestMonth['years_count'] }} years of data
                                            </small>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Recommendations</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        @if($highestMonth && $lowestMonth)
                                            <li class="list-group-item">
                                                💡 Consider reducing spending in <strong>{{ $highestMonth['month_name'] }}</strong> 
                                                ({{ number_format($highestMonth['avg_amount'], 0) }}) by allocating some expenses 
                                                to <strong>{{ $lowestMonth['month_name'] }}</strong> 
                                                ({{ number_format($lowestMonth['avg_amount'], 0) }})
                                            </li>
                                        @endif
                                        
                                        <li class="list-group-item">
                                            💡 Use these historical averages to set more accurate monthly budgets
                                        </li>
                                        
                                        <li class="list-group-item">
                                            💡 Note that averages are based on {{ $monthsWithData->first()['years_count'] ?? 0 }} 
                                            years of data, providing reliable patterns
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function calculateTotal() {
            var total = 0;
            var projectedExpenseInputs = document.getElementsByClassName('projectedExpenseInput');
            for (var i = 0; i < projectedExpenseInputs.length; i++) {
                var value = parseFloat(projectedExpenseInputs[i].value);
                total += isNaN(value) ? 0 : value;
            }
            document.getElementById('totalProjectedOutput').innerText = total.toFixed(2);
        }

        function previewProjection() {
            var reduction = parseFloat(document.getElementById('reduction_percent').value) || 0.10;
            var monthlyLimit = parseFloat(document.getElementById('monthlyLimitOutput').innerText.replace(/,/g, '')) || 0;

            var inputs = document.getElementsByClassName('projectedExpenseInput');
            var bases = [];
            var baseSum = 0;
            for (var i = 0; i < inputs.length; i++) {
                var avg = parseFloat(inputs[i].dataset.avg) || 0;
                var last = parseFloat(inputs[i].dataset.last) || 0;
                var base = (last > 0) ? last : avg;
                bases.push({
                    el: inputs[i],
                    base: base
                });
                baseSum += base * (1 - reduction);
            }

            if (baseSum <= 0) {
                // fallback proportional allocation using avg values
                var avgSum = 0;
                for (var i = 0; i < inputs.length; i++) {
                    avgSum += parseFloat(inputs[i].dataset.avg) || 0;
                }
                if (avgSum <= 0) {
                    // nothing to project
                    for (var i = 0; i < inputs.length; i++) {
                        inputs[i].value = (0).toFixed(2);
                    }
                } else {
                    for (var i = 0; i < inputs.length; i++) {
                        var share = (parseFloat(inputs[i].dataset.avg) || 0) / avgSum;
                        inputs[i].value = (monthlyLimit * share).toFixed(2);
                    }
                }
            } else {
                var scale = monthlyLimit / baseSum;
                for (var i = 0; i < bases.length; i++) {
                    var projected = Math.max(0, bases[i].base * (1 - reduction)) * scale;
                    bases[i].el.value = projected.toFixed(2);
                }
            }

            // Populate modal table for editing
            var tbody = document.querySelector('#previewTable tbody');
            tbody.innerHTML = '';
            var modalTotal = 0;
            for (var i = 0; i < bases.length; i++) {
                var inp = bases[i].el;
                var cat = inp.dataset.cat;
                var catname = inp.dataset.catname || ('Category ' + cat);
                var baseVal = bases[i].base;
                var projVal = parseFloat(inp.value) || 0;

                var tr = document.createElement('tr');
                var tdName = document.createElement('td');
                tdName.textContent = catname;
                var tdBase = document.createElement('td');
                tdBase.textContent = baseVal.toFixed(2);
                var tdProj = document.createElement('td');
                var modalInput = document.createElement('input');
                modalInput.type = 'number';
                modalInput.step = '0.01';
                modalInput.min = '0';
                modalInput.className = 'form-control modalProjectedInput';
                modalInput.value = projVal.toFixed(2);
                modalInput.dataset.cat = cat;
                tdProj.appendChild(modalInput);

                tr.appendChild(tdName);
                tr.appendChild(tdBase);
                tr.appendChild(tdProj);
                tbody.appendChild(tr);

                modalTotal += projVal;
            }
            document.getElementById('modalTotal').innerText = modalTotal.toFixed(2);

            // show modal (support Bootstrap 4 & 5)
            try {
                if (window.jQuery && typeof jQuery('#previewModal').modal === 'function') {
                    jQuery('#previewModal').modal('show');
                } else if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
                    var m = new bootstrap.Modal(document.getElementById('previewModal'));
                    m.show();
                }
            } catch (e) {
                console.warn('Could not show modal', e);
            }

            calculateTotal();
        }

        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
            document.getElementById('previewBtn').addEventListener('click', function(e) {
                e.preventDefault();
                previewProjection();
            });

            // Apply modal edits back to main inputs
            document.getElementById('applyModalBtn').addEventListener('click', function() {
                var modalInputs = document.getElementsByClassName('modalProjectedInput');
                var modalSum = 0;
                for (var i = 0; i < modalInputs.length; i++) {
                    var m = modalInputs[i];
                    var cat = m.dataset.cat;
                    var val = parseFloat(m.value) || 0;
                    modalSum += val;
                    var mainInp = document.querySelector('input.projectedExpenseInput[data-cat="' + cat +
                        '"]');
                    if (mainInp) mainInp.value = val.toFixed(2);
                }
                document.getElementById('modalTotal').innerText = modalSum.toFixed(2);
                calculateTotal();
                // hide modal
                try {
                    if (window.jQuery && typeof jQuery('#previewModal').modal === 'function') {
                        jQuery('#previewModal').modal('hide');
                    } else if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
                        var m = bootstrap.Modal.getInstance(document.getElementById('previewModal'));
                        if (m) m.hide();
                    }
                } catch (e) {
                    console.warn('Could not hide modal', e);
                }
            });

            // Initialize tooltips for the multi-year averages section
            if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip === 'function') {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }

            // Initialize chart for monthly averages
            initializeMonthlyAveragesChart();
        });

        function initializeMonthlyAveragesChart() {
            var ctx = document.getElementById('monthlyAveragesChart');
            if (!ctx) return;

            // Prepare data from PHP
            var labels = [];
            var data = [];
            var colors = [];
            
            @for($month = 1; $month <= 12; $month++)
                @if(isset($formattedOverallMultiYearAverages[$month]))
                    labels.push("{{ \Carbon\Carbon::create()->month($month)->format('M') }}");
                    data.push({{ $formattedOverallMultiYearAverages[$month]['avg_amount'] }});
                    
                    // Color coding based on spending level
                    @php
                        $maxAvg = collect($formattedOverallMultiYearAverages)->max('avg_amount');
                        $percentage = $maxAvg > 0 ? ($formattedOverallMultiYearAverages[$month]['avg_amount'] / $maxAvg) * 100 : 0;
                        $color = $percentage > 80 ? '#dc3545' : ($percentage > 50 ? '#ffc107' : '#28a745');
                    @endphp
                    colors.push("{{ $color }}");
                @endif
            @endfor

            if (data.length === 0) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Monthly Average Spending',
                        data: data,
                        backgroundColor: colors,
                        borderColor: '#495057',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Average: ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</x-backend.layouts.master>