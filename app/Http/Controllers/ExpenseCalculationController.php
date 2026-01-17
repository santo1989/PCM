<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use Illuminate\Http\Request;
use App\Http\Requests\ExpenseCalculationRequest;
use Illuminate\Support\Facades\Cache;
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
        // Build base query and apply filters in a single place to avoid repeated queries and session misuse
        $query = ExpenseCalculation::with('category')->orderBy('date', 'desc');

        $search_category_id = request('category_id');
        $search_types = request('types');
        $search_entry_date_start = request('entry_date_start');
        $search_entry_date_end = request('entry_date_end');

        if ($search_category_id) {
            $query->where('category_id', $search_category_id);
        }

        if ($search_types) {
            $query->where('types', strtoupper($search_types));
        }

        if ($search_entry_date_start && $search_entry_date_end) {
            $query->whereBetween('date', [$search_entry_date_start, $search_entry_date_end]);
        }

        // If export requested, run query once and stream the export view
        $format = strtolower(request('export_format', ''));
        if ($format === 'xlsx') {
            $search_cashes = $query->get();
            if ($search_cashes->isEmpty()) {
                return redirect()->route('expenseCalculations.index')->withErrors('First search the data then export');
            }

            $viewContent = View::make('backend.library.expenseCalculations.export', compact('search_cashes'))->render();
            $filename = Auth::user()->name . '_' . Carbon::now()->format('Y_m_d') . '_' . time() . '.xls';
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            return response()->make($viewContent, 200, $headers);
        }

        $expenseCalculations = $query->paginate(50)->withQueryString();

        // Efficient category usage sorting with single query using left join and count
        $categories = Category::leftJoin('expense_calculations', 'categories.id', '=', 'expense_calculations.category_id')
            ->select('categories.*', DB::raw('COUNT(expense_calculations.id) as uses_count'))
            ->groupBy('categories.id')
            ->orderByDesc('uses_count')
            ->get();

        // No session-based search_cashes by default (we only use it for export above)
        $search_cashes = null;

        return view('backend.library.expenseCalculations.index', compact('expenseCalculations', 'search_cashes', 'categories', 'search_category_id', 'search_types', 'search_entry_date_start', 'search_entry_date_end'));
    }




    public function create()
    {

        $expenseCalculations = ExpenseCalculation::all();
        $categories = Category::all();
        return view('backend.library.expenseCalculations.create', compact('expenseCalculations', 'categories'));
    }


    public function store(ExpenseCalculationRequest $request)
    {
        // validated already by ExpenseCalculationRequest

        $categoryIds = $request->input('category_id', []);
        $names = $request->input('name', []);
        $dates = $request->input('date', []);
        $amounts = $request->input('amount', []);

        // Validate at least one non-empty row
        $hasRow = false;
        foreach ($amounts as $amt) {
            if ($amt !== null && $amt !== '') {
                $hasRow = true;
                break;
            }
        }
        if (!$hasRow) {
            return redirect()->route('expenseCalculations.index')->withErrors('All fields are null, Please fill up at least one field');
        }

        // Preload categories to avoid N+1
        $categoriesMap = [];
        $idsToLoad = array_filter($categoryIds, function ($v) {
            return $v !== null && $v !== '';
        });
        if (!empty($idsToLoad)) {
            $categoriesMap = Category::whereIn('id', $idsToLoad)->get()->keyBy('id');
        }

        $expenseRows = [];
        $countRows = max(count($categoryIds), count($names), count($dates), count($amounts));
        for ($i = 0; $i < $countRows; $i++) {
            $amt = $amounts[$i] ?? null;
            if ($amt === null || $amt === '') continue; // skip empty

            $catId = $categoryIds[$i] ?? null;
            $expenseRows[] = [
                'category_id' => $catId,
                'name' => isset($names[$i]) ? strtoupper($names[$i]) : null,
                'date' => $dates[$i] ?? null,
                'amount' => $amt,
                'types' => isset($categoriesMap[$catId]) ? strtoupper($categoriesMap[$catId]->types) : null,
                'rules' => isset($categoriesMap[$catId]) ? strtoupper($categoriesMap[$catId]->rules) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $handRules = $request->input('rules', []);
        $handTypes = $request->input('types', []);
        $handNames = $request->input('name', []);
        $handDates = $request->input('date', []);
        $handAmounts = $request->input('amount', []);

        $handRows = [];
        $handCount = count($handTypes);
        for ($i = 0; $i < $handCount; $i++) {
            $amt = $handAmounts[$i] ?? null;
            if ($amt === null || $amt === '') continue;
            $handRows[] = [
                'rules' => isset($handRules[$i]) ? strtoupper($handRules[$i]) : null,
                'types' => isset($handTypes[$i]) ? strtoupper($handTypes[$i]) : null,
                'name' => isset($handNames[$i]) ? strtoupper($handNames[$i]) : null,
                'date' => $handDates[$i] ?? null,
                'amount' => $amt,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Use transaction and bulk inserts for performance and atomicity
        DB::transaction(function () use ($expenseRows, $handRows) {
            if (!empty($expenseRows)) {
                ExpenseCalculation::insert($expenseRows);
            }
            if (!empty($handRows)) {
                HandCash::insert($handRows);
            }
        });

        // Invalidate dashboard caches for affected periods
        try {
            $years = [];
            $months = [];
            foreach ($expenseRows as $r) {
                if (!empty($r['date'])) {
                    $y = date('Y', strtotime($r['date']));
                    $m = date('m', strtotime($r['date']));
                    $years[$y] = true;
                    $months[$y . ':' . $m] = true;
                }
            }
            foreach ($handRows as $r) {
                if (!empty($r['date'])) {
                    $y = date('Y', strtotime($r['date']));
                    $m = date('m', strtotime($r['date']));
                    $years[$y] = true;
                    $months[$y . ':' . $m] = true;
                }
            }
            // forget common keys
            Cache::forget('dashboard:monthly_trend:last12');
            foreach (array_keys($years) as $y) {
                Cache::forget("dashboard:category_breakdown:{$y}:all");
                Cache::forget("dashboard:top_categories:{$y}");
            }
            foreach (array_keys($months) as $ym) {
                [$y, $m] = explode(':', $ym);
                Cache::forget("dashboard:summary:{$y}:{$m}");
                Cache::forget("dashboard:category_breakdown:{$y}:{$m}");
            }
        } catch (\Exception $e) {
            // don't block workflow if cache clearing fails
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


    public function update(ExpenseCalculationRequest $request, $id)
    {
        $expenseCalculations = ExpenseCalculation::findOrFail($id);

        $expenseCalculations->category_id = $request->input('category_id');
        $expenseCalculations->name = strtoupper($request->input('name'));
        $expenseCalculations->date = $request->input('date');
        $expenseCalculations->amount = $request->input('amount');

        // Avoid duplicate DB calls by loading the category once
        $category = null;
        if ($request->input('category_id')) {
            $category = Category::find($request->input('category_id'));
        }
        $expenseCalculations->types = isset($category) ? strtoupper($category->types) : strtoupper($expenseCalculations->types);
        $expenseCalculations->rules = isset($category) ? strtoupper($category->rules) : strtoupper($expenseCalculations->rules);

        $expenseCalculations->save();

        // Clear cache for this record's period
        try {
            if ($expenseCalculations->date) {
                $y = date('Y', strtotime($expenseCalculations->date));
                $m = date('m', strtotime($expenseCalculations->date));
                Cache::forget('dashboard:monthly_trend:last12');
                Cache::forget("dashboard:summary:{$y}:{$m}");
                Cache::forget("dashboard:category_breakdown:{$y}:all");
                Cache::forget("dashboard:category_breakdown:{$y}:{$m}");
            }
        } catch (\Exception $e) {
        }

        // Redirect
        return redirect()->route('expenseCalculations.index')->withMessages('ExpenseCalculation and related data are updated successfully!');
    }


    public function destroy($id)
    {
        $expenseCalculations = ExpenseCalculation::findOrFail($id);
        $date = $expenseCalculations->date;
        $expenseCalculations->delete();

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
            $expenseCalculations->whereIn('types', array_map('strtoupper', (array) $request->types));
        }

        if ($request->has('entry_date_start') && $request->has('entry_date_end')) {
            $expenseCalculations->whereBetween('date', [$request->entry_date_start, $request->entry_date_end]);
        }

        $expenseCalculations = $expenseCalculations->groupBy('category_id', 'types')->get();

        return view('backend.library.expenseCalculations.filter', compact('expenseCalculations'));
    }
}
