<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;

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

        $mobileRules = ['Mobile_Bkash', 'Mobile_Rocket', 'Mobile_Nagad'];
        $bankRules = ['City_Bank', 'Sonali_Bank_Gulshan', 'Sonali_Bank_Tongi', 'DBBL', 'PBL', 'FD', 'DPS'];

        $mobile_cash_save = HandCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $mobileRules)
            ->where('types', 'Save')
            ->groupBy('rules', 'types')
            ->get();


        $mobile_cash_withdraw = HandCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $mobileRules)
            ->where('types', 'Widrows')
            ->groupBy('rules', 'types')
            ->get();



        $bank_cash_save = HandCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $bankRules)
            ->where('types', 'Save')
            ->groupBy('rules', 'types')
            ->get();

        $bank_cash_withdraw = HandCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $bankRules)
            ->where('types', 'Widrows')
            ->groupBy('rules', 'types')
            ->get();

        //find total balance from save - widrows amount according to rules and types

        $mobile_cash = HandCash::query()
            ->select([
                'rules',
                DB::raw('SUM(CASE WHEN types = "Save" THEN amount ELSE 0 END) - SUM(CASE WHEN types = "Widrows" THEN amount ELSE 0 END) as Balance'),
            ])
            ->whereIn('rules', $mobileRules)
            ->groupBy('rules') // Remove 'types' from the GROUP BY clause
            ->get();




        $bank_cash = HandCash::query()
            ->select([
                'rules',
                DB::raw('SUM(CASE WHEN types = "Save" THEN amount ELSE 0 END) - SUM(CASE WHEN types = "Widrows" THEN amount ELSE 0 END) as Balance'),
            ])
            ->whereIn('rules', $bankRules)
            ->groupBy('rules')
            ->get();
        //  dd($mobile_cash, $bank_cash);

        // $mobile_cash_save = HandCash::where('rules', 'Mobile')->where('types', 'Save')->get();
        // $mobile_cash_withdraw = HandCash::where('rules', 'Mobile')->where('types', 'Widrows')->get();
        // $bank_cash_save = HandCash::where('rules', 'Bank')->where('types', 'Save')->get();
        // $bank_cash_withdraw = HandCash::where('rules', 'Bank')->where('types', 'Widrows')->get();



        // $handCashes_Peti_withdrow = 

        // // dd($handCashes_Peti_withdrow);
        $handCashes_Peti_save = HandCash::where('rules', 'Peti')->where('types', 'Save')->sum('amount');
        $handCashes_Peti_withdraw = HandCash::where('rules', 'Peti')->where('types', 'Widrows')->sum('amount');
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

        $Bank_FD = HandCash::where('rules', 'FD')->where('types', 'Save')->sum('amount');
        $Bank_FD_withdraw = HandCash::where('rules', 'FD')->where('types', 'Widrows')->sum('amount');
        $Bank_FD_balence = $Bank_FD - $Bank_FD_withdraw;
        $Bank_DPS = HandCash::where('rules', 'DPS')->where('types', 'Save')->sum('amount');
        $Bank_DPS_withdraw = HandCash::where('rules', 'DPS')->where('types', 'Widrows')->sum('amount');
        $Bank_DPS_balence = $Bank_DPS - $Bank_DPS_withdraw;


        $cash_cash_save = HandCash::where('rules', 'Cash')->where('types', 'Save')->get();
        $cash_cash_withdraw = HandCash::where('rules', 'Cash')->where('types', 'Widrows')->get();
        $loan_cash_save = HandCash::where('rules', 'loan')->where('types', 'Save')->get();
        $loan_cash_withdraw = HandCash::where('rules', 'loan')->where('types', 'Widrows')->get();

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

        $CreditCard_Credit = HandCash::where('rules', 'CreditCard')->where('types', 'Save')->sum('amount');
        $CreditCard_withdraw = HandCash::where('rules', 'CreditCard')->where('types', 'Widrows')->sum('amount');

        $CreditCard_balance = $CreditCard_Credit - $CreditCard_withdraw;

        $MyLoan_pay = HandCash::where('rules', 'MyLoan')->where('types', 'Save')->sum('amount');
        $MyLoan_borrow = HandCash::where('rules', 'MyLoan')->where('types', 'Widrows')->sum('amount');
        $MyLoan_balance = $MyLoan_pay - $MyLoan_borrow;

        // Calculate the total HandCashes
        $hands = $handCashes_Mobile_balence + $handCashes_Bank_balence + $handCashes_Cash_balence + $handCashes_loan_balence + $CreditCard_balance;

        //Calculate the total amount without loan, CreditCard, Peti, MyLoan and DPS
        $total = $hands- $handCashes_Peti_balence - $Bank_DPS_balence- $CreditCard_balance- $handCashes_loan_balence - $MyLoan_balance;

        // Calculate the total amounts without loan, CreditCard and Peti, DPS, Bank FD, cash 



        // Pass the calculated data to the view
        return view('backend.library.handCashes.index', compact('mobile_cash_save', 'mobile_cash_withdraw', 'bank_cash_save', 'cash_cash_save', 'cash_cash_withdraw', 'bank_cash_withdraw', 'handCashes', 'hands', 'handCashes_Mobile_balence', 'handCashes_Bank_balence', 'handCashes_Cash_balence', 'handCashes_loan_balence', 'loan_cash_save', 'loan_cash_withdraw', 'mobile_cash', 'bank_cash', 'CreditCard_Credit', 'CreditCard_withdraw', 'CreditCard_balance', 'Bank_FD', 'Bank_FD_withdraw', 'Bank_FD_balence', 'Bank_DPS', 'Bank_DPS_withdraw', 'Bank_DPS_balence',  'handCashes_Peti_balence', 'handCashes_Peti_save', 'handCashes_Peti_withdraw', 'total', 'MyLoan_pay', 'MyLoan_borrow', 'MyLoan_balance'));
    }




    public function create()
    {

        $handCashes = HandCash::all();

        return view('backend.library.handCashes.create', compact('handCashes'));
    }


    public function store(Request $request)
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
        for ($i = 0; $i < count($request->input('types')); $i++) {
            // Create a new cash record
            $cash = new HandCash();

            // Set the values for each field
            $cash->rules = $request->input('rules')[$i];
            $cash->types = $request->input('types')[$i];
            $cash->name = $request->input('name')[$i];
            $cash->date = $request->input('date')[$i];
            $cash->amount = $request->input('amount')[$i];


            // Save the cash record
            $cash->save();
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
        $cash1->rules = $request->input('rules1');
        $cash1->types = $request->input('types1');
        $cash1->name = $request->input('name');
        $cash1->date = $request->input('date');
        $cash1->amount = $request->input('amount'); // amount

        // Save the cash record
        $cash1->save();

        // Create a new cash record
        $cash2 = new HandCash();

        // Set the values for each field
        $cash2->rules = $request->input('rules2');
        $cash2->types = $request->input('types2');
        $cash2->name = $request->input('name');
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


    public function update(Request $request, $id)
    {
        $handCashes = HandCash::findOrFail($id);

        $handCashes->rules = $request->input('rules');
        $handCashes->types = $request->input('types');
        $handCashes->name = $request->input('name');
        $handCashes->date = $request->input('date');
        $handCashes->amount = $request->input('amount');

        $handCashes->save();

        // Redirect
        return redirect()->route('handCashes.index')->withMessages('HandCash and related data are updated successfully!');
    }


    public function destroy($id)
    {
        $handCashes = HandCash::findOrFail($id);

        $handCashes->delete();


        return redirect()->route('handCashes.index')->withMessage('HandCash and related data are deleted successfully!');
    }

    public function Budge_Projection()
    {

        $handCashes = HandCash::all();

        return view('backend.reports.projection_report', compact('handCashes'));
    }

    public function Yearly_report()
    {

        // $currentYear = date('Y');

        $monthlyData = [];

        for ($month = 1; $month <= 12; $month++) {
            $thisMonthIncome = ExpenseCalculation::where('types', 'income')
                ->whereMonth('date', $month)
                // ->whereYear('date', $currentYear)
                ->get();

            $thisMonthExpense = ExpenseCalculation::where('types', 'expense')
                ->whereMonth('date', $month)
                // ->whereYear('date', $currentYear)
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as totalExpense'))
                ->get();

            $thisMonthneeds = ExpenseCalculation::where('rules', 'needs')
                ->whereMonth('date', $month)
                // ->whereYear('date', $currentYear)
                ->sum('amount');

            $thisMonthwants = ExpenseCalculation::where('rules', 'wants')
                ->whereMonth('date', $month)
                // ->whereYear('date', $currentYear)
                ->sum('amount');

            $thisMonthsavings = ExpenseCalculation::where('rules', 'savings')
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
            ->where('types', 'income')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('amount', 'desc')
            ->get();

        // Expenses grouped by category (sorted by total amount descending)
        $thisMonthExpense = ExpenseCalculation::with('category')
            ->where('types', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as totalExpense'))
            ->orderBy('totalExpense', 'desc')
            ->get();

        // Needs/Wants/Savings totals
        $thisMonthneeds = ExpenseCalculation::where('rules', 'needs')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $thisMonthwants = ExpenseCalculation::where('rules', 'wants')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $thisMonthsavings = ExpenseCalculation::where('rules', 'savings')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        // Yearly income (individual transactions sorted by amount descending)
        $thisYearIncome = ExpenseCalculation::with('category')
            ->where('types', 'income')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('amount', 'desc')
            ->get();

        // Yearly income grouped by category (sorted by total amount descending)
        $thisYearIncomecategory = ExpenseCalculation::with('category')
            ->where('types', 'income')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as totalIncomeYear'))
            ->orderBy('totalIncomeYear', 'desc')
            ->get();

        // Yearly expenses grouped by category (sorted by total amount descending)
        $thisYearExpense = ExpenseCalculation::with('category')
            ->where('types', 'expense')
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
            ->where('types', 'expense')
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
                ->where('types', 'income')->where('category_id', 1)
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
   
}
