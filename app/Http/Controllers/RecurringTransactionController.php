<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringTransactionController extends Controller
{
    public function index()
    {
        $recurring = RecurringTransaction::with('category')
            ->orderByDesc('is_active')
            ->orderBy('next_run_on')
            ->get();
        $categories = $this->categoryOptions();

        return view('backend.library.recurring.index', compact('recurring', 'categories'));
    }

    public function store(Request $request, RecurringTransactionService $service)
    {
        $rule = new RecurringTransaction($this->validated($request));
        $rule->is_active = true;
        $rule->rescheduleNextRun();
        $rule->save();

        $created = $service->runDue();

        return redirect()->route('recurring.index')->withMessage(
            'Recurring transaction saved.' . ($created ? " {$created} past-due " . ($created === 1 ? 'entry was' : 'entries were') . ' created.' : '')
        );
    }

    public function edit(RecurringTransaction $recurring)
    {
        $categories = $this->categoryOptions();

        return view('backend.library.recurring.edit', compact('recurring', 'categories'));
    }

    public function update(Request $request, RecurringTransaction $recurring, RecurringTransactionService $service)
    {
        $wasActive = $recurring->is_active;
        $recurring->fill($this->validated($request));
        $recurring->is_active = $request->boolean('is_active');
        if (!$wasActive && $recurring->is_active) {
            $this->resumeFromToday($recurring);
        }
        $recurring->rescheduleNextRun();
        $recurring->save();

        $service->runDue();

        return redirect()->route('recurring.index')->withMessage('Recurring transaction updated.');
    }

    public function toggle(RecurringTransaction $recurring, RecurringTransactionService $service)
    {
        $recurring->is_active = !$recurring->is_active;
        if ($recurring->is_active) {
            $this->resumeFromToday($recurring);
            $recurring->rescheduleNextRun();
        }
        $recurring->save();
        $service->runDue();

        return back()->withMessage($recurring->is_active ? 'Recurring transaction resumed.' : 'Recurring transaction paused.');
    }

    public function destroy(RecurringTransaction $recurring)
    {
        // Entries already created stay in Income & Expenses; only the template is removed.
        $recurring->delete();

        return redirect()->route('recurring.index')->withMessage('Recurring transaction deleted. Entries it already created were kept.');
    }

    public function runNow(RecurringTransactionService $service)
    {
        $created = $service->runDue();

        return back()->withMessage($created
            ? "Created {$created} due " . ($created === 1 ? 'entry' : 'entries') . '.'
            : 'Nothing is due right now.');
    }

    /** Resuming continues from today; it does not back-fill the months it was paused. */
    private function resumeFromToday(RecurringTransaction $recurring): void
    {
        $yesterday = now()->subDay()->startOfDay();
        if (!$recurring->last_run_on || $recurring->last_run_on->lt($yesterday)) {
            $recurring->last_run_on = $yesterday;
        }
    }

    private function validated(Request $request): array
    {
        $accounts = implode(',', array_keys(config('finance.handcash_rules')));
        $isTransfer = $request->input('kind') === RecurringTransaction::KIND_TRANSFER;

        $data = $request->validate([
            'kind' => 'required|in:' . RecurringTransaction::KIND_INCOME . ',' . RecurringTransaction::KIND_TRANSFER,
            'name' => 'required|string|max:191',
            // Income must use an INCOME category; transfers use accounts instead.
            'category_id' => [$isTransfer ? 'nullable' : 'required', Rule::exists('categories', 'id')->where('types', 'INCOME')],
            'from_account' => [$isTransfer ? 'required' : 'nullable', 'in:' . $accounts],
            'to_account' => [$isTransfer ? 'required' : 'nullable', 'in:' . $accounts, 'different:from_account'],
            'amount' => 'required|numeric|min:0.01',
            'day_of_month' => 'required|integer|between:1,31',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ], [
            'category_id.exists' => 'Choose an income category.',
            'to_account.different' => 'The "to" account must be different from the "from" account.',
        ]);

        if ($isTransfer) {
            $data['category_id'] = null;
        } else {
            $data['from_account'] = $data['to_account'] = null;
        }
        $data['start_date'] = Carbon::parse($data['start_date']);
        $data['end_date'] = !empty($data['end_date']) ? Carbon::parse($data['end_date']) : null;

        return $data;
    }

    private function categoryOptions()
    {
        return Category::where('types', 'INCOME')->orderBy('name')->get(['id', 'name', 'types']);
    }
}
