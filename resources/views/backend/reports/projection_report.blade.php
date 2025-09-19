<x-backend.layouts.master>
    <x-slot name="title">
        Projection Report
    </x-slot>

    <h3 class="text-center">Projected Monthly Budget</h3>
    <form action="{{ route('calculate_and_save_budget') }}" method="post">
        @csrf
        <div class="row">
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
                            $thisYearExpenseCategory = collect($thisYearExpense)->firstWhere('category_id', $category->id);
                            $lastMonthExpenseCategory = $lastMonthExpense->firstWhere('category_id', $category->id);
                            
                            // Get the projected expense for this category from the database
                            $projectedAmount = $thisMonthProjectedExpenses->get($category->id)->amount ?? 0;
                        @endphp

                        @if (
                            (!empty($thisYearExpenseCategory) && $thisYearExpenseCategory['averageExpense'] > 0) || 
                            (!empty($lastMonthExpenseCategory) && $lastMonthExpenseCategory->totalExpense > 0)
                        )
                            <tr>
                                <td>{{ $category->name }}<input type="hidden" name="category_id[]" value="{{ $category->id }}"></td>
                                <td>{{ number_format($thisYearExpenseCategory['averageExpense'] ?? 0, 2) }}</td>
                                <td>{{ number_format($lastMonthExpenseCategory->totalExpense ?? 0, 2) }}</td>
                                <td>
                                    <input type="text" name="projectedExpense[{{ $category->id }}]"
                                        id="projectedExpense{{ $category->id }}"
                                        class="form-control projectedExpenseInput"
                                        value="{{ number_format($projectedAmount, 2) }}" readonly>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    <tr>
                        <td class="text-right"><strong>Monthly Income:</strong> <strong id="monthlyIncomeOutput">{{ number_format($totalMonthlyIncome, 2) }}</strong></td>
                        <td class="text-right"><strong>Budget Limit (90%):</strong> <strong id="monthlyLimitOutput">{{ number_format($MonthlyactualLimitExpense, 2) }}</strong></td>
                        <td class="text-right"><strong>Total Last Month:</strong> <strong id="lastMonthExpenseOutput">{{ number_format($totallastMonthExpense, 2) }}</strong></td>
                        <td class="text-right"><strong>Total Projected:</strong> <strong id="totalProjectedOutput">0.00</strong></td>
                    </tr>
                </tbody>
            </table>
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Calculate & Save Next Month's Budget</button>
            </div>
        </div>
    </form>

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

        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });
    </script>
</x-backend.layouts.master>