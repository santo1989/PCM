<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use App\Models\ProjectedExpense;
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

        $handCashes = HandCash::latest();
        $search_cashes = null; // Initialize the variable

        // Check if the types field is selected
        if (request('types')) {
            $handCashes = $handCashes->where('types', request('types'));
            $search_cashes = $handCashes->get();
            session(['search_cashes' => $search_cashes]);
        }

        // Check if the types field is selected
        if (request('types')) {
            $handCashes = $handCashes->where('types', request('types'));
            $search_cashes = $handCashes->get();
            session(['search_cashes' => $search_cashes]);
        }

        // Check if the entry_date fields are filled
        if (request('entry_date_start') && request('entry_date_end')) {
            $handCashes = $handCashes->whereBetween('date', [
                request('entry_date_start'),
                request('entry_date_end')
            ]);
            $search_cashes = $handCashes->get();
            session(['search_cashes' => $search_cashes]);
        }

        $handCashes = $handCashes->get();

        // Check if export format is requested
        $format = strtolower(request('export_format'));

        if ($format === 'xlsx') {
            // Store the necessary values in the session
            session(['export_format' => $format]);

            // Retrieve the values from the session
            $format = session('export_format');
            $search_cashes = session('search_cashes');

            if ($search_cashes == null) {
                return redirect()->route('handCashes.index')->withErrors('First search the data then export');
            } else {
                $data = compact('search_cashes');
                // Generate the view content based on the requested format
                $viewContent = View::make('backend.library.handCashes.export', $data)->render();

                // Set appropriate headers for the file download
                $filename = Auth::user()->name . '_' . Carbon::now()->format('Y_m_d') . '_' . time() . '.xls';
                $headers = [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Content-Transfer-Encoding' => 'binary',
                    'Cache-Control' => 'must-revalidate',
                    'Pragma' => 'public',
                    'Content-Length' => strlen($viewContent)
                ];

                // Use the "binary" option in response to ensure the file is downloaded correctly
                return response()->make($viewContent, 200, $headers);
            }
        }

        // Perform the database queries and retrieve the data
        // Date filtering for balance calculations
        $balanceDateStart = request('balance_date_start');
        $balanceDateEnd = request('balance_date_end');

        $mobileRules = ['Mobile_Bkash', 'Mobile_Rocket', 'Mobile_Nagad'];
        $bankRules = ['City_Bank', 'City_Bank_Islamic', 'Sonali_Bank_Gulshan', 'Sonali_Bank_Tongi', 'DBBL', 'PBL', 'FD', 'DPS', 'Islamic_DPS', 'investment'];

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



        // Pass the calculated data to the view
        return view('backend.library.handCashes.index', compact('mobile_cash_save', 'mobile_cash_withdraw', 'bank_cash_save', 'cash_cash_save', 'cash_cash_withdraw', 'bank_cash_withdraw', 'handCashes', 'hands', 'handCashes_Mobile_balence', 'handCashes_Bank_balence', 'handCashes_Cash_balence', 'handCashes_loan_balence', 'loan_cash_save', 'loan_cash_withdraw', 'mobile_cash', 'bank_cash', 'CreditCard_Credit', 'CreditCard_withdraw', 'CreditCard_balance', 'Bank_FD', 'Bank_FD_withdraw', 'Bank_FD_balence', 'Bank_DPS', 'Bank_DPS_withdraw', 'Bank_DPS_balence',  'handCashes_Peti_balence', 'handCashes_Peti_save', 'handCashes_Peti_withdraw', 'total', 'MyLoan_pay', 'MyLoan_borrow', 'MyLoan_balance', 'DPSLoan_pay', 'DPSLoan_borrow', 'DPSLoan_balance', 'Bank_Islamic_DPS', 'Bank_Islamic_DPS_withdraw', 'Bank_Islamic_DPS_balence'));
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
            return redirect()->route('handCashes.index')->withErrors('All fields are null, Please fill up at least one field');
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

        return redirect()->route('handCashes.index')->withMessages('HandCash and related data are added successfully!');
    }

    public function handCashes_transfer_create()
    {
        return view('backend.library.handCashes.transfer');
    }

    public function handCashes_transfer(Request $request)
    {
        // dd($request->all());

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



        return redirect()->route('handCashes.index')->withMessages('HandCash and related data are added successfully!');
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
                Cache::forget("dashboard:summary:{$y}:{$m}");
                Cache::forget("dashboard:category_breakdown:{$y}:all");
                Cache::forget("dashboard:category_breakdown:{$y}:{$m}");
            }
        } catch (\Exception $e) {
        }

        // Redirect
        return redirect()->route('handCashes.index')->withMessages('HandCash and related data are updated successfully!');
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
                Cache::forget("dashboard:summary:{$y}:{$m}");
                Cache::forget("dashboard:category_breakdown:{$y}:all");
                Cache::forget("dashboard:category_breakdown:{$y}:{$m}");
            }
        } catch (\Exception $e) {
        }

        return redirect()->route('handCashes.index')->withMessage('HandCash and related data are deleted successfully!');
    }



    public function Yearly_report()
    {

        // $currentYear = date('Y');

        $monthlyData = [];

        for ($month = 1; $month <= 12; $month++) {
            $thisMonthIncome = ExpenseCalculation::where('types', 'INCOME')
                ->whereMonth('date', $month)
                // ->whereYear('date', $currentYear)
                ->get();

            $thisMonthExpense = ExpenseCalculation::where('types', 'EXPENSE')
                ->whereMonth('date', $month)
                // ->whereYear('date', $currentYear)
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as totalExpense'))
                ->get();

            $thisMonthneeds = ExpenseCalculation::where('rules', 'NEEDS')
                ->whereMonth('date', $month)
                // ->whereYear('date', $currentYear)
                ->sum('amount');

            $thisMonthwants = ExpenseCalculation::where('rules', 'WANTS')
                ->whereMonth('date', $month)
                // ->whereYear('date', $currentYear)
                ->sum('amount');

            $thisMonthsavings = ExpenseCalculation::where('rules', 'SAVINGS')
                ->whereMonth('date', $month)
                // ->whereYear('date', $currentYear)
                ->sum('amount');

            $monthlyData[$month] = [
                'income' => $thisMonthIncome->sum('amount'),
                'needs' => $thisMonthIncome->sum('amount') * 0.5,
                'wants' => $thisMonthIncome->sum('amount') * 0.3,
                'savings' => $thisMonthIncome->sum('amount') * 0.2,
                'expense' => $thisMonthExpense->sum('totalExpense'),
                'thisMonthneeds' => $thisMonthneeds,
                'thisMonthwants' => $thisMonthwants,
                'thisMonthsavings' => $thisMonthsavings,
            ];
        }

        return view('backend.reports.yearly_report', compact('monthlyData'));
    }



    public function Monthly_report()
    {
        // Get all hand cash records
        $handCashes = HandCash::all();

        // Handle date range inputs
        if (request('start_date') && request('end_date')) {
            $startDate = request('start_date');
            $endDate = request('end_date');
        } else {
            $startDate = now()->startOfMonth()->format('Y-m-d');
            $endDate = now()->endOfMonth()->format('Y-m-d');
        }

        $currentMonth = now()->format('m');
        $currentYear = now()->format('Y');

        // Income for the period (individual transactions sorted by amount descending)
        $thisMonthIncome = ExpenseCalculation::with('category')
            ->where('types', 'INCOME')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('amount', 'desc')
            ->get();

        // Expenses grouped by category (sorted by total amount descending)
        $thisMonthExpense = ExpenseCalculation::with('category')
            ->where('types', 'EXPENSE')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as totalExpense'))
            ->orderBy('totalExpense', 'desc')
            ->get();

        // Needs/Wants/Savings totals
        $thisMonthneeds = ExpenseCalculation::where('rules', 'NEEDS')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $thisMonthwants = ExpenseCalculation::where('rules', 'WANTS')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $thisMonthsavings = ExpenseCalculation::where('rules', 'SAVINGS')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        // Yearly income (individual transactions sorted by amount descending)
        $thisYearIncome = ExpenseCalculation::with('category')
            ->where('types', 'INCOME')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('amount', 'desc')
            ->get();

        // Yearly income grouped by category (sorted by total amount descending)
        $thisYearIncomecategory = ExpenseCalculation::with('category')
            ->where('types', 'INCOME')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as totalIncomeYear'))
            ->orderBy('totalIncomeYear', 'desc')
            ->get();

        // Yearly expenses grouped by category (sorted by total amount descending)
        $thisYearExpense = ExpenseCalculation::with('category')
            ->where('types', 'EXPENSE')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as totalExpenseYear'))
            ->orderBy('totalExpenseYear', 'desc')
            ->get();

        return view('backend.reports.monthly_report', compact(
            'handCashes',
            'thisMonthIncome',
            'thisMonthExpense',
            'thisMonthneeds',
            'thisMonthwants',
            'thisMonthsavings',
            'thisYearIncome',
            'thisYearExpense',
            'startDate',
            'endDate',
            'currentMonth',
            'currentYear',
            'thisYearIncomecategory'
        ));
    }



    public function power_bi_report()
    {
        return view('backend.reports.power_bi_report');
    }


    public function Monthly_invest()
    {
        // Date range handling
        // Get min and max dates from the database
        $minDate = ExpenseCalculation::min('date');
        $maxDate = ExpenseCalculation::max('date');

        // Handle start and end dates with proper parsing
        $startDate = request('start_date') ?? ($minDate ? Carbon::parse($minDate)->format('Y-m-d') : now()->format('Y-m-d'));
        $endDate = request('end_date') ?? ($maxDate ? Carbon::parse($maxDate)->format('Y-m-d') : now()->format('Y-m-d'));


        // Get income data with investment calculations
        // $incomes = ExpenseCalculation::with('category')
        //     ->where('types', 'income')
        $incomes = ExpenseCalculation::with('category')
            ->where('category_id', 1)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('amount', 'desc')
            ->get()
            ->map(function ($item) {
                $amount = (float)$item->amount;
                // Get employment duration in years
                $startYear = 2022; // Replace with actual user start year
                $currentYear = now()->year;
                $years = max($currentYear - $startYear, 0);

                // Calculate investment percentage with 10% yearly increase
                $baseInvestmentPercent = 0.3;
                $investmentPercent = min($baseInvestmentPercent * pow(1.1, $years), 0.8);

                $amount = (float)$item->amount;

                return [
                    'date' => $item->date,
                    'amount' => $amount,
                    'investment' => $amount * $investmentPercent,
                    'needs' => $amount * 0.5,
                    'wants' => $amount * 0.1,
                    'future' => $amount * 0.1,
                ];
            });

        // Expense calculations
        $expenses = ExpenseCalculation::with('category')
            ->where('types', 'EXPENSE')
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('rules');
        // Map expenses to include amounts and convert to float
        $expenses = $expenses->map(function ($group) {
            return $group->map(function ($item) {
                $item->amount = (float)$item->amount; // Convert model's amount
                return $item;
            });
        });

        //total expenses 
        $totalExpenses = $expenses->flatten()->sum('amount');

        // Investment growth calculation (NEW)
        $investmentGrowth = $this->calculateInvestmentGrowth($startDate, $endDate);
        // For expenses (add after grouping)
        $expenses = $expenses->map(function ($group) {
            return $group->map(function ($item) {
                $item->amount = (float)$item->amount; // Convert model's amount
                return $item;
            });
        });
        return view(
            'backend.reports.Monthly_invest',
            compact('incomes', 'expenses', 'startDate', 'endDate', 'investmentGrowth', 'totalExpenses')
        );
    }

    private function calculateInvestmentGrowth($startDate, $endDate)
    {
        // Calculate investment growth based on yearly income
        $startYear = Carbon::parse($startDate)->year ?? 2022;
        $endYear = Carbon::parse($endDate)->year ?? now()->year;

        // Get the current year
        $currentYear = now()->year;

        // Initialize an array to store growth data
        $growthData = [];

        // Loop through each year from start to current year
        for ($year = $startYear; $year <= $currentYear; $year++) {
            // Get the total income for the year
            $yearlyIncome = (float) ExpenseCalculation::whereYear('date', $year)
                ->where('types', 'INCOME')->where('category_id', 1)
                ->sum('amount');

            // Calculate investment percentage based on years active
            $yearsActive = $year - $startYear;
            $investmentPercent = min(0.3 * pow(1.1, $yearsActive), 0.8);

            // Store the growth data
            $growthData[] = [
                'year' => $year,
                'amount' => (float) $yearlyIncome * $investmentPercent
            ];
        }

        return [
            'years' => array_column($growthData, 'year'),
            'amounts' => array_map('floatval', array_column($growthData, 'amount'))
        ];
    }
    // public function Budge_Projection()
    // {
    //     // Retrieve all categories, excluding specified ones
    //     $categories = Category::all()->except([1, 19, 20, 23]);

    //     // Calculate this year's average expenses per category (excluding current month)
    //     $allYearExpenses = ExpenseCalculation::where('types', 'expense')
    //         // ->whereYear('date', Carbon::now()->year)
    //         // ->whereMonth('date', '!=', Carbon::now()->month)
    //         ->groupBy('category_id')
    //         ->select('category_id', DB::raw('sum(amount) as totalExpense'), DB::raw('count(distinct MONTH(date)) as totalMonths'))
    //         ->get();

    //     $thisYearExpense = [];
    //     foreach ($allYearExpenses as $expense) {
    //         $averageExpense = $expense->totalExpense / $expense->totalMonths;
    //         $thisYearExpense[] = [
    //             'category_id' => $expense->category_id,
    //             'averageExpense' => ceil($averageExpense),
    //         ];
    //     }

    //     // Get last month's expenses per category
    //     $lastMonth = date('m') == '01' ? '12' : str_pad(date('m') - 1, 2, '0', STR_PAD_LEFT);
    //     $lastYear = date('m') == '01' ? date('Y') - 1 : date('Y');

    //     $lastMonthExpense = ExpenseCalculation::whereYear('date', $lastYear)
    //         ->whereMonth('date', $lastMonth)
    //         ->where('types', 'EXPENSE')
    //         ->groupBy('category_id')
    //         ->select('category_id', DB::raw('SUM(amount) as totalExpense'))
    //         ->get()
    //         ->map(function ($expense) {
    //             $expense->totalExpense = ceil($expense->totalExpense);
    //             return $expense;
    //         });

    //     $totallastMonthExpense = $lastMonthExpense->sum('totalExpense');

    //     // Get total income for the current month
    //     $totalMonthlyIncome = ExpenseCalculation::where('category_id', 1)
    //         ->whereYear('date', now()->year)
    //         ->whereMonth('date', now()->month)
    //         ->sum('amount');

    //     // Calculate monthly actual limit according to finance rules:
    //     // MonthlyactualLimitExpense = (monthly salary - DPS - Islamic_DPS) * 70%
    //     // Use DPS and Islamic_DPS contributions for the current month (types = 'Save')
    //     $dpsThisMonth = HandCash::where('rules', 'DPS')
    //         ->where('types', 'SAVE')
    //         ->whereYear('date', now()->year)
    //         ->whereMonth('date', now()->month)
    //         ->sum('amount');

    //     $islamicDpsThisMonth = HandCash::where('rules', 'ISLAMIC_DPS')
    //         ->where('types', 'SAVE')
    //         ->whereYear('date', now()->year)
    //         ->whereMonth('date', now()->month)
    //         ->sum('amount');

    //     $MonthlyactualLimitExpense = max(0, ($totalMonthlyIncome - $dpsThisMonth - $islamicDpsThisMonth) * 0.7);

    //     // This is a new part of the code to get the projected expenses for this month
    //     $thisMonthProjectedExpenses = ProjectedExpense::whereYear('date', now()->year)
    //         ->whereMonth('date', now()->month)
    //         ->get()
    //         ->keyBy('category_id');

    //     return view('backend.reports.projection_report', compact(
    //         'categories',
    //         'thisYearExpense',
    //         'lastMonthExpense',
    //         'totallastMonthExpense',
    //         'MonthlyactualLimitExpense',
    //         'totalMonthlyIncome',
    //         'thisMonthProjectedExpenses'
    //     ));
    // }

    /**
     * Interactive dashboard view
     */

    public function Budge_Projection()
    {
        // Retrieve all categories, excluding specified ones
        $categories = Category::all()->except([1, 19, 20, 23]);

        // Calculate this year's average expenses per category (excluding current month)
        $allYearExpenses = ExpenseCalculation::where('types', 'expense')
            ->groupBy('category_id')
            ->select('category_id', DB::raw('sum(amount) as totalExpense'), DB::raw('count(distinct MONTH(date)) as totalMonths'))
            ->get();

        $thisYearExpense = [];
        foreach ($allYearExpenses as $expense) {
            $averageExpense = $expense->totalExpense / $expense->totalMonths;
            $thisYearExpense[] = [
                'category_id' => $expense->category_id,
                'averageExpense' => ceil($averageExpense),
            ];
        }

        // ========== NEW: Multi-Year Monthly Averages ==========
        // Get multi-year monthly averages for each category
        $multiYearMonthlyAverages = ExpenseCalculation::where('types', 'expense')
            ->whereIn('category_id', $categories->pluck('id')->toArray())
            ->groupBy('category_id', DB::raw('MONTH(date)'))
            ->select(
                'category_id',
                DB::raw('MONTH(date) as month'),
                DB::raw('AVG(amount) as avg_amount'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(DISTINCT YEAR(date)) as years_count'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->orderBy('category_id')
            ->orderBy('month')
            ->get();

        // Format multi-year monthly averages
        $formattedMultiYearAverages = [];
        foreach ($multiYearMonthlyAverages as $data) {
            $formattedMultiYearAverages[$data->category_id][$data->month] = [
                'month' => $data->month,
                'month_name' => Carbon::create()->month($data->month)->format('F'),
                'avg_amount' => ceil($data->avg_amount),
                'total_amount' => ceil($data->total_amount),
                'years_count' => $data->years_count,
                'transaction_count' => $data->transaction_count
            ];
        }

        // Get overall multi-year monthly averages (across all categories)
        $overallMultiYearMonthlyAverages = ExpenseCalculation::where('types', 'expense')
            ->whereIn('category_id', $categories->pluck('id')->toArray())
            ->groupBy(DB::raw('MONTH(date)'))
            ->select(
                DB::raw('MONTH(date) as month'),
                DB::raw('AVG(amount) as avg_amount'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(DISTINCT YEAR(date)) as years_count'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->orderBy('month')
            ->get();

        $formattedOverallMultiYearAverages = [];
        foreach ($overallMultiYearMonthlyAverages as $data) {
            $formattedOverallMultiYearAverages[$data->month] = [
                'month' => $data->month,
                'month_name' => Carbon::create()->month($data->month)->format('F'),
                'avg_amount' => ceil($data->avg_amount),
                'total_amount' => ceil($data->total_amount),
                'years_count' => $data->years_count,
                'transaction_count' => $data->transaction_count
            ];
        }

        // Get last month's expenses per category
        $lastMonth = date('m') == '01' ? '12' : str_pad(date('m') - 1, 2, '0', STR_PAD_LEFT);
        $lastYear = date('m') == '01' ? date('Y') - 1 : date('Y');

        $lastMonthExpense = ExpenseCalculation::whereYear('date', $lastYear)
            ->whereMonth('date', $lastMonth)
            ->where('types', 'EXPENSE')
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as totalExpense'))
            ->get()
            ->map(function ($expense) {
                $expense->totalExpense = ceil($expense->totalExpense);
                return $expense;
            });

        $totallastMonthExpense = $lastMonthExpense->sum('totalExpense');

        // Get total income for the current month
        $totalMonthlyIncome = ExpenseCalculation::where('category_id', 1)
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        // Calculate monthly actual limit according to finance rules
        $dpsThisMonth = HandCash::where('rules', 'DPS')
            ->where('types', 'SAVE')
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        $islamicDpsThisMonth = HandCash::where('rules', 'ISLAMIC_DPS')
            ->where('types', 'SAVE')
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        $MonthlyactualLimitExpense = max(0, ($totalMonthlyIncome - $dpsThisMonth - $islamicDpsThisMonth) * 0.7);

        // This is a new part of the code to get the projected expenses for this month
        $thisMonthProjectedExpenses = ProjectedExpense::whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->get()
            ->keyBy('category_id');

        return view('backend.reports.projection_report', compact(
            'categories',
            'thisYearExpense',
            'formattedMultiYearAverages',
            'formattedOverallMultiYearAverages',
            'lastMonthExpense',
            'totallastMonthExpense',
            'MonthlyactualLimitExpense',
            'totalMonthlyIncome',
            'thisMonthProjectedExpenses'
        ));
    }
    public function interactiveDashboard()
    {
        return view('backend.reports.interactive_dashboard');
    }

    /**
     * JSON: high-level summary (totals)
     */
    public function interactiveDashboardSummary(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        $cacheKey = "dashboard:summary:{$year}:{$month}";
        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($year, $month) {
            $totalIncome = ExpenseCalculation::where('types', 'income')
                ->whereYear('date', $year)
                ->sum('amount');

            $totalExpense = ExpenseCalculation::where('types', 'expense')
                ->whereYear('date', $year)
                ->sum('amount');

            // Current month totals
            $monthIncome = ExpenseCalculation::where('types', 'income')
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->sum('amount');

            $monthExpense = ExpenseCalculation::where('types', 'expense')
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->sum('amount');

            // Cash balances grouped by rules (hand cash)
            $cashBalances = HandCash::select('rules', DB::raw('SUM(CASE WHEN types = "Save" THEN amount ELSE 0 END) - SUM(CASE WHEN types = "Widrows" THEN amount ELSE 0 END) as balance'))
                ->groupBy('rules')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->rules => (float) $item->balance];
                });

            return [
                'totalIncome' => (float) $totalIncome,
                'totalExpense' => (float) $totalExpense,
                'monthIncome' => (float) $monthIncome,
                'monthExpense' => (float) $monthExpense,
                'cashBalances' => $cashBalances,
                'net' => (float) ($totalIncome - $totalExpense),
            ];
        });

        return response()->json(array_merge(['year' => (int)$year, 'month' => (int)$month], $data));
    }

    /**
     * JSON: monthly trend for the last 12 months
     */
    public function interactiveDashboardMonthlyTrend(Request $request)
    {
        $cacheKey = 'dashboard:monthly_trend:last12';
        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () {
            $end = now();
            $start = now()->subMonths(11);

            $months = [];
            $incomeSeries = [];
            $expenseSeries = [];

            for ($dt = $start->copy(); $dt->lte($end); $dt->addMonth()) {
                $m = $dt->month;
                $y = $dt->year;
                $label = $dt->format('Y-m');
                $months[] = $label;

                $incomeSeries[] = (float) ExpenseCalculation::where('types', 'income')
                    ->whereYear('date', $y)
                    ->whereMonth('date', $m)
                    ->sum('amount');

                $expenseSeries[] = (float) ExpenseCalculation::where('types', 'expense')
                    ->whereYear('date', $y)
                    ->whereMonth('date', $m)
                    ->sum('amount');
            }

            return ['months' => $months, 'income' => $incomeSeries, 'expense' => $expenseSeries];
        });

        return response()->json($payload);
    }

    /**
     * JSON: category breakdown for selected period
     */
    public function interactiveDashboardCategoryBreakdown(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', null);
        $cacheKey = "dashboard:category_breakdown:{$year}:" . ($month ?: 'all');
        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($year, $month) {
            $q = ExpenseCalculation::where('types', 'expense')
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as total'));

            if ($month) {
                $q->whereYear('date', $year)->whereMonth('date', $month);
            } else {
                $q->whereYear('date', $year);
            }

            $items = $q->orderBy('total', 'desc')->get();

            // eager load categories in bulk
            $categoryIds = $items->pluck('category_id')->unique()->filter()->values()->all();
            $categories = Category::whereIn('id', $categoryIds)->get()->keyBy('id');

            return $items->map(function ($item) use ($categories) {
                $category = $categories->get($item->category_id);
                return [
                    'category_id' => $item->category_id,
                    'category' => $category ? $category->name : 'Unknown',
                    'total' => (float) $item->total,
                ];
            })->values();
        });

        return response()->json($data);
    }

    /**
     * JSON: savings vs loans (hand cash rules) summary
     */
    public function interactiveDashboardSavingsLoans(Request $request)
    {
        // total savings and total loans across HandCash
        $savings = HandCash::where('types', 'SAVE')->sum('amount');
        $withdrawals = HandCash::where('types', 'WIDROWS')->sum('amount');

        // separate out Loan rules if present
        $loan_in = HandCash::where('rules', 'LOAN')->where('types', 'SAVE')->sum('amount');
        $loan_out = HandCash::where('rules', 'LOAN')->where('types', 'WIDROWS')->sum('amount');

        return response()->json([
            'savings_total' => (float) $savings,
            'withdrawals_total' => (float) $withdrawals,
            'loan_in' => (float) $loan_in,
            'loan_out' => (float) $loan_out,
        ]);
    }

    /**
     * JSON: top expense categories for year/month
     */
    public function interactiveDashboardTopCategories(Request $request)
    {
        $year = $request->get('year', now()->year);
        $limit = (int) $request->get('limit', 8);

        $items = ExpenseCalculation::where('types', 'expense')
            ->whereYear('date', $year)
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->orderBy('total', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $c = Category::find($item->category_id);
                return [
                    'category' => $c ? $c->name : 'Unknown',
                    'total' => (float) $item->total,
                ];
            });

        return response()->json($items);
    }

    /**
     * JSON: running balance (hand cash chronologically)
     */
    public function interactiveDashboardRunningBalance(Request $request)
    {
        // fetch last 200 handcash transactions ordered by date
        $rows = HandCash::orderBy('date', 'asc')->orderBy('id', 'asc')->limit(1000)->get(['date', 'name', 'rules', 'types', 'amount']);

        $balance = 0.0;
        $series = [];
        foreach ($rows as $r) {
            $amt = (float) $r->amount;
            if (strtolower($r->types) === 'save') $balance += $amt;
            else $balance -= $amt;
            $series[] = [
                'date' => $r->date,
                'name' => $r->name,
                'rules' => $r->rules,
                'types' => $r->types,
                'amount' => $amt,
                'balance' => round($balance, 2),
            ];
        }

        return response()->json($series);
    }

    /**
     * JSON: recent transactions (expense/income)
     */
    public function interactiveDashboardRecentTransactions(Request $request)
    {
        $limit = (int) $request->get('limit', 20);
        $rows = ExpenseCalculation::with('category')
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'date' => $r->date,
                    'name' => $r->name,
                    'category' => $r->category->name ?? null,
                    'types' => $r->types,
                    'amount' => (float) $r->amount,
                ];
            });

        return response()->json($rows);
    }

    public function calculateAndSaveBudget()
    {
        // 1. Get total income for the current month
        $totalMonthlyIncome = ExpenseCalculation::where('category_id', 1)
            ->whereYear('date', Carbon::now()->year)
            ->whereMonth('date', Carbon::now()->month)
            ->sum('amount');

        if ($totalMonthlyIncome <= 0) {
            return back()->with('error', 'No income data found for the current month. Cannot calculate budget.');
        }

        // 2. Calculate the total budget for expenses after a 10% saving
        $savingRate = 0.10;
        $totalBudgetForExpenses = $totalMonthlyIncome * (1 - $savingRate);

        // 3. Get total average yearly expenses for proportional allocation
        $allYearExpenses = ExpenseCalculation::where('types', 'expense')
            ->whereYear('date', Carbon::now()->year)
            ->whereMonth('date', '!=', Carbon::now()->month)
            ->groupBy('category_id')
            ->select('category_id', DB::raw('sum(amount) as totalExpense'))
            ->get();

        $totalAllYearExpense = $allYearExpenses->sum('totalExpense');

        // 4. Allocate budget to each category and prepare for saving.
        //    Apply a reduction target (default 10%) relative to last month's expense for each category
        $reductionPercent = floatval(request()->get('reduction_percent', 0.10));

        $projectedExpensesData = [];
        $nextMonth = Carbon::now()->addMonth();

        // Delete existing projected budget for the next month to avoid duplicates
        ProjectedExpense::whereYear('date', $nextMonth->year)
            ->whereMonth('date', $nextMonth->month)
            ->delete();

        // Last month's totals per category
        $prev = Carbon::now()->subMonth();
        $lastMonthRows = ExpenseCalculation::where('types', 'expense')
            ->whereYear('date', $prev->year)
            ->whereMonth('date', $prev->month)
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->get()
            ->keyBy('category_id')
            ->map(function ($r) {
                return (float) $r->total;
            })->toArray();

        // Build a base amount for each category using last month if available, otherwise use yearly avg
        $baseAmounts = [];
        foreach ($allYearExpenses as $expense) {
            $catId = $expense->category_id;
            $lastMonthAmt = $lastMonthRows[$catId] ?? null;
            if ($lastMonthAmt !== null && $lastMonthAmt > 0) {
                $base = $lastMonthAmt;
            } else {
                // fallback to average expense per active month
                $monthsCount = max($expense->totalMonths ?? 1, 1);
                $base = ($expense->totalExpense / $monthsCount) ?: 0;
            }
            $baseAmounts[$catId] = (float) $base;
        }

        // If there are categories from last month not in allYearExpenses, include them
        foreach ($lastMonthRows as $catId => $amt) {
            if (!array_key_exists($catId, $baseAmounts)) {
                $baseAmounts[$catId] = (float) $amt;
            }
        }

        // Initial projected per-category: try to reduce last-month/base by reductionPercent
        $initialProjected = [];
        $initialTotal = 0.0;
        foreach ($baseAmounts as $catId => $base) {
            $proj = max(0, $base * (1 - $reductionPercent));
            $initialProjected[$catId] = $proj;
            $initialTotal += $proj;
        }

        // If no data, fallback to proportional allocation like before
        if ($initialTotal <= 0.0) {
            foreach ($allYearExpenses as $expense) {
                $proportion = ($totalAllYearExpense > 0) ? ($expense->totalExpense / $totalAllYearExpense) : 0;
                $allocatedAmount = ceil($totalBudgetForExpenses * $proportion);

                $projectedExpensesData[] = [
                    'category_id' => $expense->category_id,
                    'date' => $nextMonth,
                    'amount' => $allocatedAmount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } else {
            // Scale initialProjected to fit total budget
            $scale = $totalBudgetForExpenses / $initialTotal;
            foreach ($initialProjected as $catId => $val) {
                $allocated = (int) ceil($val * $scale);
                $projectedExpensesData[] = [
                    'category_id' => $catId,
                    'date' => $nextMonth,
                    'amount' => $allocated,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // 5. Save the new projected budget to the database
        if (!empty($projectedExpensesData)) {
            ProjectedExpense::insert($projectedExpensesData);
        }

        return back()->with('success', 'Dynamic budget for next month has been calculated and saved successfully!');
    }
}
