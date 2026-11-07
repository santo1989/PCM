<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->type }} {{ $invoice->number }} – {{ $invoice->client }}</title>
    <style>
        :root { --ink: #111827; --muted: #6b7280; --line: #d1d5db; --brand: #4f46e5; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: var(--ink); font: 14px/1.45 "Segoe UI", Roboto, Arial, sans-serif; }
        .sheet { background: #fff; max-width: 820px; margin: 16px auto; padding: 40px 44px; box-shadow: 0 2px 12px rgba(0, 0, 0, .08); }
        h1 { margin: 0 0 18px; font-size: 30px; letter-spacing: .04em; color: var(--brand); text-transform: uppercase; }
        .row { display: flex; flex-wrap: wrap; gap: 24px; justify-content: space-between; }
        .muted { color: var(--muted); }
        .pre { white-space: pre-line; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border: 1px solid var(--line); padding: 7px 9px; vertical-align: top; }
        th { background: #f9fafb; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; text-align: left; }
        .num { text-align: right; white-space: nowrap; }
        .total td { font-weight: 700; background: #f9fafb; }
        .stamp { display: inline-block; border: 2px solid; padding: 2px 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
        .Paid { color: #15803d; } .Overdue { color: #b91c1c; } .Due, .Partly { color: #b45309; }
        .toolbar { max-width: 820px; margin: 16px auto 0; display: flex; flex-wrap: wrap; gap: 8px; padding: 0 16px; }
        .toolbar a, .toolbar button { font: inherit; padding: 6px 12px; border: 1px solid var(--line); background: #fff; color: var(--ink); border-radius: 6px; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: var(--brand); color: #fff; border-color: var(--brand); }
        .pay { max-width: 820px; margin: 12px auto; padding: 12px 16px; background: #fff; border-radius: 8px; display: flex; flex-wrap: wrap; gap: 8px; align-items: end; }
        .pay label { display: grid; gap: 2px; font-size: 12px; color: var(--muted); }
        .pay input, .pay select { font: inherit; padding: 5px 8px; border: 1px solid var(--line); border-radius: 6px; }
        .msg { max-width: 820px; margin: 12px auto 0; padding: 8px 14px; background: #ecfdf5; color: #065f46; border-radius: 6px; }
        @media (max-width: 600px) { .sheet { padding: 22px 16px; margin: 8px; } h1 { font-size: 24px; } th, td { padding: 5px; font-size: 12.5px; } }
        @media print { body { background: #fff; } .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    @php
        $tk = fn($v) => number_format($v, 2) . ' TK';
        $memo = $invoice->type === 'Cash memo';
    @endphp

    @if (session('message'))<div class="msg no-print" role="status">{{ session('message') }}</div>@endif
    <div class="toolbar no-print">
        <a href="{{ route('ventures.index', ['venture' => $invoice->venture]) }}">← Freelance &amp; Business</a>
        <a href="{{ route('ventures.invoices.edit', $invoice) }}">Edit</a>
        <a href="{{ route('ventures.invoices.create', ['from' => $invoice->id]) }}">Copy as new</a>
        <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
    </div>
    @if (!$memo && $due > 0)
        <form method="POST" action="{{ route('ventures.invoices.pay', $invoice) }}" class="pay no-print">
            @csrf
            <strong style="width: 100%;">Record a payment received (Tk {{ number_format($due, 2) }} still due)</strong>
            <label>Date<input type="date" name="date" value="{{ now()->toDateString() }}" required></label>
            <label>Amount<input type="number" name="amount" min="0.01" step="any" value="{{ $due }}" required></label>
            <label>Received into<select name="account">@foreach (config('finance.handcash_rules') as $k => $v)<option value="{{ $k }}" {{ $k === 'CITY_BANK' ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></label>
            <button type="submit" class="primary" style="font: inherit; padding: 6px 12px; border-radius: 6px; border: 0; background: #4f46e5; color: #fff;">Save payment</button>
        </form>
    @endif

    <main class="sheet">
        @if ($memo)
            <h1>Cash receipt</h1>
            <table>
                <tr><th>Received by</th><td>{{ $profile['name'] }}</td><th>Payment date</th><td>{{ $invoice->date->format('d/m/Y') }}</td></tr>
                <tr><th>Receipt number</th><td>{{ $invoice->number }}</td><th>Amount paid</th><td class="num">TK {{ number_format($invoice->total, 2) }}</td></tr>
                <tr><th>Received from</th><td colspan="3" class="pre">{{ $invoice->bill_to ?: $invoice->client }}</td></tr>
                <tr><th colspan="4" style="text-align: center;">For</th></tr>
                @foreach ($invoice->items as $it)
                    <tr><td colspan="3" class="pre">{{ $it['description'] }}{{ !empty($it['period']) ? ' (' . $it['period'] . ')' : '' }}</td><td class="num">{{ $tk(($it['qty'] ?? 1) * ($it['rate'] ?? 0)) }}</td></tr>
                @endforeach
                @if ($invoice->notes)<tr><td colspan="4" class="pre" style="text-align: center;">{{ $invoice->notes }}</td></tr>@endif
                <tr><td colspan="2"></td><th colspan="2" style="text-align: center;">Thank you</th></tr>
            </table>
        @else
            <div class="row">
                <div>
                    <h1>{{ $invoice->type }}</h1>
                    <div style="font-weight: 700; font-size: 16px;">{{ $profile['name'] }}</div>
                    <div class="pre muted">{{ $profile['address'] }}</div>
                    @if ($profile['phone'])<div class="muted">Mobile: {{ $profile['phone'] }}</div>@endif
                    @if ($profile['email'])<div class="muted">{{ $profile['email'] }}</div>@endif
                </div>
                <div style="text-align: right;"><span class="stamp {{ strtok($status, ' ') }}">{{ $status }}</span></div>
            </div>

            <div class="row" style="margin-top: 22px;">
                <div><div class="muted">To,</div><div class="pre" style="font-weight: 600;">{{ $invoice->bill_to ?: $invoice->client }}</div></div>
                <table style="width: auto; margin: 0;">
                    <tr><th>{{ $invoice->type }} date</th><td>{{ strtoupper($invoice->date->format('M d, Y')) }}</td></tr>
                    <tr><th>{{ $invoice->type }} no.</th><td>{{ $invoice->number }}</td></tr>
                    @if ($invoice->due_date)<tr><th>Pay by</th><td>{{ strtoupper($invoice->due_date->format('M d, Y')) }}</td></tr>@endif
                </table>
            </div>

            <table>
                <thead><tr><th style="width: 46px;">S.No.</th><th>Item description</th><th>Period</th><th class="num">Qty / Unit</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    @foreach ($invoice->items as $n => $it)
                        @php($amount = ($it['qty'] ?? 1) * ($it['rate'] ?? 0))
                        <tr>
                            <td>{{ $n + 1 }}.</td><td class="pre">{{ $it['description'] }}</td><td class="pre">{{ $it['period'] ?? '' }}</td>
                            <td class="num">{{ $it['qty'] ?? 1 }}</td><td class="num">{{ $tk($it['rate'] ?? 0) }}</td><td class="num">{{ $amount < 0 ? '− ' . $tk(-$amount) : $tk($amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total"><td colspan="5" class="num">TOTAL</td><td class="num">{{ $tk($invoice->total) }}</td></tr>
                    @if ($paid > 0)
                        <tr><td colspan="5" class="num">Received</td><td class="num">{{ $tk($paid) }}</td></tr>
                        <tr class="total"><td colspan="5" class="num">Balance due</td><td class="num">{{ $tk($due) }}</td></tr>
                    @endif
                </tfoot>
            </table>

            @if ($invoice->notes)<p class="pre"><strong>Note:</strong> {{ $invoice->notes }}</p>@endif
            @if ($profile['payment'])<div style="margin-top: 14px;"><strong>Payment details</strong><div class="pre">{{ $profile['payment'] }}</div></div>@endif
            <p style="margin-top: 28px;">{{ $profile['footer'] }}<br><span class="muted">({{ $profile['name'] }})</span></p>

            @if ($payments->isNotEmpty())
                <div class="no-print" style="margin-top: 20px;">
                    <strong>Payments received</strong>
                    <table>
                        @foreach ($payments as $p)
                            <tr><td>{{ \Carbon\Carbon::parse($p->date)->format('d M Y') }}</td><td>{{ config('finance.handcash_rules.' . optional($p->handCashes->first())->rules, optional($p->handCashes->first())->rules) }}</td><td class="num">{{ $tk((float) str_replace(',', '', $p->amount)) }}</td></tr>
                        @endforeach
                    </table>
                </div>
            @endif
        @endif
    </main>
</body>
</html>
