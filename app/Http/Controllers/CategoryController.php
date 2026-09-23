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
                $q->where("name", "like", "%" . $search . "%")
                    ->orWhere("types", "like", "%" . $search . "%")
                    ->orWhere("rules", "like", "%" . $search . "%");
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
        ]);

        $categories = Category::all();
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
        $categories->save();

        // Redirect
        return $this->redirectToIndex('categories.index');
    }


    public function destroy($id)
    {
        $categories = Category::findOrFail($id)->delete();

        return $this->redirectToIndex('categories.index')->withMessage('Category are deleted successfully!');
    }
}
