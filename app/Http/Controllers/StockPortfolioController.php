<?php

namespace App\Http\Controllers;

use App\Models\StockCash;
use App\Models\StockDividend;
use App\Models\StockSymbol;
use App\Models\StockTrade;
use App\Services\StockPortfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** DSE stock portfolio: holdings, P/L and cash from stock_trades / stock_cash / stock_dividends. */
class StockPortfolioController extends Controller
{
    private const CASH_IN = ['Deposit', 'Dividend'];
    private const CASH_OUT = ['Withdraw', 'Fee'];

    public function index()
    {
        $portfolio = StockPortfolio::load();
        $symbols = StockSymbol::orderBy('symbol')->get();
        $cash = StockCash::orderByDesc('date')->orderByDesc('id')->get();
        $dividends = StockDividend::orderByDesc('record_date')->get();

        return view('backend.stocks.index', compact('portfolio', 'symbols', 'cash', 'dividends'));
    }

    public function storeTrade(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'side' => 'required|in:Buy,Sell',
            'symbol' => 'required|string|max:32',
            'market' => 'required|in:Public,Spot',
            'qty' => 'required|integer|min:1',
            'rate' => 'required|numeric|gt:0',
            'commission' => 'nullable|numeric|min:0',
            'category' => ['nullable', Rule::in(array_keys(StockPortfolio::SETTLEMENT_DAYS))],
            'company' => 'nullable|string|max:191',
            'note' => 'nullable|string|max:191',
        ]);
        $data['symbol'] = strtoupper(trim($data['symbol']));

        DB::transaction(function () use ($data) {
            // New symbol: remember its company and category (category decides when shares become sellable).
            StockSymbol::firstOrCreate(['symbol' => $data['symbol']], ['company' => $data['company'] ?? null, 'category' => $data['category'] ?? 'A']);
            StockTrade::create([
                'date' => $data['date'], 'side' => $data['side'], 'symbol' => $data['symbol'], 'market' => $data['market'],
                'qty' => $data['qty'], 'rate' => $data['rate'], 'note' => $data['note'] ?? null,
                'commission' => $data['commission'] ?? round($data['qty'] * $data['rate'] * StockPortfolio::COMMISSION, 4),
            ]);
            // Checked with the new trade in date order, so a sell dated before its buy is caught too (throwing undoes the insert).
            if ($bad = collect(StockPortfolio::load()['oversold'])->firstWhere('symbol', $data['symbol'])) {
                throw ValidationException::withMessages(['qty' => "On {$bad['date']} you held only {$bad['held']} {$bad['symbol']} shares, so {$bad['qty']} cannot be sold then. Check the date and quantity."]);
            }
            StockPortfolio::syncUnrealizedLedger();
        });

        return redirect()->route('stocks.index')->withMessage("{$data['side']} {$data['qty']} {$data['symbol']} saved.");
    }

    public function storeCash(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'type' => ['required', Rule::in(array_merge(self::CASH_IN, self::CASH_OUT))],
            'amount' => 'required|numeric|gt:0',
            'particulars' => 'nullable|string|max:191',
        ]);
        $in = in_array($data['type'], self::CASH_IN, true);
        StockCash::create(['date' => $data['date'], 'type' => $data['type'], 'particulars' => $data['particulars'] ?? null,
            'cash_in' => $in ? $data['amount'] : 0, 'cash_out' => $in ? 0 : $data['amount']]);

        return redirect()->route('stocks.index')->withMessage("{$data['type']} of Tk " . number_format($data['amount'], 2) . ' saved.');
    }

    public function storeDividend(Request $request)
    {
        $data = $request->validate([
            'symbol' => 'required|string|max:32',
            'bo_account' => 'nullable|string|max:191',
            'record_date' => 'required|date',
            'percent' => 'nullable|numeric|min:0',
            'qty' => 'nullable|integer|min:0',
            'gross' => 'required|numeric|gt:0',
            'tax' => 'nullable|numeric|min:0',
            'received_date' => 'nullable|date',
        ]);
        $data['symbol'] = strtoupper(trim($data['symbol']));
        $data['tax'] = $data['tax'] ?? 0;
        $data['net'] = $data['gross'] - $data['tax'];
        $data['status'] = empty($data['received_date']) ? 'Receivable' : 'Received';

        DB::transaction(function () use ($data) {
            StockDividend::create($data);
            // A received dividend is also cash in on the BO account, as in the workbook's Cash sheet.
            if ($data['status'] === 'Received') {
                StockCash::create(['date' => $data['received_date'], 'type' => 'Dividend', 'particulars' => "Cash dividend – {$data['symbol']}", 'cash_in' => $data['net']]);
            }
        });

        return redirect()->route('stocks.index')->withMessage("{$data['symbol']} dividend saved.");
    }

    public function fetchPrices()
    {
        [$updated, $failed] = StockPortfolio::fetchDsePrices();
        $message = $updated ? 'DSE prices updated for ' . implode(', ', array_keys($updated)) . '.' : 'No prices could be read from DSE.';

        return redirect()->route('stocks.index')->withMessage($message . ($failed ? ' Not found: ' . implode(', ', $failed) . ' – enter those by hand.' : ''));
    }

    /** prices[SYMBOL] => latest price; the price it replaces becomes yesterday's close for day change. */
    public function updatePrices(Request $request)
    {
        $request->validate(['prices' => 'required|array', 'prices.*' => 'nullable|numeric|gt:0']);
        $today = now()->toDateString();
        $changed = 0;
        foreach (StockSymbol::whereIn('symbol', array_keys($request->prices))->get() as $s) {
            $price = $request->prices[$s->symbol];
            if ($price === null || $price === '' || (float) $price === $s->price) continue;
            if ($s->price_date && $s->price_date->toDateString() < $today) $s->prev_close = $s->price;
            $s->price = $price;
            $s->price_date = $today;
            $s->save();
            $changed++;
        }
        if ($changed) StockPortfolio::syncUnrealizedLedger();

        return redirect()->route('stocks.index')->withMessage("Prices updated for {$changed} " . ($changed === 1 ? 'stock' : 'stocks') . '.');
    }
}
