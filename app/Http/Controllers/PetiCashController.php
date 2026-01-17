<?php

namespace App\Http\Controllers;

use App\Models\PetiCash;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;

class PetiCashController extends Controller
{
    public function index()
    {

        $petiCash = PetiCash::latest();
        $search_cashes = null; // Initialize the variable

        // Check if the types field is selected
        if (request('types')) {
            $petiCash = $petiCash->where('types', request('types'));
            $search_cashes = $petiCash->get();
            session(['search_cashes' => $search_cashes]);
        }

        // Check if the types field is selected
        if (request('types')) {
            $petiCash = $petiCash->where('types', request('types'));
            $search_cashes = $petiCash->get();
            session(['search_cashes' => $search_cashes]);
        }

        // Check if the entry_date fields are filled
        if (request('entry_date_start') && request('entry_date_end')) {
            $petiCash = $petiCash->whereBetween('date', [
                request('entry_date_start'),
                request('entry_date_end')
            ]);
            $search_cashes = $petiCash->get();
            session(['search_cashes' => $search_cashes]);
        }

        $petiCash = $petiCash->get();

        // Check if export format is requested
        $format = strtolower(request('export_format'));

        if ($format === 'xlsx') {
            // Store the necessary values in the session
            session(['export_format' => $format]);

            // Retrieve the values from the session
            $format = session('export_format');
            $search_cashes = session('search_cashes');

            if ($search_cashes == null) {
                return redirect()->route('petiCash.index')->withErrors('First search the data then export');
            } else {
                $data = compact('search_cashes');
                // Generate the view content based on the requested format
                $viewContent = View::make('backend.library.petiCash.export', $data)->render();

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
        $bankRules = ['City_Bank', 'Sonali_Bank_Gulshan', 'Sonali_Bank_Tongi', 'DBBL', 'PBL'];

        $mobile_cash_save = PetiCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $mobileRules)
            ->where('types', 'SAVE')
            ->groupBy('rules', 'types')
            ->get();


        $mobile_cash_withdraw = PetiCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $mobileRules)
            ->where('types', 'WIDROWS')
            ->groupBy('rules', 'types')
            ->get();



        $bank_cash_save = PetiCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $bankRules)
            ->where('types', 'SAVE')
            ->groupBy('rules', 'types')
            ->get();

        $bank_cash_withdraw = PetiCash::query()
            ->select(['rules', 'types', DB::raw('SUM(amount) as total')])
            ->whereIn('rules', $bankRules)
            ->where('types', 'WIDROWS')
            ->groupBy('rules', 'types')
            ->get();

        //find total balance from save - widrows amount according to rules and types

        $mobile_cash = PetiCash::query()
            ->select([
                'rules',
                DB::raw('SUM(CASE WHEN types = "SAVE" THEN amount ELSE 0 END) - SUM(CASE WHEN types = "WIDROWS" THEN amount ELSE 0 END) as Balance'),
            ])
            ->whereIn('rules', $mobileRules)
            ->groupBy('rules') // Remove 'types' from the GROUP BY clause
            ->get();




        $bank_cash = PetiCash::query()
            ->select([
                'rules',
                DB::raw('SUM(CASE WHEN types = "SAVE" THEN amount ELSE 0 END) - SUM(CASE WHEN types = "WIDROWS" THEN amount ELSE 0 END) as Balance'),
            ])
            ->whereIn('rules', $bankRules)
            ->groupBy('rules')
            ->get();
        //  dd($mobile_cash, $bank_cash);

        // $mobile_cash_save = PetiCash::where('rules', 'Mobile')->where('types', 'Save')->get();
        // $mobile_cash_withdraw = PetiCash::where('rules', 'Mobile')->where('types', 'Widrows')->get();
        // $bank_cash_save = PetiCash::where('rules', 'Bank')->where('types', 'Save')->get();
        // $bank_cash_withdraw = PetiCash::where('rules', 'Bank')->where('types', 'Widrows')->get();



        // $handCashes_Peti_withdrow = 

