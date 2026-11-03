<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use App\Models\ProjectedExpense;
use App\Services\AI\MLPipeline;
use Illuminate\Http\Request;
use App\Http\Requests\HandCashRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HandCashController extends Controller
{
    public function index()
    {
        $handCashesQuery = HandCash::query()->orderByDesc('date')->orderByDesc('created_at');

        $typeInput = request('types', request('handCashes_type'));
        $typeValues = array_values(array_filter(array_map('strtoupper', (array) $typeInput)));
        if (!empty($typeValues)) {
            $handCashesQuery->whereIn('types', $typeValues);
        }

        $ruleInput = request('rules', request('handCashes_rule'));
        $ruleValues = array_values(array_filter(array_map('strtoupper', (array) $ruleInput)));
        if (!empty($ruleValues)) {
            $handCashesQuery->whereIn('rules', $ruleValues);
        }

        $name = request('name') ?: request('handCashes_name');
        if ($name) {
            $handCashesQuery->where('name', 'like', $this->likeTerm($name));
        }

        $entryDateStart = request('entry_date_start') ?: request('handCashes_date_start');
        $entryDateEnd = request('entry_date_end') ?: request('handCashes_date_end');

        if ($entryDateStart && $entryDateEnd) {
            $handCashesQuery->whereBetween('date', [$entryDateStart, $entryDateEnd]);
        } elseif ($entryDateStart) {
            $handCashesQuery->whereDate('date', '>=', $entryDateStart);
        } elseif ($entryDateEnd) {
            $handCashesQuery->whereDate('date', '<=', $entryDateEnd);
        }

        // Excel export: always available (not gated behind "search first" — that previously
        // broke export on a fresh page load, since nothing had populated the session yet).
        // Reuses the same filters as the table below; if no date range was given, defaults
        // to the current calendar month rather than dumping the entire all-time history.
        $format = strtolower(request('export_format', ''));

        if ($format === 'xlsx') {
            $exportQuery = clone $handCashesQuery;
            $exportRangeStart = $entryDateStart;
            $exportRangeEnd = $entryDateEnd;

            if (!$entryDateStart && !$entryDateEnd) {
                // No transaction dates: export the Balance date range if one is set,
                // otherwise the current month (not the entire history).
                $exportRangeStart = request('balance_date_start');
                $exportRangeEnd = request('balance_date_end');
                if (!$exportRangeStart && !$exportRangeEnd) {
                    $exportRangeStart = now()->startOfMonth()->toDateString();
                    $exportRangeEnd = now()->toDateString();
                }
                $exportQuery->when($exportRangeStart, fn($q) => $q->whereDate('date', '>=', $exportRangeStart))
                    ->when($exportRangeEnd, fn($q) => $q->whereDate('date', '<=', $exportRangeEnd));
            }

            $search_cashes = $exportQuery->get();

            $viewContent = View::make('backend.library.handCashes.export', [
                'search_cashes' => $search_cashes,
                'rangeStart' => $exportRangeStart ?: 'Earliest Record',
                'rangeEnd' => $exportRangeEnd ?: 'Today',
            ])->render();

            $filename = Auth::user()->name . '_' . Carbon::now()->format('Y_m_d') . '_' . time() . '.xls';
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Transfer-Encoding' => 'binary',
                'Cache-Control' => 'must-revalidate',
                'Pragma' => 'public',
                'Content-Length' => strlen($viewContent),
            ];

            return response()->make($viewContent, 200, $headers);
        }

        $handCashes = $handCashesQuery->paginate($this->perPage(20))->appends(request()->query());

        // Perform the database queries and retrieve the data
        // Date filtering for balance calculations
        $balanceDateStart = request('balance_date_start');
        $balanceDateEnd = request('balance_date_end');
        // One bound alone still filters: open side runs from the first record / to today.
        if ($balanceDateStart xor $balanceDateEnd) {
            $balanceDateStart = $balanceDateStart ?: (HandCash::min('date') ?: $balanceDateEnd);
            $balanceDateEnd = $balanceDateEnd ?: now()->toDateString();
        }

        // Bounds for every date input on this page: earliest handcash transaction through today.
        $minDataDate = HandCash::min('date');

        $mobileRules = ['MOBILE_BKASH', 'MOBILE_ROCKET', 'MOBILE_NAGAD'];
        $bankRules = ['CITY_BANK', 'CITY_BANK_ISLAMIC', 'SONALI_BANK_GULSHAN', 'SONALI_BANK_TONGI', 'DBBL', 'PBL', 'FD', 'DPS', 'ISLAMIC_DPS', 'INVESTMENT'];

        $mobile_cash_save = HandCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $mobileRules)
            ->where('types', 'SAVE')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->groupBy('rules', 'types')
            ->get();


        $mobile_cash_withdraw = HandCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $mobileRules)
            ->where('types', 'WIDROWS')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->groupBy('rules', 'types')
            ->get();



        $bank_cash_save = HandCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $bankRules)
            ->where('types', 'SAVE')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->groupBy('rules', 'types')
            ->get();

        $bank_cash_withdraw = HandCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $bankRules)
            ->where('types', 'WIDROWS')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->groupBy('rules', 'types')
            ->get();

        //find total balance from save - widrows amount according to rules and types

        $mobile_cash = HandCash::query()
            ->select([
                'rules',
                DB::raw('SUM(CASE WHEN types = "SAVE" THEN amount ELSE 0 END) - SUM(CASE WHEN types = "WIDROWS" THEN amount ELSE 0 END) as Balance'),
            ])
            ->whereIn('rules', $mobileRules)
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->groupBy('rules') // Remove 'types' from the GROUP BY clause
            ->get();




        $bank_cash = HandCash::query()
            ->select([
                'rules',
                DB::raw('SUM(CASE WHEN types = "SAVE" THEN amount ELSE 0 END) - SUM(CASE WHEN types = "WIDROWS" THEN amount ELSE 0 END) as Balance'),
            ])
            ->whereIn('rules', $bankRules)
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->groupBy('rules')
            ->get();
        //  dd($mobile_cash, $bank_cash);

        // $mobile_cash_save = HandCash::where('rules', 'Mobile')->where('types', 'Save')->get();
        // $mobile_cash_withdraw = HandCash::where('rules', 'Mobile')->where('types', 'Widrows')->get();
        // $bank_cash_save = HandCash::where('rules', 'Bank')->where('types', 'Save')->get();
        // $bank_cash_withdraw = HandCash::where('rules', 'Bank')->where('types', 'Widrows')->get();



        // $handCashes_Peti_withdrow = 

        // // dd($handCashes_Peti_withdrow);
        $handCashes_Peti_save = HandCash::where('rules', 'PETI')->where('types', 'SAVE')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $handCashes_Peti_withdraw = HandCash::where('rules', 'PETI')->where('types', 'WIDROWS')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $handCashes_Peti_balence = $handCashes_Peti_save - $handCashes_Peti_withdraw;
        // $peti_cash_from_expense = ExpenseCalculation::where('types', 'income')->where('date', '>', '2024-04-09')->whereNotIn('category_id', [1])->sum('amount');
        // $handCashes_Peti_save = $handCashes_Peti_save + $peti_cash_from_expense;
        // dd($handCashes_Peti_save);
        // // after 9th april 2024 all expenses will be calculated from the expenses table
        // $handCashes_Peti_withdrow =
        // ExpenseCalculation::where('types', 'expense')
        // ->where('date', '>', '2024-04-09')
        // ->where('category_id', '<>', 6)
        // ->sum('amount');
        // $handCashes_Peti_balence = $handCashes_Peti_save - $handCashes_Peti_withdrow;
        // dd($handCashes_Peti_balence);

        $Bank_FD = HandCash::where('rules', 'FD')->where('types', 'SAVE')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $Bank_FD_withdraw = HandCash::where('rules', 'FD')->where('types', 'WIDROWS')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $Bank_FD_balence = $Bank_FD - $Bank_FD_withdraw;
        $Bank_DPS = HandCash::where('rules', 'DPS')->where('types', 'SAVE')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $Bank_DPS_withdraw = HandCash::where('rules', 'DPS')->where('types', 'WIDROWS')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $Bank_DPS_balence = $Bank_DPS - $Bank_DPS_withdraw;
        $Bank_Islamic_DPS = HandCash::where('rules', 'ISLAMIC_DPS')->where('types', 'SAVE')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $Bank_Islamic_DPS_withdraw = HandCash::where('rules', 'ISLAMIC_DPS')->where('types', 'WIDROWS')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $Bank_Islamic_DPS_balence = $Bank_Islamic_DPS - $Bank_Islamic_DPS_withdraw;


        $cash_cash_save = HandCash::where('rules', 'CASH')->where('types', 'SAVE')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->get();
        $cash_cash_withdraw = HandCash::where('rules', 'CASH')->where('types', 'WIDROWS')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->get();
        $loan_cash_save = HandCash::where('rules', 'LOAN')->where('types', 'SAVE')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->get();
        $loan_cash_withdraw = HandCash::where('rules', 'LOAN')->where('types', 'WIDROWS')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->get();

        // $mobileCash = 

        // Calculate the total amounts for Mobile
        $handCashes_Mobile_balence = 0;
        // if (!$mobile_cash_save->isEmpty()) {
        //     $handCashes_Mobile_balence += $mobile_cash_save->sum('amount');
        // }
        // if (!$mobile_cash_withdraw->isEmpty()) {
        //     $handCashes_Mobile_balence -= $mobile_cash_withdraw->sum('amount');
        // }

        $handCashes_Mobile_balence = $mobile_cash_save->sum('total') - $mobile_cash_withdraw->sum('total');

        // Calculate the total amounts for Bank
        $handCashes_Bank_balence = 0;
        // if (!$bank_cash_save->isEmpty()) {
        //     $handCashes_Bank_balence += $bank_cash_save->sum('amount');
        // }
        // if (!$bank_cash_withdraw->isEmpty()) {
        //     $handCashes_Bank_balence -= $bank_cash_withdraw->sum('amount');
        // }

        $handCashes_Bank_balence = $bank_cash_save->sum('total') - $bank_cash_withdraw->sum('total');

        // Calculate the total amounts for Cash
        $handCashes_Cash_balence = 0;
        if (!$cash_cash_save->isEmpty()) {
            $handCashes_Cash_balence += $cash_cash_save->sum('amount');
        }
        if (!$cash_cash_withdraw->isEmpty()) {
            $handCashes_Cash_balence -= $cash_cash_withdraw->sum('amount');
        }

        // Calculate the total amounts for loan
        $handCashes_loan_balence = 0;
        if (!$loan_cash_save->isEmpty()) {
            $handCashes_loan_balence += $loan_cash_save->sum('amount');
        }
        if (!$loan_cash_withdraw->isEmpty()) {
            $handCashes_loan_balence -= $loan_cash_withdraw->sum('amount');
        }

        $CreditCard_Credit = HandCash::where('rules', 'CREDITCARD')->where('types', 'SAVE')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $CreditCard_withdraw = HandCash::where('rules', 'CREDITCARD')->where('types', 'WIDROWS')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');

        $CreditCard_balance = $CreditCard_Credit - $CreditCard_withdraw;

        $MyLoan_pay = HandCash::where('rules', 'MYLOAN')->where('types', 'SAVE')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $MyLoan_borrow = HandCash::where('rules', 'MYLOAN')->where('types', 'WIDROWS')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $MyLoan_balance = $MyLoan_pay - $MyLoan_borrow;

        // DPSLoan
        $DPSLoan_pay = HandCash::where('rules', 'DPSLOAN')->where('types', 'SAVE')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $DPSLoan_borrow = HandCash::where('rules', 'DPSLOAN')->where('types', 'WIDROWS')
            ->when($balanceDateStart && $balanceDateEnd, function ($query) use ($balanceDateStart, $balanceDateEnd) {
                return $query->whereBetween('date', [$balanceDateStart, $balanceDateEnd]);
            })
            ->sum('amount');
        $DPSLoan_balance = $DPSLoan_pay - $DPSLoan_borrow;



        // Calculate the total HandCashes
        // $hands = $handCashes_Mobile_balence + $handCashes_Bank_balence + $handCashes_Cash_balence + $handCashes_loan_balence + $CreditCard_balance;
        $total = $handCashes_Mobile_balence + $handCashes_Bank_balence + $handCashes_Cash_balence  + $CreditCard_balance +  $handCashes_Peti_balence;

        //Calculate the total amount without loan, CreditCard, Peti, MyLoan and DPS
        $hands  = $total + $DPSLoan_balance + $MyLoan_balance + $handCashes_loan_balence;

        // Calculate the total amounts without loan, CreditCard and Peti, DPS, Bank FD, cash 



        // Balance summary (the account-balance tables at the top of the page) as Excel.
        if ($format === 'balance_xlsx') {
            $summaryView = view('backend.library.handCashes.balance_export', compact(
                'mobile_cash', 'handCashes_Mobile_balence', 'CreditCard_Credit', 'CreditCard_withdraw', 'CreditCard_balance',
                'handCashes_loan_balence', 'MyLoan_pay', 'MyLoan_borrow', 'MyLoan_balance', 'DPSLoan_pay', 'DPSLoan_borrow',
                'DPSLoan_balance', 'bank_cash', 'handCashes_Bank_balence', 'handCashes_Cash_balence', 'handCashes_Peti_balence',
                'hands', 'total', 'balanceDateStart', 'balanceDateEnd'
            ));

            return \Maatwebsite\Excel\Facades\Excel::download(
                new class($summaryView) implements \Maatwebsite\Excel\Concerns\FromView, \Maatwebsite\Excel\Concerns\ShouldAutoSize {
                    public function __construct(private \Illuminate\Contracts\View\View $summaryView) {}

                    public function view(): \Illuminate\Contracts\View\View
                    {
                        return $this->summaryView;
                    }
                },
                'balance-summary-' . now()->format('Y-m-d') . '.xlsx'
            );
        }

        // Pass the calculated data to the view
        return view('backend.library.handCashes.index', compact('mobile_cash_save', 'mobile_cash_withdraw', 'bank_cash_save', 'cash_cash_save', 'cash_cash_withdraw', 'bank_cash_withdraw', 'handCashes', 'hands', 'handCashes_Mobile_balence', 'handCashes_Bank_balence', 'handCashes_Cash_balence', 'handCashes_loan_balence', 'loan_cash_save', 'loan_cash_withdraw', 'mobile_cash', 'bank_cash', 'CreditCard_Credit', 'CreditCard_withdraw', 'CreditCard_balance', 'Bank_FD', 'Bank_FD_withdraw', 'Bank_FD_balence', 'Bank_DPS', 'Bank_DPS_withdraw', 'Bank_DPS_balence',  'handCashes_Peti_balence', 'handCashes_Peti_save', 'handCashes_Peti_withdraw', 'total', 'MyLoan_pay', 'MyLoan_borrow', 'MyLoan_balance', 'DPSLoan_pay', 'DPSLoan_borrow', 'DPSLoan_balance', 'Bank_Islamic_DPS', 'Bank_Islamic_DPS_withdraw', 'Bank_Islamic_DPS_balence', 'minDataDate'));
    }




    public function create()
    {

        $handCashes = HandCash::all();

        return view('backend.library.handCashes.create', compact('handCashes'));
    }


    public function store(HandCashRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'types.*' => 'nullable',
            'name.*' => 'nullable',
            'date.*' => 'nullable|date',
            'amount.*' => 'nullable',

        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if (
            $request->input('types') === null ||
            $request->input('name') === null ||
            $request->input('date') === null ||
            $request->input('amount') === null
        ) {
            return $this->redirectToIndex('handCashes.index')->withErrors('All fields are null, Please fill up at least one field');
        }

        // Iterate through each set of input fields
        $affectedYears = [];
        $affectedMonths = [];
        for ($i = 0; $i < count($request->input('types')); $i++) {
            // Create a new cash record
            $cash = new HandCash();

            // Set the values for each field
            $cash->rules = isset($request->input('rules')[$i]) ? strtoupper($request->input('rules')[$i]) : null;
            $cash->types = isset($request->input('types')[$i]) ? strtoupper($request->input('types')[$i]) : null;
            $cash->name = strtoupper($request->input('name')[$i]);
            $cash->date = $request->input('date')[$i];
            $cash->amount = $request->input('amount')[$i];


            // Save the cash record
            $cash->save();

            // track affected periods for cache invalidation
            if (!empty($cash->date)) {
                $affectedYears[date('Y', strtotime($cash->date))] = true;
                $affectedMonths[date('Y', strtotime($cash->date)) . ':' . date('m', strtotime($cash->date))] = true;
            }
        }

        // Clear relevant dashboard caches
        try {
            Cache::forget('dashboard:monthly_trend:last12');
            \App\Services\FinancialAnalyticsService::forgetAll();
            \App\Services\FinancialAnalysisService::forgetAll();
            foreach (array_keys($affectedYears) as $y) {
                Cache::forget("dashboard:category_breakdown:{$y}:all");
                Cache::forget("dashboard:top_categories:{$y}");
            }
            foreach (array_keys($affectedMonths) as $ym) {
                [$y, $m] = explode(':', $ym);
                Cache::forget("dashboard:summary:{$y}:{$m}");
                Cache::forget("dashboard:category_breakdown:{$y}:{$m}");
            }
        } catch (\Exception $e) {
        }

        return $this->redirectToIndex('handCashes.index')->withMessage('HandCash and related data are added successfully!');
    }

    public function handCashes_transfer_create()
    {
        return view('backend.library.handCashes.transfer');
    }

    public function handCashes_transfer(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'name' => 'nullable|string|max:255',
            'types1' => 'required|string|max:255',
            'rules1' => 'required|string|max:255',
            'types2' => 'required|string|max:255',
            'rules2' => 'required|string|max:255',
        ]);

        // Both halves or neither: a lone "money out" row would make money vanish from the books.
        DB::transaction(function () use ($request) {
            // Create a new cash record
            $cash1 = new HandCash();

            // Set the values for each field
            $cash1->rules = $request->input('rules1') ? strtoupper($request->input('rules1')) : null;
            $cash1->types = $request->input('types1') ? strtoupper($request->input('types1')) : null;
            $cash1->name = strtoupper($request->input('name'));
            $cash1->date = $request->input('date');
            $cash1->amount = $request->input('amount'); // amount

            // Save the cash record
            $cash1->save();

            // Create a new cash record
            $cash2 = new HandCash();

            // Set the values for each field
            $cash2->rules = strtoupper($request->input('rules2'));
            $cash2->types = strtoupper($request->input('types2'));
            $cash2->name = strtoupper($request->input('name'));
            $cash2->date = $request->input('date');
            $cash2->amount = $request->input('amount'); // amount

            // Save the cash record
            $cash2->save();

            // Link the two halves so editing one keeps the other in step.
            HandCash::whereKey($cash1->id)->update(['transfer_pair_id' => $cash2->id]);
            HandCash::whereKey($cash2->id)->update(['transfer_pair_id' => $cash1->id]);
        });



        return $this->redirectToIndex('handCashes.index')->withMessage('HandCash and related data are added successfully!');
    }





    public function show($id)
    {
        $handCashes = HandCash::findOrFail($id);

        return view('backend.library.handCashes.show', compact('handCashes'));
    }


    public function edit($id)
    {
        $handCashes = HandCash::findOrFail($id);

        return view('backend.library.handCashes.edit', compact('handCashes'));
    }


    public function update(HandCashRequest $request, $id)
    {
        $handCashes = HandCash::findOrFail($id);

        $handCashes->rules = strtoupper($request->input('rules'));
        $handCashes->types = strtoupper($request->input('types'));
        $handCashes->name = strtoupper($request->input('name'));
        $handCashes->date = $request->input('date');
        $handCashes->amount = $request->input('amount');

        $handCashes->save();

        // Clear cache for this record's period
        try {
            if ($handCashes->date) {
                $y = date('Y', strtotime($handCashes->date));
                $m = date('m', strtotime($handCashes->date));
                Cache::forget('dashboard:monthly_trend:last12');
            \App\Services\FinancialAnalyticsService::forgetAll();
            \App\Services\FinancialAnalysisService::forgetAll();
                Cache::forget("dashboard:summary:{$y}:{$m}");
                Cache::forget("dashboard:category_breakdown:{$y}:all");
                Cache::forget("dashboard:category_breakdown:{$y}:{$m}");
            }
        } catch (\Exception $e) {
        }

        // Redirect
        return $this->redirectToIndex('handCashes.index')->withMessage('HandCash and related data are updated successfully!');
    }


    public function destroy($id)
    {
        $handCashes = HandCash::findOrFail($id);
        $date = $handCashes->date;
        $handCashes->delete();

        try {
            if ($date) {
                $y = date('Y', strtotime($date));
                $m = date('m', strtotime($date));
                Cache::forget('dashboard:monthly_trend:last12');
            \App\Services\FinancialAnalyticsService::forgetAll();
            \App\Services\FinancialAnalysisService::forgetAll();
                Cache::forget("dashboard:summary:{$y}:{$m}");
                Cache::forget("dashboard:category_breakdown:{$y}:all");
                Cache::forget("dashboard:category_breakdown:{$y}:{$m}");
            }
        } catch (\Exception $e) {
        }

        return $this->redirectToIndex('handCashes.index')->withMessage('HandCash and related data are deleted successfully!');
    }
}
