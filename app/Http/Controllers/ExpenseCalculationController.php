<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;

class ExpenseCalculationController extends Controller
{
    public function index()
    {
        $expenseCalculations = ExpenseCalculation::latest();

        $search_cashes = null; // Initialize the variable
        $search_category_id = null; // Initialize the variable
        $search_types = null; // Initialize the variable
        $search_entry_date_start = null; // Initialize the variable
        $search_entry_date_end = null; // Initialize the variable
        

        // Check if the category_id field is selected
        if (request('category_id')) {
            $expenseCalculations = $expenseCalculations->where('category_id', request('category_id'));
            $search_cashes = $expenseCalculations->get();
            session(['search_cashes' => $search_cashes]);
            $search_category_id = request('category_id');
        }

        // Check if the types field is selected
        if (request('types')) {
            $expenseCalculations = $expenseCalculations->where('types', request('types'));
            $search_cashes = $expenseCalculations->get();
            session(['search_cashes' => $search_cashes]);
            $search_types = request('types');
        }

        // Check if the entry_date fields are filled
        if (request('entry_date_start') && request('entry_date_end')) {
            $expenseCalculations = $expenseCalculations->whereBetween('date', [
                request('entry_date_start'),
                request('entry_date_end')
            ]);
            $search_cashes = $expenseCalculations->get();
            session(['search_cashes' => $search_cashes]);
            $search_entry_date_start = request('entry_date_start');
            $search_entry_date_end = request('entry_date_end');
        }

        $expenseCalculations = $expenseCalculations->paginate(50);
        // $expenseCalculations = $expenseCalculations->get();

        // Check if export format is requested
        $format = strtolower(request('export_format'));

        if ($format === 'xlsx') {
            // Store the necessary values in the session
            session(['export_format' => $format]);

            // Retrieve the values from the session
            $format = session('export_format');
            $search_cashes = session('search_cashes');

            if ($search_cashes == null) {
                return redirect()->route('expenseCalculations.index')->withErrors('First search the data then export');
            } else {
                $data = compact('search_cashes');
                // Generate the view content based on the requested format
                $viewContent = View::make('backend.library.expenseCalculations.export', $data)->render();

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

        $categories = Category::all();
        return view('backend.library.expenseCalculations.index', compact('expenseCalculations', 'search_cashes', 'categories', 'search_category_id', 'search_types', 'search_entry_date_start', 'search_entry_date_end'));
    }




    public function create()
    {

        $expenseCalculations = ExpenseCalculation::all();
        $categories = Category::all();
        return view('backend.library.expenseCalculations.create', compact('expenseCalculations', 'categories'));
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id.*' => 'nullable',
            'name.*' => 'nullable',
            'date.*' => 'nullable|date',
            'amount.*' => 'nullable',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if (
            $request->input('category_id') === null ||
            $request->input('name') === null ||
            $request->input('date') === null ||
            $request->input('amount') === null
        ) {
            return redirect()->route('expenseCalculations.index')->withErrors('All fields are null, Please fill up at least one field');
        }

        // Iterate through each set of input fields
        for ($i = 0; $i < count($request->input('category_id')); $i++) {
            // Create a new cash record
            $cash = new ExpenseCalculation();

            // Set the values for each field
            $cash->category_id = $request->input('category_id')[$i];
            $cash->name = $request->input('name')[$i];
            $cash->date = $request->input('date')[$i];
            $cash->amount = $request->input('amount')[$i];
            $cash->types = Category::find($request->input('category_id')[$i])->types;
            $cash->rules = Category::find($request->input('category_id')[$i])->rules;


            // Save the cash record
            $cash->save();

            //     if($cash->types !='income' && $cash->category_id != 6 && $cash->amount != (null || 0)){
            //         // Create a new cash record
            //         $cash = new HandCash();

            //         // Set the values for each field
            //         $cash->rules = 'Peti';
            //         $cash->types = 'Widrows';
            //         $cash->name = 'Daily Expense Balance';
            //         $cash->date = now()->format('Y-m-d');
            //         $cash->amount = $request->input('amount')[$i]; 
            //         $cash->save();
            // }
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

        return redirect()->route('expenseCalculations.index')->withMessages('ExpenseCalculation and related data are added successfully!');
    }



    public function show($id)
    {
        $expenseCalculations = ExpenseCalculation::findOrFail($id);
        $categories = Category::all();
        return view('backend.library.expenseCalculations.show', compact('expenseCalculations', 'categories'));
    }


    public function edit($id)
    {
        $expenseCalculations = ExpenseCalculation::findOrFail($id);
        $categories = Category::all();
        return view('backend.library.expenseCalculations.edit', compact('expenseCalculations', 'categories'));
    }


    public function update(Request $request, $id)
    {
        $expenseCalculations = ExpenseCalculation::findOrFail($id);

        $expenseCalculations->category_id = $request->input('category_id');
        $expenseCalculations->name = $request->input('name');
        $expenseCalculations->date = $request->input('date');
        $expenseCalculations->amount = $request->input('amount');
        $expenseCalculations->types = Category::find($request->input('category_id'))->types;
        $expenseCalculations->rules = Category::find($request->input('category_id'))->rules;

        $expenseCalculations->save();

        // Redirect
        return redirect()->route('expenseCalculations.index')->withMessages('ExpenseCalculation and related data are updated successfully!');
    }


    public function destroy($id)
    {
        $expenseCalculations = ExpenseCalculation::findOrFail($id);

        $expenseCalculations->delete();


        return redirect()->route('expenseCalculations.index')->withMessage('ExpenseCalculation and related data are deleted successfully!');
    }
    public function filter(Request $request)
    {
        $expenseCalculations = DB::table('expense_calculations')
            ->select('category_id', 'types', DB::raw('SUM(amount) as total_amount'));

        if ($request->has('category_id')) {
            $expenseCalculations->whereIn('category_id', $request->category_id);
        }

        if ($request->has('types')) {
            $expenseCalculations->whereIn('types', $request->types);
        }

        if ($request->has('entry_date_start') && $request->has('entry_date_end')) {
            $expenseCalculations->whereBetween('date', [$request->entry_date_start, $request->entry_date_end]);
        }

        $expenseCalculations = $expenseCalculations->groupBy('category_id', 'types')->get();

        return view('backend.library.expenseCalculations.filter', compact('expenseCalculations'));
    }
}