        // dd($handCashes_Peti_withdrow);
        $handCashes_Peti_save = PetiCash::where('rules', 'PETI')->where('types', 'SAVE')->sum('amount');
        $handCashes_Peti_withdrow = PetiCash::where('rules', 'PETI')->where('types', 'WIDROWS')->sum('amount');
        $handCashes_Peti_balence = $handCashes_Peti_save - $handCashes_Peti_withdrow;

        $cash_cash_save = PetiCash::where('rules', 'CASH')->where('types', 'SAVE')->get();
        $cash_cash_withdraw = PetiCash::where('rules', 'CASH')->where('types', 'WIDROWS')->get();
        $loan_cash_save = PetiCash::where('rules', 'LOAN')->where('types', 'SAVE')->get();
        $loan_cash_withdraw = PetiCash::where('rules', 'LOAN')->where('types', 'WIDROWS')->get();

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

        // Calculate the total HandCashes
        $hands = $handCashes_Mobile_balence + $handCashes_Bank_balence + $handCashes_Cash_balence;

        $CreditCard_Credit = PetiCash::where('rules', 'CREDITCARD')->where('types', 'SAVE')->sum('amount');
        $CreditCard_withdraw = PetiCash::where('rules', 'CREDITCARD')->where('types', 'WIDROWS')->sum('amount');

        $CreditCard_balance = $CreditCard_Credit - $CreditCard_withdraw;

        // Pass the calculated data to the view
        return view('backend.library.petiCash.index', compact('mobile_cash_save', 'mobile_cash_withdraw', 'bank_cash_save', 'cash_cash_save', 'cash_cash_withdraw', 'bank_cash_withdraw', 'petiCash', 'hands', 'handCashes_Mobile_balence', 'handCashes_Bank_balence', 'handCashes_Cash_balence', 'handCashes_loan_balence', 'loan_cash_save', 'loan_cash_withdraw', 'mobile_cash', 'bank_cash', 'CreditCard_Credit', 'CreditCard_withdraw', 'CreditCard_balance'));
    }




    public function create()
    {

        $petiCash = PetiCash::all();

        return view('backend.library.petiCash.create', compact('petiCash'));
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
            return redirect()->route('petiCash.index')->withErrors('All fields are null, Please fill up at least one field');
        }

        // Iterate through each set of input fields
        for ($i = 0; $i < count($request->input('types')); $i++) {
            // Create a new cash record
            $cash = new PetiCash();

            // Set the values for each field
            // Normalize inputs to uppercase before saving (also handled by model mutators)
            $cash->rules = isset($request->input('rules')[$i]) ? strtoupper($request->input('rules')[$i]) : null;
            $cash->types = isset($request->input('types')[$i]) ? strtoupper($request->input('types')[$i]) : null;
            $cash->name = isset($request->input('name')[$i]) ? strtoupper($request->input('name')[$i]) : null;
            $cash->date = $request->input('date')[$i];
            $cash->amount = $request->input('amount')[$i];


            // Save the cash record
            $cash->save();
        }

        return redirect()->route('petiCash.index')->withMessages('PetiCash and related data are added successfully!');
    }



    public function show($id)
    {
        $petiCash = PetiCash::findOrFail($id);

        return view('backend.library.petiCash.show', compact('petiCash'));
    }


    public function edit($id)
    {
        $petiCash = PetiCash::findOrFail($id);

        return view('backend.library.petiCash.edit', compact('petiCash'));
    }


    public function update(Request $request, $id)
    {
        $petiCash = PetiCash::findOrFail($id);

        // Normalize inputs to uppercase (also handled by model mutators)
        $petiCash->rules = $request->input('rules') ? strtoupper($request->input('rules')) : null;
        $petiCash->types = $request->input('types') ? strtoupper($request->input('types')) : null;
        $petiCash->name = $request->input('name') ? strtoupper($request->input('name')) : null;
        $petiCash->date = $request->input('date');
        $petiCash->amount = $request->input('amount');

        $petiCash->save();

        // Redirect
        return redirect()->route('petiCash.index')->withMessages('PetiCash and related data are updated successfully!');
    }


    public function destroy($id)
    {
        $petiCash = PetiCash::findOrFail($id);

        $petiCash->delete();


        return redirect()->route('petiCash.index')->withMessage('PetiCash and related data are deleted successfully!');
    }
}
