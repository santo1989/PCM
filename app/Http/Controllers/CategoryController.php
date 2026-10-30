<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $query = Category::query()->orderBy("id");
        if ($search = trim((string) request("search"))) {
            $query->where(function ($q) use ($search) {
                $q->where("name", "like", $this->likeTerm($search))
                    ->orWhere("types", "like", $this->likeTerm($search))
                    ->orWhere("rules", "like", $this->likeTerm($search));
            });
        }
        if ($types = array_filter((array) request("types"))) {
            $query->whereIn("types", array_map("strtoupper", $types));
        }
        $categories = $query->paginate($this->perPage())->withQueryString();
        return view("backend.library.categories.index", compact('categories'));
    }


    public function create()
    {
        $categories = Category::all();
        return view('backend.library.categories.create', compact('categories'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|min:3|max:191',
            'types' => 'required',
            'rules' => 'required',
            'monthly_budget' => 'nullable|numeric|min:0',
        ]);

        // Data insert
        $categories = new Category;
        $categories->name = $request->name;
        $categories->types = $request->types;
        $types = strtoupper((string) $request->types);
        if (in_array($types, ['EXPENSE', 'LOAN', 'RETURN'], true)) {
            $categories->rules = $request->rules;
        } else {
            $categories->rules = '0';
        }
        $categories->monthly_budget = $request->filled('monthly_budget') ? $request->monthly_budget : null;

        $categories->save();

        // Redirect
        return $this->redirectToIndex('categories.index');
    }


    public function show($id)
    {
        $categories = Category::findOrFail($id);
        return view('backend.library.categories.show', compact('categories'));
    }


    public function edit($id)
    {
        $categories = Category::findOrFail($id);
        return view('backend.library.categories.edit', compact('categories'));
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|min:3|max:191',
            'types' => 'required',
            'rules' => 'required',
            'monthly_budget' => 'nullable|numeric|min:0',
        ]);

        // Data update
        $categories = Category::findOrFail($id);
        $categories->name = $request->name;
        $categories->types = $request->types;
        $types = strtoupper((string) $request->types);
        if (in_array($types, ['EXPENSE', 'LOAN', 'RETURN'], true)) {
            $categories->rules = $request->rules;
        } else {
            $categories->rules = '0';
        }
        $categories->monthly_budget = $request->filled('monthly_budget') ? $request->monthly_budget : null;
        $categories->save();

        // Redirect
        return $this->redirectToIndex('categories.index');
    }


    /** "Set as budgets" from the AI outlook: budgets[CATEGORY NAME] => amount (empty clears it). */
    public function applyBudgets(Request $request)
    {
        $request->validate([
            'budgets' => 'required|array',
            'budgets.*' => 'nullable|numeric|min:0',
        ]);

        $categories = Category::where('types', 'EXPENSE')->whereIn('name', array_keys($request->budgets))->get();
        foreach ($categories as $category) {
            $amount = $request->budgets[$category->name];
            $category->monthly_budget = $amount === null || $amount === '' ? null : $amount;
            $category->save();
        }

        // Report pages don't show flash messages; the category list does, with the new budgets.
        return redirect()->route('categories.index')->with('message', "Monthly budgets saved for {$categories->count()} categories.");
    }

    public function destroy($id)
    {
        $categories = Category::findOrFail($id)->delete();

        return $this->redirectToIndex('categories.index')->withMessage('Category are deleted successfully!');
    }
}
