<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCalculation;
use App\Services\VentureBilling;
use App\Services\VentureLedger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Freelance & Business (AUGUSTA IT): income, costs and profit per client, from the normal ledger. */
class VentureController extends Controller
{
    public function index(Request $request)
    {
        $ventures = config('finance.ventures');
        $key = array_key_exists($request->get('venture'), $ventures) ? $request->get('venture') : array_key_first($ventures);
        $rows = VentureLedger::rows($key);
        $summary = VentureLedger::summarize($rows);
        $overview = collect(array_keys($ventures))->mapWithKeys(fn($k) => [$k => VentureLedger::summarize($k === $key ? $rows : VentureLedger::rows($k))['total']])->all();
        $clients = collect($rows)->pluck('client')->filter()->unique()->sort()->values();
        $accounts = config('finance.handcash_rules');
        $invoices = VentureBilling::invoices($key);
        $dueByClient = VentureBilling::dueByClient($invoices);
        $schedules = \App\Models\VentureSchedule::where('venture', $key)->orderByDesc('is_active')->orderBy('next_due')->get();
        $sendSoon = VentureBilling::reminders()['send']->where('venture', $key)->pluck('id')->all();
        $advice = VentureLedger::advice($ventures[$key]['label'], $summary, $dueByClient,
            $schedules->whereIn('id', $sendSoon)->map(fn($s) => [$s->client, $s->next_due->toDateString(), VentureBilling::itemsTotal($s->items)])->values()->all(), now()->format('Y'));

        return view('backend.ventures.index', compact('ventures', 'key', 'rows', 'summary', 'overview', 'clients', 'accounts', 'invoices', 'dueByClient', 'schedules', 'sendSoon', 'advice'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'venture' => ['required', Rule::in(array_keys(config('finance.ventures')))],
            'kind' => 'required|in:income,cost',
            'date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'name' => 'required|string|max:255',
            'client' => 'nullable|string|max:120',
            'account' => ['required', Rule::in(array_keys(config('finance.handcash_rules')))],
        ]);
        VentureLedger::record($data['venture'], $data['kind'], $data['date'], $data['amount'], $data['name'], $data['client'] ?? null, $data['account']);

        return redirect()->route('ventures.index', ['venture' => $data['venture']])
            ->withMessage(($data['kind'] === 'income' ? 'Income' : 'Cost') . ' of Tk ' . number_format($data['amount'], 2) . ' saved to ' . config("finance.ventures.{$data['venture']}.label") . '.');
    }

    /** Tag (or clear) the client / project of an existing freelance or business row. */
    public function setClient(Request $request, ExpenseCalculation $expense)
    {
        $data = $request->validate(['client' => 'nullable|string|max:120', 'venture' => ['required', Rule::in(array_keys(config('finance.ventures')))]]);
        $ids = array_filter(array_map(fn($c) => optional($c)->id, VentureLedger::categories($data['venture'])));
        abort_unless(in_array((int) $expense->category_id, array_map('intval', $ids), true), 404);

        $expense->client = $data['client'] ? strtoupper(trim($data['client'])) : null;
        $expense->save();

        return redirect()->route('ventures.index', ['venture' => $data['venture']])->withMessage('Client updated.');
    }
}
