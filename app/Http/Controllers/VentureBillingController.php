<?php

namespace App\Http\Controllers;

use App\Models\VentureInvoice;
use App\Models\VentureSchedule;
use App\Services\VentureBilling;
use App\Services\VentureLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Invoices / bills / cash memos, payments against them, recurring bill reminders and the letterhead. */
class VentureBillingController extends Controller
{
    /** New document form; ?schedule= prefills it from a recurring bill, ?from= copies an old invoice. */
    public function create(Request $request)
    {
        $schedule = $request->filled('schedule') ? VentureSchedule::findOrFail($request->schedule) : null;
        $copy = $request->filled('from') ? VentureInvoice::findOrFail($request->from) : null;
        $source = $schedule ?? $copy;
        $venture = $source->venture ?? (array_key_exists($request->venture, config('finance.ventures')) ? $request->venture : 'freelance');
        $date = now()->toDateString();
        $invoice = new VentureInvoice([
            'venture' => $venture, 'type' => $copy->type ?? 'Bill', 'client' => $source->client ?? '', 'bill_to' => $source->bill_to ?? '',
            'date' => $date, 'due_date' => $schedule ? $schedule->next_due : now()->addDays(15), 'items' => $source->items ?? [['description' => '', 'period' => '', 'qty' => 1, 'rate' => '']],
            'notes' => $copy->notes ?? 'Tax is not applicable',
        ]);
        $invoice->number = VentureBilling::nextNumber($date, VentureInvoice::pluck('number')->all());

        return view('backend.ventures.invoice_form', ['invoice' => $invoice, 'schedule' => $schedule, 'clients' => $this->clients()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['total'] = VentureBilling::itemsTotal($data['items']);
        if ($data['type'] === 'Cash memo') {
            $request->validate(['account' => ['required', Rule::in(array_keys(config('finance.handcash_rules')))]]);
        }

        $invoice = DB::transaction(function () use ($data, $request) {
            $invoice = VentureInvoice::create($data);
            // Cash memo = paid on the spot: book the income straight away.
            if ($invoice->type === 'Cash memo' && $invoice->total > 0) {
                VentureLedger::record($invoice->venture, 'income', $invoice->date->toDateString(), $invoice->total, "{$invoice->type} {$invoice->number}", $invoice->client, $request->account, $invoice->id);
            }
            // Billed from a recurring schedule: move it to its next cycle.
            if ($invoice->schedule_id && $s = VentureSchedule::find($invoice->schedule_id)) {
                $s->next_due = $s->next_due->copy()->addMonthsNoOverflow($s->every_months);
                $s->save();
            }

            return $invoice;
        });

        return redirect()->route('ventures.invoices.show', $invoice)->withMessage("{$invoice->type} {$invoice->number} saved.");
    }

    public function edit(VentureInvoice $invoice)
    {
        return view('backend.ventures.invoice_form', ['invoice' => $invoice, 'schedule' => null, 'clients' => $this->clients()]);
    }

    public function update(Request $request, VentureInvoice $invoice)
    {
        $data = $this->validated($request, $invoice);
        $data['total'] = VentureBilling::itemsTotal($data['items']);
        $invoice->update($data);

        return redirect()->route('ventures.invoices.show', $invoice)->withMessage("{$invoice->type} {$invoice->number} updated.");
    }

    /** Printable page (browser Print → Save as PDF). */
    public function show(VentureInvoice $invoice)
    {
        $row = collect(VentureBilling::invoices($invoice->venture))->first(fn($i) => $i['model']->id === $invoice->id);

        return view('backend.ventures.invoice_print', ['invoice' => $invoice, 'paid' => $row['paid'], 'due' => $row['due'], 'status' => $row['status'],
            'payments' => $invoice->payments()->with('handCashes:id,expense_calculation_id,rules')->orderBy('date')->get(), 'profile' => VentureBilling::profile($invoice->venture)]);
    }

    /** Money received against an invoice: a normal ledger income row linked to it. */
    public function pay(Request $request, VentureInvoice $invoice)
    {
        $data = $request->validate(['date' => 'required|date', 'amount' => 'required|numeric|gt:0', 'account' => ['required', Rule::in(array_keys(config('finance.handcash_rules')))]]);
        VentureLedger::record($invoice->venture, 'income', $data['date'], $data['amount'], "{$invoice->type} {$invoice->number} PAYMENT", $invoice->client, $data['account'], $invoice->id);

        return back()->withMessage('Payment of Tk ' . number_format($data['amount'], 2) . " recorded against {$invoice->number}.");
    }

    public function storeSchedule(Request $request)
    {
        VentureSchedule::create($this->validatedSchedule($request) + ['is_active' => true]);

        return redirect()->route('ventures.index', ['venture' => $request->venture])->withMessage('Bill reminder saved.');
    }

    public function updateSchedule(Request $request, VentureSchedule $schedule)
    {
        $data = $this->validatedSchedule($request);
        // Same amount: keep the detailed bill lines instead of collapsing them to one.
        if (abs(VentureBilling::itemsTotal($schedule->items) - VentureBilling::itemsTotal($data['items'])) < 0.005) unset($data['items']);
        $schedule->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('ventures.index', ['venture' => $schedule->venture])->withMessage('Bill reminder updated.');
    }

    public function profile(Request $request)
    {
        $data = $request->validate(['venture' => ['required', Rule::in(array_keys(config('finance.ventures')))], 'name' => 'required|string|max:191',
            'address' => 'nullable|string|max:500', 'phone' => 'nullable|string|max:191', 'email' => 'nullable|string|max:191',
            'payment' => 'nullable|string|max:2000', 'footer' => 'nullable|string|max:500']);
        VentureBilling::saveProfile($data['venture'], array_map(fn($v) => (string) $v, array_diff_key($data, ['venture' => 1])));

        return back()->withMessage('Letterhead saved.');
    }

    private function validated(Request $request, ?VentureInvoice $invoice = null): array
    {
        $data = $request->validate([
            'venture' => ['required', Rule::in(array_keys(config('finance.ventures')))],
            'type' => ['required', Rule::in(VentureInvoice::TYPES)],
            'number' => ['required', 'string', 'max:32', Rule::unique('venture_invoices', 'number')->ignore(optional($invoice)->id)],
            'client' => 'required|string|max:120',
            'bill_to' => 'nullable|string|max:1000',
            'date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:date',
            'notes' => 'nullable|string|max:2000',
            'schedule_id' => 'nullable|integer|exists:venture_schedules,id',
            'items' => 'required|array|min:1',
            'items.*.description' => 'nullable|string|max:1000',
            'items.*.period' => 'nullable|string|max:120',
            'items.*.qty' => 'nullable|numeric|min:0',
            'items.*.rate' => 'nullable|numeric', // negative = discount line
        ]);
        $data['client'] = strtoupper(trim($data['client']));
        $data['items'] = array_values(array_filter($data['items'], fn($i) => trim((string) ($i['description'] ?? '')) !== '' || (float) ($i['rate'] ?? 0) != 0));
        if (!$data['items'] || VentureBilling::itemsTotal($data['items']) < 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Add at least one line; the total cannot be negative.']);
        }

        return $data;
    }

    private function validatedSchedule(Request $request): array
    {
        $data = $request->validate([
            'venture' => ['required', Rule::in(array_keys(config('finance.ventures')))],
            'client' => 'required|string|max:120', 'bill_to' => 'nullable|string|max:1000', 'title' => 'required|string|max:191',
            'amount' => 'required|numeric|gt:0', 'every_months' => 'required|integer|between:1,60',
            'next_due' => 'required|date', 'remind_days' => 'required|integer|between:0,120',
        ]);
        $data['client'] = strtoupper(trim($data['client']));
        // A reminder carries one default line; the invoice form lets you split it when you bill.
        $data['items'] = [['description' => $data['title'], 'period' => '', 'qty' => 1, 'rate' => $data['amount']]];
        unset($data['amount']);

        return $data;
    }

    private function clients()
    {
        return \App\Models\ExpenseCalculation::whereNotNull('client')->distinct()->orderBy('client')->pluck('client')
            ->merge(VentureInvoice::distinct()->pluck('client'))->unique()->sort()->values();
    }
}
